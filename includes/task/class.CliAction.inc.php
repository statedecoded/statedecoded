<?php

abstract class CliAction
{
	static public $name;
	static public $summary;
	public $options;

	abstract public function execute($args = array());

	/*
	 * Technically, a static abstract function doesn't make sense,
	 * since static methods belong to the class that defined them
	 * and cannot be overridden - but you really must have this
	 * function for these classes to work.
	 */
	//abstract public static function getHelp($args = array()) {}
}
