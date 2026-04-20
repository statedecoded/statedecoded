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

- [ ] **`$this->logger->message(...)` at line 305** — there is no `$this` in this procedural file. This is a fatal the first time the `populate_db()` branch fails. Replace with `$logger->message(...)` (the procedural `$logger` was instantiated on line 27) or with `$parser->logger->message(...)`.
- [ ] **Typo: HTTPS branch sets `$base_url`, every downstream line uses `$edition_url_base`** (lines 442–455). Result: the HTTPS branch produces an "undefined variable" notice and starts the URL with `https://…` missing. Set `$edition_url_base = 'https://';` in the HTTPS branch.
- [ ] **Unchecked `$_POST['action']`.** Lines 158, 200, 210, 240, 320, 338, 356 dereference `$_POST['action']` directly. On PHP 8 these emit warnings and the `==` against a string will still match `null` inconsistently. Gate the whole block on `isset($_POST['action'])`.
- [ ] **`$body .=` starts undefined** in several branches (e.g. the `update_db` branch at line 202). Initialize `$body = '';` at the top.
- [ ] **`ADMIN_PASSWORD` is plaintext string comparison** (`!= ADMIN_PASSWORD`). Use `hash_equals(ADMIN_PASSWORD_HASH, ...)` or switch to `password_hash`/`password_verify`, and stop keeping the plaintext credential in `config.inc.php`.

### Router (`includes/class.Router.inc.php`)

- [ ] **Fallback return has the wrong shape.** `getRoute()` line 111 returns `array($this->handlers[end($this->routes)], array())` but `$this->handlers[$name]` is `[$regex, $handler]`, not the handler. Callers in `MasterController` destructure `[$handler, $args]` and will try to treat a 2-element regex/handler pair as a `[class, method]` pair. Replace with `array($this->handlers[end($this->routes)][1], array())`.
- [ ] **Insert-before semantics**: `array_splice(..., -1, 0, $name)` relies on the `default` route being added first. Add an assertion (or refactor) so the ordering invariant is explicit.
- [ ] **Regex is wrapped with `/.../` after `str_replace('/','\/', …)`** — this double-escapes already-escaped slashes in author-supplied routes and will silently fail on patterns that contain literal backslashes. Switch to a non-slash delimiter (e.g. `#...#`) and drop the string replace.

### Database (`class.Database.inc.php`, `class.DatabaseStatement.inc.php`)

- [x] **`Database::query()` overrides `PDO::query()` with an incompatible signature.** PHP 8 requires `query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs)`. Update signature to `#[\ReturnTypeWillChange]` or match the parent.
- [x] **`DatabaseStatement::fetch()/fetchAll()/fetchObject()/setFetchMode()` signatures** also diverge from PHP 8 `PDOStatement`. Adopt the current upstream signatures or add `#[\ReturnTypeWillChange]`.
- [x] **`&$pdo_statement` by reference then `$this->pdo_statement =& $statement->pdo_statement;`** (line 139) — no longer needed for PHP objects; remove the `&` to avoid "reference to a non-variable" notices.

### Data / SQL

- [ ] **`INSERT DELAYED` in `class.Law.inc.php:652`** — removed in MySQL 5.7+, ignored by MariaDB 10. Replace with a plain `INSERT` (writes happen per pageview — if load is a concern, queue to a background writer).
- [ ] **`PDO::FETCH_OBJ` on result of `parent::query` without error check** — several sites in `class.Law.inc.php` and `class.Dictionary.inc.php` call `$db->query(...)` and then call `->fetch()` without verifying the statement object is truthy. With ERRMODE_EXCEPTION this will just throw, but the silent mode leaves you null-dereferencing.

### Other

