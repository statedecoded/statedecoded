<?php

abstract class CliAction
{
	static public $name;
	static public $summary;
	public $options;
	public $default_options = array();

	public function __construct($args = array())
	{
		/*
		 * Set our defaults
		 */
		foreach($args as $key=>$value)
		{
			$this->$key = &$args[$key];
		}
		foreach($this->default_options as $key => $value)
		{
			if(!isset($this->options[$key]))
			{
				$this->options[$key] = $value;
			}
		}
	}

	abstract public function execute($args = array());

	/*
	 * Technically, a static abstract function doesn't make sense,
	 * since static methods belong to the class that defined them
	 * and cannot be overridden - but you really must have this
	 * function for these classes to work.
	 */
	//abstract public static function getHelp($args = array()) {}

	/*
	 * Generic helpers to convert text if that's needed.
	 */
	public function formatOutput($text)
	{
		if(isset($this->options['format']) && $this->options['format'] === 'html')
		{
			return $this->formatHtml($text);
		}
		else
		{
			return $text;
		}
	}

	public function formatHtml($text)
	{
		return nl2br($text);
	}
}
