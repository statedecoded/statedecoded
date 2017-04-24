<?php

/**
 * MasterController class
 *
 * Routes all requests to the proper controllers.
 *
 * PHP version 5
 *
 * @license		http://www.gnu.org/licenses/gpl.html GPL 3
 * @version		1.0
 * @link		http://www.statedecoded.com/
 * @since		0.8
 */

class MasterController
{

	protected $router;
	protected $db;
	protected $cache;

	public function __construct($args = array())
	{
		foreach($args as $key => $value)
		{
			$this->$key = $value;
		}

		if(!isset($this->router))
		{
			$this->router = new Router;
		}
	}

	public function execute()
	{
		/*
		 * Setup the data we'll pass to any instances.
		 */
		$local_data = array(
			'db' => $this->db,
			'cache' => $this->cache
		);

		/*
		 * Explode the request into a method and some args.
		 */
		list($handler, $args) = $this->parseRequest();

		/*
		 * If we have a real handler, run it.
		 */
		if (is_array($handler) && isset($handler[0]) && isset($handler[1]))
		{
			$class = $handler[0];
			$method = $handler[1];
			$object = new $class($local_data);
			print $object->$method($args);
		}

		/*
		 * If we have a single file to run, run that.
		 */
		elseif (is_string($handler) && strlen($handler) > 0)
		{
			if(file_exists(WEB_ROOT.'/'.$handler))
			{
				extract($local_data);
				require(WEB_ROOT.'/'.$handler);
			}
		}

	}

	public function parseRequest()
	{
		$router = $this->router;
		require 'routes.inc.php';

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

		list($handler, $args) = $router->getRoute($url);

		return array($handler, $args);

	}

}
