<?php

/**
 * Index
 *
 * Routes all the main requests.
 * 
 * PHP version 5
 *
 * @author		Bill Hunt <bill at krues8dr dot com>
 * @copyright	2013 Bill Hunt
 * @license		http://www.gnu.org/licenses/gpl.html GPL 3
 * @version		0.8
 * @link		http://www.statedecoded.com/
 * @since		0.8
 *
 */

/*
 * If we have not defined the include path yet, then try to do so automatically. (Once we have
 * automatically defined the include path, the text of this file is modified to prevent the below
 * stanza from running.)
 */
$include_path_defined = FALSE;
if ($include_path_defined === FALSE)
{
	
	/*
	 * Try a couple of likely locations.
	 */
	if (file_exists('includes'))
	{
		$include_path = dirname(__FILE__) . '/includes/';
	}
	elseif (file_exists('../includes'))
	{
		$include_path = dirname(dirname(__FILE__)) . '/includes/';
	}
	
	/*
	 * Since we have not found it, recurse through the directories to locate it.
	 */
	else
	{
		
		/*
		 * These are the directories that we want to peer into the child directories of -- the
		 * current directory and its parent directory.
		 */
		$parent_directories = array('.', '..');
		foreach ($parent_directories as $parent_directory)
		{
			
			/*
			 * Iterate through all of the parent directory's contents.
			 */
			$files = scandir($parent_directory);
			foreach ($files as $file)
			{
				
				/*
				 * If this file is a directory, peer into its contents.
				 */
				if ( ($file != '.') && ($file != '..') && is_dir($parent_directory . '/' . $file) )
				{
				
					$child_files = scandir($parent_directory . '/' . $file);
					
					if (in_array('class.Law.inc.php', $child_files) === TRUE)
					{
						$include_path = realpath(dirname(__FILE__) . '/' . $parent_directory . '/' . $file . '/');
						break(2);
					}
					
				}
				
			}
			
		}
		
	}
	
}

/*
 * Define the path to the includes library.
 */
define('INCLUDE_PATH', $include_path);

/*
 * If APC is not running.
 */
if ( !extension_loaded('apc') || (ini_get('apc.enabled') != 1) )
{

	/*
	 * Include the site's config file.
	 */
	require INCLUDE_PATH . '/config.inc.php';

	define('APC_RUNNING', FALSE);

}

/*
 * Else if APC is running, get data from the cache.
 */
else
{

	/*
	 * Attempt to load the config file constants out of APC.
	 */
	$result = apc_load_constants('config');

	/*
	 * If this attempt did not work.
	 */
	if ($result === FALSE)
	{

		/*
		 * Load constants from the config file.
		 */
		require INCLUDE_PATH . '/config.inc.php';

		define('APC_RUNNING', TRUE);

		/*
		 * And then save them to APC.
		 */
		$constants = get_defined_constants(TRUE);
		apc_define_constants('config', $constants['user']);

	}
}

/*
 * Connect to the database.
 */
try
{
	$db = new PDO( PDO_DSN, PDO_USERNAME, PDO_PASSWORD, array(PDO::ATTR_ERRMODE => PDO::ERRMODE_SILENT) );
}

/*
 * If we cannot connect.
 */
catch (PDOException $e)
{

	/*
	 * If we get error 1049, that means that no database of this name could be found. This means
	 * that The State Decoded has not yet been installed. Redirect to the admin section.
	 */
	if (strpos($e->getMessage(), '[1049]') !== FALSE)
	{
		if (strpos($_SERVER['REQUEST_URI'], '/admin') === FALSE)
		{
			header('Location: /admin/');
			exit;
		}
	}

	/*
	 * Else it's a generic database problem.
	 */
	else
	{

		/*
		 * A specific error page has been created for database connection failures, display that.
		 */
		if (defined('ERROR_PAGE_DB'))
		{
			require($_SERVER['DOCUMENT_ROOT'] . '/' . ERROR_PAGE_DB);
			exit();
		}

		/*
		 * If no special error page exists, display a generic error.
		 */
		else
		{
			die(SITE_TITLE . ' is having some database trouble right now. Please check back in a few minutes.');
		}

	}

}

/*
 * Prior to PHP v5.3.6, the PDO does not pass along to MySQL the DSN charset configuration option,
 * and it must be done manually.
 */
if (version_compare(PHP_VERSION, '5.3.6', '<'))
{
	$db->exec("SET NAMES utf8");
}

/*
 * We're going to need access to the database connection throughout the site.
 */
global $db;

/*
 * Include the functions that drive the site.
 */
require('functions.inc.php');

/*
 * Include the custom functions file.
 */
require(CUSTOM_FUNCTIONS);

/*
 * Establish routes
 */
require('routes.inc.php');

/*
 * Initialize the master controller
 */
$mc = new MasterController();
$mc->execute();
