<?php

/**
 * SearchIndex class.
 *
 * Implements a search engine (e.g. Solr, Elasticsearch)
 * Basically a thin wrapper for the search engine.
 *
 * PHP version 8
 *
 * @license		http://www.gnu.org/licenses/gpl.html GPL 3
 * @version		1.0
 * @link		https://www.statedecoded.com/
 * @since		0.9
 *
 * Usage (update):
 *
 * $searchIndex = new SearchIndex(array('engine' => 'Solr'));
 * $searchIndex->startUpdate();
 * $searchIndex->addData($law);
 * $searchIndex->addData($law2);
 * $searchIndex->commit();
 */

#[\AllowDynamicProperties]
class SearchIndex
{
	/*
	 * The engine we're using to do the search.
	 */
	public $engine;

	public function __construct($args = [])
	{
		foreach($args as $key=>$value)
		{
			$this->$key = $value;
		}

		if(!isset($this->config) && defined('SEARCH_CONFIG'))
		{
			$this->config = json_decode(SEARCH_CONFIG, true);
			$args['config'] = $this->config;
		}

		if(!isset($this->config['engine']))
		{
			throw new Exception('No search engine defined in SearchIndex.');
		}
		else {
			$engine = $this->config['engine'];
			$this->engine = new $engine($args);
		}
	}

	/*
	 * Begin an update transaction.
	 */
	public function start_update()
	{
		return $this->engine->start_update();
	}

	/*
	 * Add data to the transaction.
	 */
	public function add_document($data)
	{
		return $this->engine->add_document($data);
	}

	/*
	 * Commit the transaction.
	 */
	public function commit()
	{
		return $this->engine->commit();
	}

	/*
	 * Search for documents.
	 */
	public function search($query)
	{
		return $this->engine->search($query);
	}

	/*
	 * Find documents similar to the passed object.
	 */
	public function find_related($object, $count)
	{
		return $this->engine->find_related($object, $count);
	}

	/*
	 * Clear out the index.
	 */
	public function delete($edition_id = null) {
		return $this->engine->delete($edition_id);
	}

}
