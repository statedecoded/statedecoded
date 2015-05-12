<?php

/**
 * Wrapper class for search results from sql engines.
 *
 * A barebones search client using the existing database.
 * Feel free to use this as a base to create new search adapters!
 *
 * PHP version 5
 *
 * @license		http://www.gnu.org/licenses/gpl.html GPL 3
 * @version		0.9
 * @link		http://www.statedecoded.com/
 * @since		0.9
 */

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
