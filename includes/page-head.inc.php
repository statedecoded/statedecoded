<?php



/**
 * Loads the State Decoded environment.
 */

# Include the PEAR database abstraction layer. <http://pear.php.net/package/MDB2>
require_once 'MDB2.php';

# If APC is not running.
if ( !extension_loaded('apc') || (ini_get('apc.enabled') != 1) )
{
	# Include the site's config file.
	require_once 'config.inc.php';
}

# Else if APC is running, get data from the cache.
else
{
	# Attempt to load the config file constants out of APC.
	$result = apc_load_constants('config');
	
	# If this attempt did not work.
	if ($result === false)
	{
		# Load constants from the config file.
		require_once 'config.inc.php';
		
		# And then save them to APC.
		$constants = get_defined_constants(true);
		apc_define_constants('config', $constants['user']);
	}
}

# Connect to the database.
$db =& MDB2::connect(MYSQL_DSN);
if (PEAR::isError($db))
{
	# If a specific error page has been created for database connection failures, display that.
	if (defined('ERROR_PAGE_DB'))
	{
		require($_SERVER['DOCUMENT_ROOT'].DIRECTORY_SEPARATOR.ERROR_PAGE_DB);
		exit();
	}
	
	# If no special error page exists, display a generic error.
	else
	{
		die(SITE_TITLE.' is having some database trouble right now. Please check back in a few minutes.');
	}
}

# We must always connect to the database with UTF-8.
$db->setCharset('utf8');

# Include the functions that drive the site.
require('functions.inc.php');

# Include the custom functions file.
require(CUSTOM_FUNCTIONS);

# Include WordPress's texturize function, for typographical niceties.
require('texturize.inc.php');
		
# We're going to need access to the database connection throughout the site.
global $db;
