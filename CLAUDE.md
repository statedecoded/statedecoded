# The State Decoded — Codebase Overview

This document is a high-level map for anyone (human or AI) working on modernizing this project. The original release was v1.0 (tagged 2017-04-24). Active modernization is underway on the `modernize` branch: PHP 5 → PHP 8 compatibility work (phases 1 and 2) is complete; phase 3 (session auth, frontend, env-var config) is in progress.

## What this project is

The State Decoded is a PHP web application that ingests a structured legal code (laws organized into titles, chapters, etc.) and publishes it as a searchable, interlinked, API-accessible website. It was funded by the Knight Foundation News Challenge and ran production sites for Virginia, Chicago, San Francisco, and other jurisdictions.

## Target environment (current)

- **PHP ≥8.0** — `composer.json` enforces this. The Docker dev environment runs PHP 8.3 Apache. Phase 1 and 2 compat work is complete.
- **MySQL 8.0** (via PDO) — DSN in `config.inc.php`, DDL in `htdocs/admin/statedecoded.sql`.
- **Apache** with `mod_env`, `mod_rewrite`, and writable `.htaccess`. `htdocs/index.php` still hard-requires `HTTP_MOD_ENV`; in Docker this is satisfied by `SetEnv` in the vhost config, bypassing the `.htaccess`-rewriting bootstrap.
- **Composer** — manages `phpstan/phpstan ^2` and `phpunit/phpunit ^10`. Run `composer install` before working outside Docker.
- **npm** — manages front-end dependencies (jQuery, jQuery UI, qtip2, Mousetrap, Font Awesome) and the Dart Sass compiler. Run `npm install && npm run build` once after cloning; `docker-run.sh` does this automatically if the assets are missing. The build step copies JS/CSS/font files from `node_modules/` and compiles `scss/application.scss` → `css/application.css` (Dart Sass). All built files are git-ignored.
- **Optional**: Memcached (see `--profile cache`), Varnish, Pandoc + pdflatex (for EPUB/Word/PDF exports), Tidy.

## Local development (Docker)

The repo ships a Docker dev environment. Standard workflow:

```bash
cp .env.example .env          # one-time; edit if needed
npm install && npm run build  # one-time; downloads and copies front-end assets
./deploy/docker-run.sh        # builds image, starts app + db, prints status
open http://localhost:8080/   # site (empty until data is imported)
open http://localhost:8080/admin/   # importer UI (HTTP Basic: admin / admin)

./docker-test.sh              # run PHPStan + PHPUnit inside the container
./deploy/docker-stop.sh       # stop containers (preserves DB volume)
docker compose -f deploy/docker-compose.yml down -v   # full reset including DB data
```

Optional services:

```bash
docker compose -f deploy/docker-compose.yml --profile cache up -d   # add Memcached; set CACHE_HOST=memcached CACHE_PORT=11211 in .env
```

**Note on the test database**: `deploy/docker/db/init/02-test-db.sql` creates `statedecoded_test` at first DB init. If you started the DB before that file existed (e.g. from a prior checkout), run it manually:
```bash
docker compose -f deploy/docker-compose.yml exec db mysql -u root -prootpassword statedecoded < deploy/docker/db/init/02-test-db.sql
```

## Repository layout

```
statedecoded                 Root CLI task runner (php statedecoded <action>)
htdocs/                      Apache document root
  index.php                  Front controller; bootstraps DB, cache, MasterController
  home.php about.php law.php structure.php search.php edition.php 404.php
  admin/index.php            Password-gated importer / setup UI (HTTP Basic auth)
  admin/statedecoded.sql     Full schema
  themes/StateDecoded2013/   Default theme (PHP partials + SASS + JS)
  downloads/ content/        Generated bulk exports + custom content
includes/                    All PHP classes and libraries
  class.*.inc.php            Core classes; filename pattern is autoloader-driven
  plugins/                   Export plugins (JSON, HTML, SDXML, PDF, EPUB, Word, USLM)
  task/                      CLI actions for the `statedecoded` task runner
  migrations/                Schema migrations (class.Migration_<timestamp>.inc.php)
  test/                      PHPUnit 10 test suite (DatabaseTest, APITest, HelpActionTest)
  functions.inc.php          Global helpers; also requires vendor/autoload.php
  routes.inc.php             Route table consumed by Router
  config-sample.inc.php      Copy to config.inc.php for a bare install
  class.State-sample.inc.php Copy to class.State.inc.php and customize per deployment
deploy/                      DevOps assets
  docker-compose.yml         Orchestrates app + db (+ optional memcached profile)
  docker-run.sh              Start the stack
  docker-stop.sh             Stop the stack
  docker/                    Docker build files
    app/                     Dockerfile, apache-vhost.conf, php.ini, entrypoint.sh
    config/                  config.inc.docker.php and config-test.inc.docker.php templates
    db/init/                 02-test-db.sql (creates statedecoded_test)
docker-test.sh               Run PHPStan + PHPUnit inside the container
composer.json / composer.lock  Manages phpstan, phpunit
vendor/                      Composer-managed (gitignored; built inside Docker image)
lexis-nexis.xsl sample.xsl   XSLTs for transforming source XML → State Decoded XML
```

