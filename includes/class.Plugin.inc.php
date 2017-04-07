<?php

/**
 * Plugin base class
 *
 * An EventManager-compliant plugin class.
 *
 * PHP version 5
 *
 * @license		http://www.gnu.org/licenses/gpl.html GPL 3
 * @version		0.9
 * @link		http://www.statedecoded.com/
 * @since		0.9
 *
 */

abstract class Plugin
{
	public $listeners = array('test');
	/*
	 * Listeners can be a list of events that automatically map to the
	 * matching functions, *or* an associative array with the event name as
	 * the key and the function name as the value. Note that these will
	 * call the methods as statically.
	 *
	 * Example 1:
	 * public static $listeners = array(
	 *     'export_data', // Maps to ThisClass::export_data()
	 *     'show_law' // Maps to ThisClass::show_law()
	 *
	 * );
	 *
	 * Example 2:
	 * public static $listeners = array(
	 *     'export_data' => 'exportCustomData', // Maps to ThisClass::exportCustomData()
	 *     'show_law' => 'addTopNav' // Maps to ThisClass::addTopNav()
	 * );
	 */

	public function __construct($args = array())
	{
		foreach($args as $key => $value)
		{
			$this->$key = $value;
		}
	}

	/*
	 * Gets a list of this plugin's listeners.
	 */
	public function register()
	{
		$classname = get_called_class();
		$wrapped_listeners = array();

		/*
		 * We translate the listeners into something that's easily
		 * consumable by our EventManager. We map our own classname here,
		 * so it can be easily overridden
		 */

		/*
		 * If we have a list.
		 */
		if(array_keys($this->listeners) === range(0, count($this->listeners) - 1)) {
			foreach($this->listeners as $value)
			{
				$wrapped_listeners[$value] = array( $classname, $value );
			}
		}
		/*
		 * If we have an associative array.
		 */
		else
		{
			foreach($this->listeners as $key => $value) {
				$wrapped_listeners[$key] = array( $classname, $value );
			}
		}

		return $wrapped_listeners;
	}
}
