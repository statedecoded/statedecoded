# TODO — PHP 5 → PHP 8 Modernization and Bug Fixes

Organized in phases. Phase 1 is required before the code will even load on PHP 8; phase 2 is the long tail of deprecations, warnings, and genuine bugs the scan turned up; phase 3 is optional-but-worth-it cleanup.

Unless stated otherwise, file paths are relative to the repo root.

---

## Phase 1 — PHP 8 hard blockers

These produce fatal errors on PHP 7.4+ or PHP 8.0+ and will prevent the site from booting.

- [x] **Replace `$str{$i}` curly-brace string offset syntax** (removed in PHP 8.0). Change every `$var{$i}` to `$var[$i]`. Locations:
  - `includes/class.Law.inc.php:994` — `ord($term{$i})`
  - `includes/class.Municode.inc.php:1653` — `ord($term{$i})`
  - `includes/class.AmericanLegal.inc.php:2256` — `ord($term{$i})`
  - `includes/class.State-sample.inc.php:1955` — `ord($term{$i})`
  - `includes/plugins/class.ExportUSLM.inc.php:196` — `ord($term{$i})`
- [x] **Replace `->{0}` and `->{$i}` dynamic property-as-array usage** on `stdClass` (still works but emits deprecation warnings on PHP 8.2+ for dynamic properties). Either define the shape via real arrays or with `#[\AllowDynamicProperties]` on the classes involved. Frequent users:
  - `includes/class.APISearchController.inc.php` (~12 sites: `$response->results->{$i}->…`)
  - `includes/class.State-sample.inc.php` (`$this->decisions->{$i}->…`, `$this->citation->{0}->…`)
  - `includes/class.Municode.inc.php` (`$final->{$i}->…`, `$this->code->section->{$this->i}->…`)
  - `includes/class.Dictionary.inc.php:115-117` (`$dictionary->{0}`)
- [x] **Fix `array_shift(explode(...))` expressions** — `array_shift` requires a variable reference; passing a function return is an error on PHP 7.0+. Replace with `explode(...)[0]` or `strtok`.
  - `includes/class.State-sample.inc.php:217, 227`
- [x] **Ensure `DatabaseStatement` constructor doesn't try to call `PDOStatement::__construct`.** It already avoids that, but PHP 8 tightened PDOStatement's lifecycle; `extends PDOStatement` now emits a deprecation/error in some builds. Consider reworking `class.DatabaseStatement.inc.php` to `implements` a small interface and compose a `PDOStatement` rather than extending it.
- [x] **`includes/functions.inc.php:531` has a commented-out `/e` preg_replace modifier** — confirm it stays commented (removed in PHP 7.0). Delete the dead line.
- [x] **Non-numeric math / implicit string-to-number coercion warnings.** Grep once the above is fixed — PHP 8 throws warnings; audit `ParserController`, `Law`, and `State-sample` for `$x + $maybe_string_field` patterns. Also replaced deprecated `FILTER_SANITIZE_STRING` with `FILTER_DEFAULT` across 10 files.
- [x] **PDO default error mode.** `htdocs/index.php:153` sets `PDO::ERRMODE_SILENT`. In PHP 8 the default is `ERRMODE_EXCEPTION` and a lot of code here assumes `execute()` returns `false` rather than throwing. Either keep the silent mode explicitly everywhere that builds a PDO (tests already do) or — better — convert the call sites to try/catch.
- [x] **`spl_autoload_register('__autoload_libraries')`** (`includes/functions.inc.php:40`) — passing a global function by name still works, but the function name starting with `__autoload` is reserved by PHP and emits warnings. Rename to `statedecoded_autoload_libraries` (or similar) and update the registration.

---

## Phase 2 — Bugs and code correctness

These exist regardless of PHP version but will also be unmasked by stricter PHP 8 warnings.

### Admin / importer (`htdocs/admin/index.php`)

- [x] **`$this->logger->message(...)` at line 305** — there is no `$this` in this procedural file. This is a fatal the first time the `populate_db()` branch fails. Replace with `$logger->message(...)` (the procedural `$logger` was instantiated on line 27) or with `$parser->logger->message(...)`.
- [x] **Typo: HTTPS branch sets `$base_url`, every downstream line uses `$edition_url_base`** (lines 442–455). Result: the HTTPS branch produces an "undefined variable" notice and starts the URL with `https://…` missing. Set `$edition_url_base = 'https://';` in the HTTPS branch.
- [x] **Unchecked `$_POST['action']`.** Lines 158, 200, 210, 240, 320, 338, 356 dereference `$_POST['action']` directly. On PHP 8 these emit warnings and the `==` against a string will still match `null` inconsistently. Gate the whole block on `isset($_POST['action'])`.
- [x] **`$body .=` starts undefined** in several branches (e.g. the `update_db` branch at line 202). Initialize `$body = '';` at the top.
- [x] **`ADMIN_PASSWORD` is plaintext string comparison** (`!= ADMIN_PASSWORD`). Use `hash_equals(ADMIN_PASSWORD_HASH, ...)` or switch to `password_hash`/`password_verify`, and stop keeping the plaintext credential in `config.inc.php`.

