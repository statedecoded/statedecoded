<?php

/**
 * Router class
 *
 * Manages all routes
 *
 * PHP version 5
 *
 * @author		Bill Hunt <bill at krues8dr dot com>
 * @copyright	2013 Bill Hunt
 * @license		http://www.gnu.org/licenses/gpl.html GPL 3
 * @version		0.8
 * @link		http://www.statedecoded.com/
 * @since		0.8
 */

class Router
{

	/*
	 * $routes keeps the order
	 */
	public static $routes = array();

	/*
	 * $handlers is our lookup table
	 */
	public static $handlers = array();

	public static function addRoute($route, $handler)
	{
	
		if (isset(self::$handlers[$route]))
		{
			trigger_error(
				'The route "' . $route . '" already exists and will be replaced.',
				E_USER_WARNING
			);
		}

		/*
		 * The default is always last, so use index -1 and length 0.
		 */
		array_splice(self::$routes, -1, 0, $route);
		self::$handlers[$route] = $handler;
		
	}

	public static function getRoutes()
	{
		return self::$routes;
	}

	public static function getRoute($url)
	{
	
		foreach(self::$routes as $route) {
			
			/*
			 * Escape slashes.
			 */
			$route_regex = str_replace('/', '\\/', $route);

			if (preg_match('/' . $route_regex . '/', $url, $matches))
			{
				return array(self::$handlers[$route], $matches);
			}
			
		}

		/*
		 * The last route is the default.
		 */
		return array(self::$handlers[end(self::$routes)], array());
		
	}
}
