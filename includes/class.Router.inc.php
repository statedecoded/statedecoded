<?php

/**
 * Router class
 *
 * Manages all routes
 *
 * PHP version 5
 *
 * @license		http://www.gnu.org/licenses/gpl.html GPL 3
 * @version		1.0
 * @link		http://www.statedecoded.com/
 * @since		0.8
 */

class Router
{

	/*
	 * $routes keeps the order.
	 */
	public $routes = array();

	/*
	 * $handlers is our lookup table.
	 */
	public $handlers = array();

	/*
	 * Adds a new route.  Takes a name, a route, a handler, and optionally
	 * the name of a route to insert the new route before.
	 */
	public function addRoute($name, $route, $handler, $before = false)
	{
		/*
		 * Overwrite an existing route.
		 */
		if(array_search($name, $this->routes) !== false)
		{
			trigger_error(
				'The route "' . $name . '" already exists and will be replaced.',
				E_USER_WARNING
			);
			$this->handlers[$name] = array($route, $handler);
		}
		/*
		 * Otherwise, splice in the route.
		 */
		else
		{
			/*
			 * The default is always last, so use index -1 and length 0 to insert
			 * the route at the end.
			 */
			$index = -1;

			/*
			 * Handle optional route to insert before.
			 */
			if($before)
			{
				$index = array_search($before, $this->routes);
				if($index === false)
				{
					// Default back to -1.
					$index = -1;
				}
			}

			/*
			 * Splice in the new route into our list.
			 */
			array_splice($this->routes, $index, 0, $name);

			$this->handlers[$name] = array($route, $handler);
		}
	}

	public function removeRoute($name)
	{
		$index = array_search($name, $this->routes);
		if($index !== false)
		{
			unset($this->routes[$index]);
			// Re-pack values.
			$this->routes = array_values($this->routes);
			unset($this->handlers[$name]);
		}
	}

	public function getRoute($url)
	{

		foreach($this->routes as $name) {

			/*
			 * Escape slashes.
			 */
			$route_regex = str_replace('/', '\\/', $this->handlers[$name][0]);

			if (preg_match('/' . $route_regex . '/', $url, $matches))
			{
				return array($this->handlers[$name][1], $matches);
			}

		}

		/*
		 * The last route is the default.
		 */
		return array($this->handlers[end($this->routes)], array());

	}
}