### Router (`includes/class.Router.inc.php`)

- [x] **Fallback return has the wrong shape.** `getRoute()` line 111 returns `array($this->handlers[end($this->routes)], array())` but `$this->handlers[$name]` is `[$regex, $handler]`, not the handler. Callers in `MasterController` destructure `[$handler, $args]` and will try to treat a 2-element regex/handler pair as a `[class, method]` pair. Replace with `array($this->handlers[end($this->routes)][1], array())`.
- [ ] **Insert-before semantics**: `array_splice(..., -1, 0, $name)` relies on the `default` route being added first. Add an assertion (or refactor) so the ordering invariant is explicit.
- [x] **Regex is wrapped with `/.../` after `str_replace('/','\/', …)`** — this double-escapes already-escaped slashes in author-supplied routes and will silently fail on patterns that contain literal backslashes. Switch to a non-slash delimiter (e.g. `#...#`) and drop the string replace.

### Database (`class.Database.inc.php`, `class.DatabaseStatement.inc.php`)

- [x] **`Database::query()` overrides `PDO::query()` with an incompatible signature.** PHP 8 requires `query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs)`. Update signature to `#[\ReturnTypeWillChange]` or match the parent.
- [x] **`DatabaseStatement::fetch()/fetchAll()/fetchObject()/setFetchMode()` signatures** also diverge from PHP 8 `PDOStatement`. Adopt the current upstream signatures or add `#[\ReturnTypeWillChange]`.
- [x] **`&$pdo_statement` by reference then `$this->pdo_statement =& $statement->pdo_statement;`** (line 139) — no longer needed for PHP objects; remove the `&` to avoid "reference to a non-variable" notices.

### Data / SQL

- [x] **`INSERT DELAYED` in `class.Law.inc.php:652`** — removed in MySQL 5.7+, ignored by MariaDB 10. Replace with a plain `INSERT` (writes happen per pageview — if load is a concern, queue to a background writer).
- [x] **`PDO::FETCH_OBJ` on result of `parent::query` without error check** — fixed the inverted guard in `class.Law.inc.php` (`$result !== TRUE` → `$result !== FALSE`).

### Other

- [x] **`class.Cache.inc.php:40`** — fixed `$_SERVER['SERVER_NAME']` to use `?? ''` so it's safe under CLI.
- [x] **`functions.inc.php:420` `join_paths()`** uses `func_get_args()` — replaced with `...$args` variadic.
- [x] **`functions.inc.php:479` `remove_dir()`** shells out to `system('/bin/rm -rf …')` — replaced with PHP `RecursiveIteratorIterator` removal.
- [x] **Uninitialized globals.** Audited all 30+ `global $db` / `global $cache` call sites. No unguarded boolean uses found — all conditional `$cache` accesses already use `isset($cache)`. Removed dead `global $cache;` from the four `Cache` class methods (they use `$this->cache`, not `$cache`), and removed dead file-level `global $db;` from three task-action files.
- [x] **Deprecated ctype behavior**: `ord($term[$i])` comparisons to `97..122` and `65..90` replaced with `ctype_lower` / `ctype_upper` in Law, Municode, AmericanLegal, State-sample, ExportUSLM.
- [x] **Unchecked `$_SERVER` reads** in `admin/index.php`: `HTTPS`, `SERVER_PORT`, `SERVER_NAME` guarded with `?? ''` / null-coalescing; `PHP_AUTH_USER`/`PHP_AUTH_PW` already guarded by `isset()` check.
- [x] **XML handling**: `decode_entities` updated from `ISO-8859-1` to `UTF-8`.
- [x] **Fix "Amendment Attempts"** sidebar box, which is unformatted, presumably because it has the wrong class name.
- [ ] **Fix formatting of subsection structures**, which aren't indented correctly relative to one another, nor is the text indented correctly relative to the subsection number.
- [ ] **Ensure that internal API key is set automatically**, since right now it's not happening.

---

## Phase 3 — Modernization (nice-to-have, larger lifts)

