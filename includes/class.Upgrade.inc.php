<?php

/**
 * Upgrade
 *
 * PHP version 5
 *
 * @license		http://www.gnu.org/licenses/gpl.html GPL 3
 * @version		1.0
 * @link		http://www.statedecoded.com/
 * @since		1.0
*/

class Upgrade
{
	
	/*
	 * Update the number of the installed version of The State Decoded
	 *
	 * Only updates the version number in config.inc.php -- the version number in MySQL's settings
	 * table is updated within the database migration that accompanies each upgrade.
	 */
	function update_version_number($version)
	{
		
		$config_file = INCLUDE_PATH . '/config.inc.php';

		if (is_writable($config_file))
		{
			$config = file_get_contents($config_file);
			$config = preg_replace("/\('VERSION', '(.+)'\)/", "('VERSION', '" . $version . "')", $config);
			file_put_contents($config_file, $config);
		}
		else
		{
			return FALSE;
		}
		
	}

}
