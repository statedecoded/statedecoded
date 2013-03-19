<?php

/**
 * Generic Logger classes
 *
 * Classes to output messages in a given format.
 *
 * PHP version 5
 *
 * @author		Bill Hunt <bill at krues8dr.com>
 * @copyright	2013 Bill Hunt
 * @license		http://www.gnu.org/licenses/gpl.html GPL 3
 * @version		0.7
 * @link		http://www.statedecoded.com/
 * @since		0.7
*/

/**
 * Base Logger - just outputs a message.  Can be HTML or plain text.
 */

class Logger
{
   /**
     * Whether or not to show messages as HTML.
     *
     * @var boolean
     */
	public $html = false;

   /**
     * This setting determines how "loud" the logger will be.
     *
     * Setting this to a higher number will reduce the number of messages
     * output by the logger.
     *
     * @var integer
     */
	public $level = 0;

	// {{{ __construct

	/**
	 * Class constructor.  Sets defaults.

	 * @param array    Hash of all values to override in the class
	 */

	public function __construct($args = array())
	{
		foreach($args as $key=>$value)
		{
			$this->$key = $value;
		}

	}

	// }}}

	// {{{ message

	/**
	 * @param string  $msg the message to print out
	 * @param integer $level The log level of the message.
	 *                      This log level must be greater than the log level
	 *                      set on the class to actually be printed.
	 */
	public function message($msg, $level = 1)
	{
		if($level >= $this->level)
		{
			print $msg;

			/**
			 * Handle line endings
			 */
			if($this->html == true)
			{
				print '<br />';
			}
			else
			{
				print "\n";
			}
			/**
			 * Flush the buffer, just to get the content out ASAP
			 */
			flush();
		}
	}

	// }}}

}


/**
 * Silent Logger - does nothing.
 */

class QuietLogger
{
	public function message($msg) {}
}


/**
 * Debug Logger - includes time and memory usage.
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
		print $this->get_time_elapsed() . "ms ";
		print memory_get_usage() . "b : ";

		parent::message($msg, $level);
	}

	// }}}

	// {{{ get_time

	/**
	 * Gets the current (micro) time.  Nothing fancy.
	 */
	public function get_time()
	{
		return microtime(true);
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
