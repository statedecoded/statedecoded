<?php

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
