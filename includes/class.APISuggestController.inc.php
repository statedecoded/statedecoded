<?php

/**
 * The API's method for suggesting autocompletion of terms
 *
 * PHP version 5
 *
 * @author		Waldo Jaquith <waldo at jaquith.org>
 * @copyright	2013 Waldo Jaquith
 * @license		http://www.gnu.org/licenses/gpl.html GPL 3
 * @version		0.9
 * @link		http://www.statedecoded.com/
 * @since		0.9
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
		$term = filter_var($args['term'], FILTER_SANITIZE_STRING);

		/*
		 * Append an asterix to the search term, so that Solr can suggest autocomplete terms.
		 */
		$term .= '*';

		/*
		 * Intialize Solarium.
		 */
		$client = new Solarium_Client($GLOBALS['solr_config']);

		/*
		 * Set up our query.
		 */
		$query = $client->createSuggester();
		$query->setHandler('suggest');
		$query->setQuery($term);
		$query->setOnlyMorePopular(TRUE);
		$query->setCount(5);
		$query->setCollate(TRUE);

		/*
		 * Execute the query.
		 */
		$search_results = $client->suggester($query);

		/*
		 * If there are no results.
		 */
		if (count($search_results) == 0)
		{

			$response->terms = FALSE;

		}

		/*
		 * If we have results, build up an array of them.
		 */
		else
		{

			$response->terms = array();
			foreach ($search_results as $term => $term_result)
			{
				$i=0;
				foreach ($term_result as $suggestion)
				{
					$response->terms[] = array(
						'id' => $i,
						'term' => $suggestion
					);
					$i++;
				}

			}
		}

		$this->render($response, 'OK');

	} /* handle() */
} /* class APISuggestController */
