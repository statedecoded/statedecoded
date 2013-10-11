<?php

/**
 * Base Logger class
 *
 * Class to output messages in a given format. Can be HTML or plain text.
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

class Logger
{

   /**
     * Whether or not to show messages as HTML.
     *
     * @var boolean
     */
	public $html = FALSE;

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
	
		if ($level >= $this->level)
		{
		
			echo $msg;

			/*
			 * Provide the correct line endings.
			 */
			if($this->html === TRUE)
			{
				echo '<br />';
			}
			else
			{
				echo "\n";
			}
			
			/*
			 * Flush the buffer to send the content to the browse immediately.
			 */
			flush();
			ob_flush();
			
		}
		
	}

	// }}}

}
