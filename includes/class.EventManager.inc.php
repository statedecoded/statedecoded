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
	public $logger;

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

	 public function trigger($name, &$args)
	{
		if(isset($this->events[$name]))
		{
			foreach($this->events[$name] as $listener_name => $listener)
			{
				$this->callListener($listener, $args);
			}
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
				$class = new $classname();
			}

			call_user_func_array(array($class, $method), $args);
		}
		else
		{
			call_user_func_array($listener, $args);
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
}
