<?php

/**
 * The Template class, only used as a factory to generate theme Page classes
 *
 * PHP version 5
 *
 * @author		Bill Hunt <bill at krues8dr.com>
 * @copyright	2013 Bill Hunt
 * @license		http://www.gnu.org/licenses/gpl.html GPL 3
 * @version		0.8
 * @link		http://www.statedecoded.com/
 * @since		0.8
 *
 */

class Template
{

	public static function create($pagename=null)
	{
	
		/*
		 * e.g., 'MyCoolTheme__Page'
		 */
		$theme_class = THEME_NAME . '__Page'; 
		
		/*
		 * e.g., '/themes/class.Page.inc.php'
		 */
		$theme_file = THEME_DIR . 'class.Page.inc.php';

		if (check_file_available($theme_file))
		{
		
			require_once($theme_file);

			return new $theme_class($pagename);
		
		}
		
	}
	
}
