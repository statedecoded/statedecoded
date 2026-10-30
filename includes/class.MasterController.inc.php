<?php

/**
 * MasterController class
 *
 * Routes all requests to the proper controllers.
 *
 * PHP version 8
 *
 * @license		http://www.gnu.org/licenses/gpl.html GPL 3
 * @version		1.1
 * @link		https://www.statedecoded.com/
 * @since		0.8
 */

#[\AllowDynamicProperties]
class MasterController
{

	protected $router;
	protected $db;
	protected $cache;

	public function __construct($args = [])
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
		$local_data = [
			'db' => $this->db,
			'cache' => $this->cache
		];

		/*
		 * Explode the request into a method and some args.
		 */
		list($handler, $args, $route_name) = $this->parseRequest();

		/*
		 * Tell browsers and any shared cache how long they may keep this response. Handlers may
		 * override this (e.g., a 404 or an API error).
		 */
		$policy = $this->cachePolicy($route_name);
		HttpCache::send($policy);

		/*
		 * If the requester already has the current version of this page, say so and stop, rather
		 * than building the page again.
		 */
		if ($policy === 'page' && $this->db)
		{
			$etag = HttpCache::etag($this->db, $this->cache);
			if ($etag !== false)
			{
				header('ETag: ' . $etag);
				if (HttpCache::matches($etag, $_SERVER['HTTP_IF_NONE_MATCH'] ?? ''))
				{
					http_response_code(304);
					return;
				}
			}
		}

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
				/*
				 * Make local variables available to included file.
				 * NOTE: Using extract() is a security risk, so we assign variables explicitly instead.
				 */
				foreach ($local_data as $__key => $__value)
				{
					$$__key = $__value;
				}
				unset($__key, $__value);
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

		if(strpos($url, '?') !== false) {
			list($url, $query_string) = explode('?', $url);
		}

		list($handler, $args, $route_name) = $router->getRoute($url);

		return [$handler, $args, $route_name];

	}

	/**
	 * Choose the cache policy for a request (see HttpCache::headers_for()).
	 *
	 * Everything is public by default, because nearly everything changes only when an edition is
	 * imported. The exceptions are anything that isn't a plain read, and the routes listed here.
	 */
	public function cachePolicy($route_name)
	{

		$private_routes = [
			'admin',
			'api-activate',
			'api-create',
			'record-view',
		];

		$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
		if ($method !== 'GET' && $method !== 'HEAD')
		{
			return 'private';
		}

		if (in_array($route_name, $private_routes, true))
		{
			return 'private';
		}

		return 'page';

	}

}
