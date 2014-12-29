<?php

/**
 * Base Logger class
 *
 * Class to output messages in a given format. Can be HTML or plain text.
 *
 * PHP version 5
 *
 * @license		http://www.gnu.org/licenses/gpl.html GPL 3
 * @version		0.8
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
		
			/*
			 * If this is a very serious message -- a show-stopping error -- highlight it in red,
			 * when outputting HTML. But don't do this if we're only listing the most serious
			 * errors, because it wouldn't provide any useful UX.
			 */
			if ( ($this->html === TRUE) && ($level == 10) && ($this->level < 10) )
			{
				echo '<span color="red">' . $msg . '</span>';
			}
			else
			{
				echo $msg;
			}

			/*
			 * Provide the correct line endings.
			 */
			if ($this->html === TRUE)
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
