<?php

/**
 * MasterController class
 *
 * Routes all requests to the proper controllers.
 *
 * PHP version 5
 *
 * @license		http://www.gnu.org/licenses/gpl.html GPL 3
 * @version		0.9
 * @link		http://www.statedecoded.com/
 * @since		0.8
 */

class MasterController
{

	public $routes = array();

	public function __construct()
	{

	}

	public function execute()
	{
	
		/*
		 * Explode the request into a method and some args
		 */
		list($handler, $args) = $this->parseRequest();

		if (is_array($handler) && isset($handler[0]) && isset($handler[1]))
		{
			$class = $handler[0];
			$method = $handler[1];
			$object = new $class();
			print $object->$method($args);
		}
		
		elseif (is_string($handler) && strlen($handler) > 0)
		{
			if(file_exists(WEB_ROOT.'/'.$handler))
			{
				require(WEB_ROOT.'/'.$handler);
			}
		}
		
	}

	public function parseRequest()
	{
	
		if (isset($_SERVER['REDIRECT_URL']) && !empty($_SERVER['REDIRECT_URL']))
		{
			$url = $_SERVER['REDIRECT_URL'];
		}
		else
		{
			$url = $_SERVER['REQUEST_URI'];
		}

		if(strpos($url, '?') !== FALSE) {
			list($url, $query_string) = explode('?', $url);
		}

		list($handler, $args) = Router::getRoute($url);
		return array($handler, $args);
		
	}

	public function fetchRoutes()
	{

	}

}
