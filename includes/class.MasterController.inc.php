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

	protected $router;
	protected $db;
	protected $events;
	protected $cache;

	public function __construct($args = array())
	{
		foreach($args as $key => $value)
		{
			$this->$key = $value;
		}

		if(!isset($this->events))
		{
			$this->events = new EventManager;
		}

		if(!isset($this->router))
		{
			$this->router = new Router;
		}
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
			$object = new $class(
				array(
					'db' => $this->db,
					'events' => $this->events,
					'cache' => $this->cache
				)
			);
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

		$this->events->trigger('parseRequest', $url, $this->router);

		list($handler, $args) = $router->getRoute($url);

		$this->events->trigger('postParsedRequest', $url, $this->router,
			$handler, $args);

		return array($handler, $args);

	}

}
