<?php

/**
 * Base Logger class
 *
 * Class to output messages in a given format. Can be HTML or plain text.
 *
 * PHP version 8
 *
 * @license		http://www.gnu.org/licenses/gpl.html GPL 3
 * @version		1.1
 * @link		https://www.statedecoded.com/
 * @since		0.7
*/

#[\AllowDynamicProperties]
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

	/**
	 * Should we flush the buffer automatically?
	 */
	public $flush_buffer = true;


	/**
	 * Error color (for terminal only)
	 */
	public $error_color = '0;31';

	// {{{ __construct

	/**
	 * Class constructor.  Sets defaults.

	 * @param array    Hash of all values to override in the class
	 */

	public function __construct($args = [])
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
			if ($this->html === true)
			{
				echo '<div class="logmessage" data-time="' . microtime(true) .'">';
			}

			echo $msg;


			if ($this->html === true)
			{
				echo '</div>';
			}
			else
			{
				echo "\n";
			}

			if($this->flush_buffer)
			{
				/*
				 * Flush the buffer to send the content to the browse immediately.
				 */
				flush();
				if(ob_get_length())
				{
					ob_flush();
				}
			}

		}

	}

	// }}}

	// {{{ error

	/**
	 * @param string  $msg the message to print out
	 * @param integer $level The log level of the message.
	 *                      This log level must be greater than the log level
	 *                      set on the class to actually be printed.
	 */
	public function error($msg, $level = 1)
	{

		if($this->html === true)
		{
			$msg = '<span class="error">' . $msg . '</span>';
		}
		else
		{
			$msg = "\033[" . $this->error_color . "m" . $msg . "\033[0m";
		}

		$this->message($msg, $level);

	}

	// }}}

	/**
	 * Debug some content.
	 */
	public function debug($data)
	{
		$this->message('<pre class="debug" style="white-space:pre">' .
			print_r($data, true) . '</pre>', 10);
	}

	/*
	 * Render a progressbar.
	 */
	public function progress($name) {
		if($this->html === true)
		{
			echo '<div class="progress" data-time="' . microtime(true) .'">
			  <div class="progress-bar progress-bar-striped active"
			  	role="progressbar" aria-valuenow="45" aria-valuemin="0" aria-valuemax="100"
			  	style="width: 0%" id="progress_' . $name .'">
			    	<span>0% Complete</span>
			  </div>
			</div>';

			flush();
			if(ob_get_length())
			{
				ob_flush();
			}
		}
	}

	public function updateProgressFiles($name, $current, $total, $label = 'Importing')
	{
		$amount = (int) ($current / $total * 100);
		$text = $label . ' ' . number_format($current) . ' of ' . number_format($total);
		$this->updateProgress($name, $amount, $text);
	}

	public function updateProgress($name, $amount, $text = '')
	{
		if($this->html === true)
		{
			if($text === '') {
				$text = $amount . '%';
			}
			echo '<script data-time="' . microtime(true) .'">
				$("#progress_' . $name .'").css("width", "' . $amount .'%");
				$("#progress_' . $name .' span").text("'. $text .'");
			';
			echo '</script>';
			flush();
			if(ob_get_length())
			{
				ob_flush();
			}
		}
		else
		{
			echo $text . "\n";
		}
	}

	public function finishProgress($name)
	{
		if($this->html === true)
		{
			echo '<script data-time="' . microtime(true).'">
				$("#progress_' . $name .'")
					.removeClass("active")
					.removeClass("progress-bar-striped")
					.addClass("progress-bar-done")
					.text("Done");
			</script>';
		}
	}

}
