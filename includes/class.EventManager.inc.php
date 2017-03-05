<?php

/**
 * Event Manager
 *
 * An event dispatcher, for plugins.
 *
 * PHP version 5
 *
 * @license		http://www.gnu.org/licenses/gpl.html GPL 3
 * @version		0.9
 * @link		http://www.statedecoded.com/
 * @since		0.9
 *
 */

class EventManager
{
	public $events = array();
	public $services = array();

	public function __construct($args = array(), $plugins = false)
	{
		foreach($args as $key => $value)
		{
			$this->$key = $value;
		}

		/*
		 * Register any plugins.
		 */
		if(!count($this->events) && !$plugins && defined('PLUGINS'))
		{
			$plugins = json_decode(PLUGINS);
		}

		if($plugins)
		{
			$this->includePlugins($plugins);
		}

		/*
		 * Always give plugins access to call their own events.
		 */
		$this->services['events'] = &$this;
	}

	public function includePlugins($plugins)
	{
		foreach($plugins as $plugin)
		{
			$pluginObj = new $plugin();
			foreach($pluginObj->register() as $eventName => $method)
			{
				$this->registerListener($eventName, $method);
			}
		}
	}

	public function registerListener($name, $method)
	{
		if(!isset($this->events[$name]))
		{
			$this->events[$name] = array();
		}
		$this->events[$name][] = $method;
	}

	public function trigger()
	{
		$args = func_get_args();
		$name = array_shift($args);

		if(isset($this->events[$name]))
		{
			$results = array();
			foreach($this->events[$name] as $i => $listener)
			{
				$results[] = $this->callListener($listener, $args);
			}
			return $results;
		}
	}

	public function callListener($listener, &$args = array())
	{
		// Simplest possible implementation:
		// * calls a listener function
		// * passes in a variable number of arguments.
		// * those arguments are expanded in the listener function.

		if(is_array($listener))
		{
			if(is_object($listener[0]))
			{
				list($class, $method) = $listener;
			}
			else
			{
				list($classname, $method) = $listener;
				$class = new $classname($this->services);
			}

			/*
			 * Handle PHP's weird dereferencing.
			 * Without this, variables are not passed by reference to plugin functions.
			 */
			$ref = array();
			foreach($args as $key => $value)
			{
				if(is_object($value))
				{
					$ref[$key] = &$args[$key];
				}
				else
				{
					$ref[$key] = $value;
				}
			}

			return call_user_func_array(array($class, $method), $ref);
		}
		else
		{
			return call_user_func_array($listener, $args);
		}
	}

	public function removeListener($event, $listener = false)
	{
		if($listener)
		{
			unset($this->events[$event][$listener]);
		}
		else
		{
			unset($this->events[$event]);
		}
	}

	public function has($name)
	{
		if(!isset($this->events[$name]))
		{
			return FALSE;
		}
		return count($this->events[$name]);
	}

	/*
	 * Dependency injection for our plugins.
	 */
	public function registerService($name, &$object)
	{
		$this->services[$name] = $object;
	}
}
