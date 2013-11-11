<?php

/**
 * Debug Logger classes
 *
 * Class to output debug messages in a given format.
 *
 * PHP version 5
 *
 * @license		http://www.gnu.org/licenses/gpl.html GPL 3
 * @version		0.8
 * @link		http://www.statedecoded.com/
 * @since		0.7
*/

class DebugLogger extends Logger
{

   /**
     * Time the logger was first created.
     *
     * You shouldn't ever mess with this (probably).
     * It would be nice if we could get the time the script started
     * but this will do for now.
     *
     * @var integer
     */
	public $start_time;

	// {{{ __construct

	/**
	 * Class constructor.  Sets defaults.
	 *
 	 * We need to set the time the logger was created.
 	 *
	 * @param array    Hash of all values to override in the class
	 */

	public function __construct($args)
	{
	
		parent::__construct($args);

		$this->start_time = $this->get_time();
		
	}

	// {{{ message

	/**
	 * Prints the message, adds the time elapsed and memory usage.
	 */
	public function message($msg, $level)
	{
	
		echo $this->get_time_elapsed() . "ms ";
		echo memory_get_usage() . "b : ";

		parent::message($msg, $level);
		
	}

	// }}}

	// {{{ get_time

	/**
	 * Gets the current (micro) time.  Nothing fancy.
	 */
	public function get_time()
	{
		return microtime(TRUE);
	}

	// }}}

	// {{{ get_time_elapsed

	/**
	 * Determines how much time has passed since the Logger was started.
	 *
	 * @param integer $level The log level of the message.
	 *                      This log level must be greater than the log level
	 *                      set on the class to actually be printed.
	 */
	public function get_time_elapsed($time)
	{
	
		if(!$time)
		{
			$time = $this->get_time();
		}
		return $time - $this->start_time;
		
	}

	// }}}
	
}