- [ ] **`class.Cache.inc.php:40`** hashes the server name with MD5 and truncates to 8 bytes for a cache prefix — fine as a key but on PHP 8 with strict extension loading you should detect Memcached vs. Redis deterministically rather than by class existence.
- [ ] **`functions.inc.php:420` `join_paths()`** uses `func_get_args()` — fine in PHP 8 but the splat variadic (`function join_paths(...$args)`) is clearer.
- [ ] **`functions.inc.php:479` `remove_dir()`** shells out to `system('/bin/rm -rf …')` — replace with a PHP recursive removal using `RecursiveIteratorIterator` to avoid the shell dependency and path-traversal risks.
- [ ] **Uninitialized globals.** PHP 8 warns on `global $foo; if ($foo)…` when `$foo` was never set. Audit every `global $db` / `global $cache` call site (30+ locations) and guard with `isset()`.
- [ ] **Deprecated ctype behavior**: `ord($term[$i])` comparisons to `97..122` and `65..90` are re-implementing `ctype_lower` / `ctype_upper`. Replace for readability.
- [ ] **Unchecked `$_SERVER` reads**: `HTTPS`, `SERVER_PORT`, `SERVER_NAME`, `REDIRECT_URL`, `REQUEST_URI`, `PHP_AUTH_USER`, `PHP_AUTH_PW` — wrap with `isset()` / `?? ''` to silence PHP 8 warnings under CLI or alternative SAPIs.
- [ ] **XML handling**: `decode_entities` forces `ISO-8859-1` and comments say "UTF-8 does not work!" — this was an old PHP 5.3 bug; UTF-8 has worked since PHP 5.4 and should be the default now.

---

## Phase 3 — Modernization (nice-to-have, larger lifts)

- [ ] **Replace the bundled Solarium 2.4.0** (`includes/Solarium/**`, 200+ files, global namespace) with a Composer-installed `solarium/solarium ^6` behind a thin adapter (`SearchEngineInterface` is already the seam). This removes the largest chunk of legacy code in one go.
- [ ] **Introduce Composer** (`composer.json`) and switch `functions.inc.php:40` autoload to `vendor/autoload.php` + a PSR-4 section mapping `StateDecoded\` to `includes/`. Keep a compatibility shim for the `class.Foo.inc.php` filename convention during the transition.
- [ ] **Add PHPStan or Psalm at level 1** to the repo; wire into CI. This will surface the remaining undefined-variable and wrong-type issues automatically instead of by hand.
- [ ] **Replace HTTP Basic admin auth** with session-based login; store `password_hash`ed credentials in the DB, not in `config.inc.php`.
- [ ] **Replace home-grown test bootstrap** in `includes/test/` with PHPUnit 10 + a `phpunit.xml`. Move fixtures out of the test files.
- [ ] **Vendor or drop WordPress-derived helpers** in `functions.inc.php` (`wptexturize`, `_wptexturize_pushpop_element`, `convert_entity`). Consider `league/commonmark` or a small texturize library.
- [ ] **Frontend modernization** (`htdocs/themes/StateDecoded2013/`): jQuery, Bootstrap modal, Modernizr, js-webshim are all 2013-era. Replace with plain modern JS (optional — not strictly needed for PHP 8 support).
- [ ] **Move secrets out of `config.inc.php`** to environment variables (e.g. via `vlucas/phpdotenv`); the current sample commits credentials' *shape* but real deployments end up with them in source.
- [ ] **Remove Apache-specific assumptions** (`mod_env` check in `index.php`, `.htaccess`-rewriting bootstrap). Provide an nginx/FPM example and let `INCLUDE_PATH` come from the environment.
- [ ] **Update `@version` docblocks** throughout (`PHP version 5` → `PHP version 8`) once each file is audited. Drive this with a find/replace only after the phase-1 work is complete, so the docblock becomes an honest claim.
- [ ] **Audit all `TODO` / `FIXME` comments** inside the codebase; there are ~25 in the parser and exporter paths (see `class.AmericanLegal.inc.php`, `class.ParserController.inc.php`, `class.Law.inc.php`, `class.ExportHTML.inc.php`). Decide which are still relevant and either fix or delete.
- [ ] **Consider dropping `Municode` and `AmericanLegal` importers** if no current deployment uses them — they are 2k+ LOC each and dominate the PHP-5-isms found above.
