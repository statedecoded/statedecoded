<?php

abstract class SearchEngineInterface
{
	public function __construct($args)
	{
		foreach($args as $key=>$value)
		{
			$this->$key = $value;
		}
	}

	public function start_update() {}

	public function add_document($record) {}

	public function commit() {}

	public function search($query) {}
}
