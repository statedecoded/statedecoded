<?php

/**
 * Abstract class for interfacing with a search engine for The State Decoded.
 * Implement this and SearchEngineResultsInterface to add your own search engine
 * interfaces.  See the Solr and Sql engines for examples.
 *
 * PHP version 5
 *
 * @license		http://www.gnu.org/licenses/gpl.html GPL 3
 * @version		0.9
 * @link		http://www.statedecoded.com/
 * @since		0.9
 */

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

	public function find_related($object, $count) {}
}
