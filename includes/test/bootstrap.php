<?php

// Pin INCLUDE_PATH to the real includes/ directory before loading any config.
// Without this, Docker config templates default INCLUDE_PATH to their own directory,
// which causes the autoloader to miss all class files.
if (!defined('INCLUDE_PATH')) {
    define('INCLUDE_PATH', dirname(__DIR__) . '/');
}

// Allow an alternate config path via env var so the test suite works from any CWD.
// The default resolves to includes/config-test.inc.php regardless of working directory.
$config_file = getenv('STATEDECODED_CONFIG')
    ?: dirname(__DIR__) . '/config-test.inc.php';

require_once $config_file;
require_once dirname(__DIR__) . '/functions.inc.php';

if (!defined('STATEDECODED_ENV') || STATEDECODED_ENV !== 'test') {
    echo "\nSTATEDECODED_ENV must equal 'test'.\n\nSee includes/test/README.md\n";
    exit(1);
}
