<?php

/**
 * The API's method for suggesting autocompletion of terms
 *
 * PHP version 8
 *
 * @license		http://www.gnu.org/licenses/gpl.html GPL 3
 * @version		1.0
 * @link		http://www.statedecoded.com/
 * @since		0.8
 *
 */

class APISuggestController extends BaseAPIController
{
	function handle($args)
	{

		/*
		 * Make sure we have a search term.
		 */
		if (!isset($args['term']) || empty($args['term']))
		{
			json_error('Search term not provided.');
			die();
		}

		/*
		 * Clean up the search term.
		 */
		$term = filter_var($args['term'], FILTER_DEFAULT);

		/*
		 * Append an asterix to the search term, so that Solr can suggest autocomplete terms.
		 */
		$term .= '*';

		/*
		 * Initialize Solarium.
		 */
		$client = SolrSearchEngine::make_client(json_decode(SEARCH_CONFIG, true));

		/*
		 * Set up our query.
		 */
		$query = $client->createSuggester();
		$query->setHandler('suggest');
		$query->setQuery($term);
		$query->setCount(5);

		/*
		 * Execute the query.
		 */
		$search_results = $client->suggester($query);

		/*
		 * getAll() returns a flat array of all suggestion strings across all dictionaries.
		 */
		$all_suggestions = $search_results->getAll();

		$response = new stdClass();

		/*
		 * If there are no results.
		 */
		if (count($all_suggestions) == 0)
		{

			$response->terms = false;

		}

		/*
		 * If we have results, build up an array of them.
		 */
		else
		{

			$response->terms = array();
			$i = 0;
			foreach ($all_suggestions as $suggestion)
			{
				$response->terms[] = array(
					'id' => $i,
					'term' => $suggestion
				);
				$i++;
			}
		}

		$this->render($response, 'OK');

	} /* handle() */
} /* class APISuggestController */
