# State Decoded Test Suite

## Overview

Tests live in `includes/test/` and run under PHPUnit 10 (installed via Composer). The suite
currently covers `Database` / `DatabaseStatement` and the `HelpAction` CLI task. `APITest`
is skipped pending a full parser/import setup.

**The test suite uses a separate database (`statedecoded_test`) and will truncate its
tables on each run. Never point it at a production database.**

## Running in Docker (recommended)

From the repo root:

```bash
./docker-phpunit.sh                        # run all tests
./docker-phpunit.sh --filter testConstruct # run one test by name
./docker-phpunit.sh DatabaseTest.php       # run one file
```

The Docker environment provides a pre-configured `statedecoded_test` database and drops
`includes/config-test.inc.php` in place automatically via `docker/config/config-test.inc.docker.php`.

## Running outside Docker

1. **Install dependencies** (one-time):
   ```bash
   composer install
   ```

2. **Create `includes/config-test.inc.php`** by copying `includes/config-sample.inc.php`
   and adjusting the DSN to point at a dedicated test database. Add:
   ```php
   define('STATEDECODED_ENV', 'test');
   ```

3. **Run the suite** from the repo root:
   ```bash
   vendor/bin/phpunit -c includes/test/phpunit.xml
   ```

   Or supply an alternate config path via environment variable:
   ```bash
   STATEDECODED_CONFIG=/path/to/config-test.inc.php vendor/bin/phpunit -c includes/test/phpunit.xml
   ```

## Configuration

| File | Purpose |
| --- | --- |
| `phpunit.xml` | PHPUnit 10 configuration; sets bootstrap, test discovery |
| `bootstrap.php` | Loads config and `functions.inc.php`; enforces `STATEDECODED_ENV=test` |
| `helper/class.TestDbHelper.inc.php` | DB setup/teardown helper used by `APITest` |

## Adding tests

- Place new test classes in `includes/test/` (or a subdirectory). PHPUnit discovers
  any file matching `*Test.php`.
- Extend `PHPUnit\Framework\TestCase`.
- Keep fixture data local to the test method or in a `@dataProvider`; avoid shared
  state in `setUp()` beyond mocks and DB connections.
- Run `./docker-phpstan.sh` after adding tests — PHPStan analyses `includes/` and will
  catch type errors before the tests run.

## Known skipped tests

| Test | Reason |
| --- | --- |
| `APITest::testRegisterKey` | Requires a full parser/import run with XML data |
| `DatabaseTest::testTimeout` | Requires `SYSTEM_VARIABLES_ADMIN` on the test DB user |
