<?php

/*
 * Note we're using config-test, not config.
 */
require_once '../config-test.inc.php';
require_once '../functions.inc.php';

/*
 * This is a precaution against running tests that may be destructive.
 *
 * Hopefully proving the user read the warning in README.md and didn't just
 * copy config.inc.php to config-test.inc.php unchanged. A different database
 * configuration should be used for testing vs production.
 */
if(!defined('STATEDECODED_ENV') ||
			 STATEDECODED_ENV !== 'test'){
	echo "
STATEDECODED_ENV must equal 'test'.

See includes/test/README.md
";
	exit(1);
}
