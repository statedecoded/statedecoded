<?php

/**
 * Abstract class for interfacing with a search engine results for The State
 * Decoded. Implement this and SearchEngineInterface to add your own search
 * engine interfaces.  See the Solr and Sql engines for examples.
 *
 * PHP version 5
 *
 * @license		http://www.gnu.org/licenses/gpl.html GPL 3
 * @version		0.9
 * @link		http://www.statedecoded.com/
 * @since		0.9
 */

interface SearchResultsInterface
{
	public function __construct($query, $results);
	public function get_results();
	public function get_fixed_spelling();
	public function get_count();
}
