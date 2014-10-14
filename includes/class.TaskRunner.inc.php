<?php

/*
 * State Decoded Task Runner
 */

class TaskRunner
{
	public $options = array();

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

		require_once($file);

		$action = new $obj(
			array('options' => &$this->options)
		);

		// Give a reference to command line options.

		return $this->format( $action->execute($args) );
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
					list($name, $val) = explode('=', $value);
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
	 * Sets the output format.  Matches the command line switch -format=NAME
	 */
	protected function format($text)
	{
		$format = '';
		if(isset($this->options['format']))
		{
			$format = $this->options['format'];
		}

		switch($format)
		{
			case 'cow' :
			case 'cowsay' :
				return $this->formatCow($text);

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
}
