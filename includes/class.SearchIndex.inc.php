<?php

/**
 * SearchIndex class
 *
 * Implements a search engine (e.g. Solr, Elasticsearch)
 * Basically a thin wrapper for the search engine.
 *
 * Update Usage:
 *
 * $searchIndex = new SearchIndex(array('engine' => 'Solr'));
 * $searchIndex->startUpdate();
 * $searchIndex->addData($law);
 * $searchIndex->addData($law2);
 * $searchIndex->commit();
 */

class SearchIndex
{
	/*
	 * The engine we're using to do the search.
	 */
	public $engine;

	public function __construct($args = array())
	{
		if(!isset($args['engine']) && defined('SEARCH_ENGINE'))
		{
			$args['engine'] = SEARCH_ENGINE;
		}

		if(!isset($args['engine']))
		{
			throw new Exception('No search engine defined in SearchIndex.');
		}
		else {
			$engine = $args['engine'];
			unset($args['engine']);
			$this->engine = new $engine($args);
		}
	}

	/*
	 * Begin an update transaction.
	 */
	public function start_update()
	{
		$this->engine->start_update();
	}

	/*
	 * Add data to the transaction.
	 */
	public function add_document($data)
	{
		$this->engine->add_document($data);
	}

	/*
	 * Commit the transaction.
	 */
	public function commit()
	{
		$this->engine->commit();
	}

}
