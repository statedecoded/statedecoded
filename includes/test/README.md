#State Decoded Test Suite

Currently, The State Decoded has a *very* incomplete test suite.  There's some coverage
for the Database wrapper, and not much else.  Feel free to contribute your own tests!

Running the test suite _will delete data from the database_. For this reason, it is
recommended a test database be configured. Copy `config.inc.php` to `config-test.inc.php`
and adjust the configuration for the test database.

You must also include the following in `config-test.inc.php` for the tests to run:

    define('STATEDECODED_ENV', 'test');

The Database wrapper contains a few functional tests, just to be safe.  One of those is
for the timeout-reconnect error handler, so the test will take several seconds to run.
It will also require a valid MySQL connection in `config-test.inc.php`.


##Installation

We're using PHPUnit for testing.  To get up and running quickly, install PHPUnit via PEAR.
You will need to run the following commands via `sudo`:

    pear config-set auto_discover 1
    pear install pear.phpunit.de/PHPUnit

This should work under most circumstances, but you may find that you're missing
dependencies.  If this is the case, you can force install them:

    pear install --force --alldeps pear.phpunit.de/PHPUnit

For full installation instructions,
[read the PHPUnit documentation](http://phpunit.de/manual/3.7/en/installation.html).


##Running the Test Runner

To run the test runner after installing PHPUnit, simply run `phpunit` from the test
directory.  Note: We're using a *very* simple relative path to resolve dependencies here,
so you *must* run the test runner from within the test directory.

    cd includes/test/
    phpunit                     # run all tests
    phpunit DatabaseTest.php    # run specific test