## How a request flows

1. `htdocs/index.php` reads `INCLUDE_PATH` from `$_SERVER` (set by Apache `SetEnv` in Docker; falls back to scanning dirs and caching into `.htaccess`). Requires `config.inc.php`, opens PDO connection as `$db`.
2. `functions.inc.php` loads `vendor/autoload.php` (Composer) then registers `statedecoded_autoload_libraries` via `spl_autoload_register` — searches the PHP include path for `class.<ClassName>.inc.php`.
3. `MasterController::execute()` parses `$_SERVER['REDIRECT_URL']` / `REQUEST_URI` through `Router::getRoute()`, which walks `routes.inc.php` regex entries.
4. A route handler is either a `[ClassName, method]` pair (controller) or a relative `.php` path under `htdocs/` (page).
5. Controllers extend `BaseController` / `BaseAPIController`. API controllers funnel through `class.API.inc.php` and render via `class.Content.inc.php` + theme partials.

## Key classes

| Class | Role |
| --- | --- |
| `MasterController` | Dispatches by regex route |
| `Router` | Regex route table, insert-before supported |
| `BaseController` / `BaseAPIController` | Shared state for controllers; API controllers check keys, set JSON headers |
| `Database` / `DatabaseStatement` | Thin PDO / PDOStatement wrappers adding reconnect-on-"gone away" and richer error formatting. `recoverError`, `fetchErrors`, `formatErrors` are public. |
| `Law` (1.4k LOC), `Structure`, `Dictionary`, `Edition`, `Permalink` | Domain models, each does its own SQL |
| `ParserController` (2.2k LOC) | The importer — environment tests, DB population, parsing, sitemap generation, indexing |
| `AmericanLegal` / `Municode` / `State-sample` | Vendor- or jurisdiction-specific XML parsers (multi-thousand LOC each) |
| `Autolinker`, `Search*`, `SqlSearchEngine` | Rewrites section/term references into hyperlinks; search runs against MySQL via `SqlSearchEngine` behind the `SearchIndex` abstraction. |
| `Plugin` + `EventManager` | Hook/event system used by the export plugins |
| `TaskRunner` + `task/*Action` | CLI dispatcher for the `statedecoded` script |

## Conventions that matter when editing

- **Filenames drive autoloading.** `class.Foo.inc.php` ⇒ class `Foo`. New classes must follow this pattern or they will silently fail to load.
- **Tabs for indentation**, Allman-style braces, PEAR-ish docblocks. Keep this style when editing existing files.
- **No namespaces in application code.** Everything is in the global namespace. Composer-installed packages (PHPUnit, PHPStan) use their own namespaces and are loaded via `vendor/autoload.php`.
- **Per-install customization happens in two files**: `config.inc.php` (constants) and `class.State.inc.php` (subclasses/overrides). Neither is committed — only `-sample` templates. Docker uses `deploy/docker/config/config.inc.docker.php` which reads from environment variables.
- **Configuration is a wall of `define()` calls**, including JSON-encoded blobs for `SEARCH_CONFIG` and `PLUGINS`. There is no runtime config object.
- **Globals are pervasive.** `$db` and `$cache` are referenced via `global` in most domain classes. This is the intended pattern. All `global $cache` call sites guard against an uninitialized cache with `isset($cache)`.

## If you're modernizing

1. **Use Docker.** Run `./deploy/docker-run.sh`, then verify and run tests with `./docker-test.sh` before and after any change.
2. **Don't touch `vendor/`** — it's Composer-managed and gitignored.
3. **The parser is the largest risk** — `ParserController` + `AmericanLegal` + `Municode` is 6k+ LOC and the least exercised path. Modernize it last.
4. **PHPStan at level 1 is the bar** — `./docker-test.sh phpstan` must stay clean on every commit.
