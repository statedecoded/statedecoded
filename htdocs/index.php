<?php

/**
 * Index
 *
 * Routes all the main requests. The site's home page can be found at home.php.
 * 
 * PHP version 5
 *
 * @license		http://www.gnu.org/licenses/gpl.html GPL 3
 * @version		0.8
 * @link		http://www.statedecoded.com/
 * @since		0.8
 *
 */

/*
 * Make sure that mod_env is installed, as it must be.
 */
if (!isset($_SERVER['HTTP_MOD_ENV']))
{
	die('The State Decoded cannot run without Apache’s mod_env installed.');
}

/*
 * If we have not defined the include path yet, then try to do so automatically. Once we have
 * automatically defined the include path, we store it in .htaccess, where it becomes available
 * within the scope of $_SERVER.
 */
if ( !isset($_SERVER['INCLUDE_PATH']) )
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
					
					/*
					 * To pick a file more or less at random, we look for class.Law.inc.php.
					 */
					if (in_array('class.Law.inc.php', $child_files) === TRUE)
					{
						$include_path = realpath(dirname(__FILE__) . '/' . $parent_directory . '/' . $file . '/');
						break(2);
					}
					
				}
				
			}
			
		}
		
	}
	
	/*
	 * If we've defined our include path, then modify this file to store it permanently and store it
	 * as a constant.
	 */
	if (isset($include_path))
	{
		
		/*
		 * If possible, modify the .htaccess file, to store permanently the include path.
		 *
		 * If we *can't* modify the .htaccess file, then we have to define the constant on the fly,
		 * with every page view. This is really quite undesirable, because it will slow down the
		 * site non-trivially, but it's better than not working at all.
		 */
		if (is_writable('.htaccess') == TRUE)
		{
			
			$htaccess = PHP_EOL . PHP_EOL . 'SetEnv INCLUDE_PATH ' . $include_path . PHP_EOL;
			$result = file_put_contents('.htaccess' , $htaccess, FILE_APPEND);
			
		}
		
		define('INCLUDE_PATH', $include_path);
		
	}
	
}

/*
 * Save the include path as a constant.
 */
if (isset($_SERVER['INCLUDE_PATH']))
{
	define('INCLUDE_PATH', $_SERVER['INCLUDE_PATH']);
}

/*
 * If the edition ID was provided by the .htaccess file, save it as a constant.
 */
if (isset($_SERVER['EDITION_ID']))
{
	define('EDITION_ID', $_SERVER['EDITION_ID']);
}

/*
 * If APC is not running.
 */
if ( !extension_loaded('apc') || (ini_get('apc.enabled') != 1) )
{

	/*
	 * Include the site's config file.
	 */
	if ( (include INCLUDE_PATH . '/config.inc.php') === FALSE )
	{
		die('Cannot run without a config.inc.php file. See the installation documentation.');
	}

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
	 * If loading from APC worked, just set the include path.
	 */
	if ($result == TRUE)
	{
		set_include_path(get_include_path() . PATH_SEPARATOR . INCLUDE_PATH);
	}

	/*
	 * If we couldn't load the constants from APC.
	 */
	else
	{
		
		/*
		 * Load constants from the config file.
		 */
		if ( (include INCLUDE_PATH . '/config.inc.php') === FALSE )
		{
			die('Cannot run without a config.inc.php file. See the installation documentation.');
		}
		
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
 * Include Solarium's autoloader, for queries to Solr.
 */
require('Solarium/Autoloader.php');
Solarium_Autoloader::register();

/*
 * Turn the Solr URL constant into a configuration array that we can use anywhere we need to use
 * Solarium.
 */
$solr_url = parse_url(SOLR_URL);
$GLOBALS['solr_config'] = array(
    'adapteroptions' => array(
        'host' => $solr_url['host'],
        'port' => $solr_url['port'],
        'path' => $solr_url['path'],
    )
);

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
