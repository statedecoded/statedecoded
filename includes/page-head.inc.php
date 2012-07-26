<?php

# Include the PEAR database abstraction layer. <http://pear.php.net/package/MDB2>
require_once 'MDB2.php';

# Include the site's config file.
require_once 'config.inc.php';

# Connect to the database.
$db =& MDB2::connect(MYSQL_DSN);
if (PEAR::isError($db))
{
	die('We’re having some database trouble right now. Check back later. Sorry!');
}

# We must always connect to the database with UTF-8.
$db->setCharset('utf8');

# Include the functions that drive the site.
require_once('functions.inc.php');

# If there exists a custom functions file, include that, too.
if (defined(CUSTOM_FUNCTIONS))
{
	require_once(CUSTOM_FUNCTIONS);
}

# Include WordPress's texturize function, for typographical niceties.
require_once('texturize.inc.php');
		
# We're going to need access to the database connection throughout the site.
global $db;

?>