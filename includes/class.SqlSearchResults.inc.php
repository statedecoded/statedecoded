<?php

class SqlSearchResults implements SearchResultsInterface
{
	public $query;
	public $results;
	public $count = 0;

	public function __construct($query, $results)
	{
		$this->query = $query;
		$this->results = $results;
	}

	public function get_results()
	{
		return $this->results;
	}

	/*
	 * This engine does not support spelling enhancement.
	 */
	public function get_fixed_spelling()
	{
		return FALSE;
	}

	public function get_count()
	{
		return $this->count;
	}
}
