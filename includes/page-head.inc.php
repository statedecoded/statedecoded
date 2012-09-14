<?php

# Include the PEAR database abstraction layer. <http://pear.php.net/package/MDB2>
require_once 'MDB2.php';

# If APC is not running.
if ( !extension_loaded('apc') || (ini_get('apc.enabled') != 1) )
{
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
	mail('waldo@jaquith.org', 'VA Laws DB Connection Failed', __FILE__."\r\r\r".$db->getMessage());
	die('We’re having some database trouble right now. Check back later. Sorry!');
}

# We must always connect to the database with UTF-8.
$db->setCharset('utf8');

# Include the functions that drive the site.
require_once('functions.inc.php');

# If there exists a custom functions file, include that, too.
if (defined('CUSTOM_FUNCTIONS'))
{
	require_once(CUSTOM_FUNCTIONS);
}

# Include WordPress's texturize function, for typographical niceties.
require_once('texturize.inc.php');
		
# We're going to need access to the database connection throughout the site.
global $db;

?>