- [x] **Replace the bundled Solarium 2.4.0** (`includes/Solarium/**`, 200+ files, global namespace) with a Composer-installed `solarium/solarium ^6` behind a thin adapter (`SearchEngineInterface` is already the seam). This removes the largest chunk of legacy code in one go.
- [x] **Introduce Composer** (`composer.json`) and switch `functions.inc.php:40` autoload to `vendor/autoload.php` + a PSR-4 section mapping `StateDecoded\` to `includes/`. Keep a compatibility shim for the `class.Foo.inc.php` filename convention during the transition.
- [x] **Add PHPStan or Psalm at level 1** to the repo; wire into CI. This will surface the remaining undefined-variable and wrong-type issues automatically instead of by hand.
- [x] **Replace home-grown test bootstrap** in `includes/test/` with PHPUnit 10 + a `phpunit.xml`. Move fixtures out of the test files.
- [x] **Frontend modernization** (`htdocs/themes/StateDecoded2013/`): jQuery, Bootstrap modal, Modernizr, js-webshim are all 2013-era. Evaluate which are still needed, and of those that are needed, use modern versions.
- [x] **Create smoke tests** to verify that the website works post-import, both the front end and the API
- [x] **Create a build process** to have GitHub Actions download dependencies, load all dependencies, and run all tests
- [x] **Update `@version` docblocks** throughout (`PHP version 5` → `PHP version 8`) once each file is audited. Drive this with a find/replace only after the phase-1 work is complete, so the docblock becomes an honest claim.
- [x] **Audit all `TODO` / `FIXME` comments** inside the codebase; there are ~25 in the parser and exporter paths (see `class.AmericanLegal.inc.php`, `class.ParserController.inc.php`, `class.Law.inc.php`, `class.ExportHTML.inc.php`). Decide which are still relevant and either fix or delete.
- [x] **Modernize the SCSS** to remove Compass imports and replace any Compass mixins with plain CSS.
- [x] **Add SCSS to the npm pipeline** so that CSS can be compiled within the build process.
- [ ] **Vendor or drop WordPress-derived helpers** in `functions.inc.php` (`wptexturize`, `_wptexturize_pushpop_element`, `convert_entity`). Consider `league/commonmark` or a small texturize library.
- [x] **Provide sample XML for testing the import**, and add that to the test pipeline.
- [x] **Fix styling of qTip popups**, which are missing images.
- [x] **Add tests specific to the test content**, such as ensuring that the number of XML files matches the number of laws, that the catch lines are accurate, that the histories are accurate, that the text is accurate, etc.
- [ ] **Restore missing filetype icons** that are missing from the listing of export file formats at the footer of each law.
- [x] **Fix character encoding problem in HTML export**, which shows bad UTF-8 characters for each law.
- [x] **Fix CSS for breadcrumb trail**, which looks all wrong, despite looking correct on the live site.
- [x] **Solve the problem of the missing API token** that prevents from API queries from working, by autogenerating it for Docker.

---

## Tests

Categories of tests to add against the sample import data in `deploy/import-data/`. The DB is populated automatically in CI by the "Import sample data" workflow step.

- [x] **Import integrity tests** — verify the import produced what we expect: total law count, total structure count, total text rows, edition count. Cheap and catches major regressions.
- [x] **Specific-content tests** — pick well-known laws (1-1, 18.2-9, 3.2-100) and assert their fields match the source XML: `catch_line`, `section_number`, `history`, the actual `<text>` content. Catches parser regressions like the recent plain-text and hierarchy bugs.
- [x] **Structural hierarchy tests** — walk the structure tree: assert that "Title 18.2" → "Chapter 1" → "Article 3" → contains "§ 18.2-9". Catches `structure_unified` view bugs and ancestry/permalink generation issues.
- [x] **Permalink / URL tests** — assert `permalinks` rows exist for every imported law and every structural unit, and that the URLs match expectations (e.g., `/18.2-9/`). Catches regressions in `build_permalinks()` like the memory-exhaustion bug we already fixed.
- [x] **Export tests** — after the export step, assert that the expected files exist on disk in all formats, that the file sizes are plausible, and that a fragment of text found in the law is also found in the files.
- [ ] **Cross-reference / autolinker tests** — pick a law whose text contains "§ X.X-X" references and assert those reference rows exist in `laws_references`.
- [ ] **HTTP smoke tests** — extend the existing `SmokeTest.php` (currently self-skips without `SMOKE_BASE_URL`) with assertions tied to the test content. Best run via a CI step that spins up `php -S` rather than requiring Docker.
