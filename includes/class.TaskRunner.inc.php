<?php

/**
 * State Decoded Command Line Task Runner.
 *
 * You can create your own tasks by adding them to includes/tasks/
 *
 * PHP version 5
 *
 * @license		http://www.gnu.org/licenses/gpl.html GPL 3
 * @version		0.9
 * @link		http://www.statedecoded.com/
 * @since		0.9
 *
 * Usage:
 *   ./statedecoded {command}
 * Use the 'help' command to see all available commands.
 */

class TaskRunner
{
	/*
	 * Our main options.
	 */
	public $options = array();

	/*
	 * The currently executing action.
	 */
	public $action;

	public static function parseActionInfo($action)
	{
		$obj = str_replace(' ', '', ucwords(str_replace('-', ' ', $action))). 'Action';
		$file = INCLUDE_PATH . 'task/class.' . $obj . '.inc.php';

		if(file_exists($file))
		{
			return array($obj, $file);
		}
		return false;
	}

	public static function filenameToClassname($filename)
	{
		$file = basename($filename);
		if(preg_match('/^class.([A-Za-z0-9]+).inc.php$/', $file, $matches))
		{
			return $matches[1];
		}
		return false;
	}

	public function execute($exec_args)
	{
		/*
		 * The first item is always the filename.
		 */
		array_shift($exec_args);

		$this->parseExecArgs($exec_args);

		// This will do for now, but really this should be wrapped in a class
		// and all actions should register themselves with that class.
		if(isset($exec_args) && count($exec_args) > 0)
		{
			$action = array_shift($exec_args);
			$args = $exec_args;

			if($action_info = TaskRunner::parseActionInfo($action))
			{
				list($obj, $file) = $action_info;
			}

		}

		if(!isset($obj))
		{
			$action = 'help';

			if($action_info = TaskRunner::parseActionInfo($action))
			{
				list($obj, $file) = $action_info;
			}

			$args = array();
		}

		$this->action = $action;

		require_once($file);

		print $this->preFormat();

		$action_object = new $obj(
			array('options' => &$this->options)
		);

		print $this->postFormat( $action_object->execute($args) );
	}

	protected function parseExecArgs(&$exec_args)
	{
		foreach($exec_args as $index => $value)
		{
			/*
			 * Eat any params that begin with a dash.
			 */
			if(substr($value, 0, 1) === '-')
			{

				/*
				 * Split our key=value pairs.
				 */
				$value = preg_replace('/^-+/', '', $value);
				if(strpos($value, '=') !== FALSE)
				{
					list($name, $val) = explode('=', $value, 2);
				}
				else {
					$name = $value;
					$val = TRUE;
				}

				/*
				 * Set the local value.
				 */
				$this->options[$name] = $val;

				/*
				 * Unset the value.
				 */
				unset($exec_args[$index]);
			}
		}
	}

	/*
	 * Runs at the beginning of output. Matches the command line switch --format=NAME
	 */
	protected function preFormat()
	{
		$format = '';
		if(isset($this->options['format']) && is_string($this->options['format']))
		{
			$format = $this->options['format'];
		}

		switch($format)
		{
			case 'html' :
				return $this->preFormatHtml($text);

			default :
				return;
		}
	}

	/*
	 * Runs at the end of output. Matches the command line switch --format=NAME
	 */
	protected function postFormat($text)
	{
		$format = '';
		if(isset($this->options['format']) && is_string($this->options['format']))
		{
			$format = $this->options['format'];
		}

		switch($format)
		{
			case 'cow' :
			case 'cowsay' :
				return $this->formatCow($text);

			case 'html' :
				return $this->postFormatHtml($text);

			case 'text' :
			default :
				return $this->formatText($text);
		}
	}

	/*
	 * Text formatting wraps on 75 cols and adds some whitespace.
	 */
	protected function formatText($text)
	{
		return "\n" . wordwrap($text) . "\n\n";
	}

	protected function formatCow($text)
	{
		if(exec('which cowsay') !== '')
		{
			 exec('cowsay -W 75 "' . $text . '"', $output);

			 return join("\n", $output) . "\n\n";
		}
		else
		{
			return "cowsay is not installed.\n\n" .
				$this->formatText($text);
		}
	}

	protected function preFormatHtml()
	{
		return '<html><head><title>statedecoded - ' .
			$this->action .
			'</title></head><body>';
	}

	protected function postFormatHtml($text)
	{
		return $text .
			'</body></html>';
	}
}
