<?php

/**
 * The API's search method
 *
 * PHP version 5
 *
 * @license		http://www.gnu.org/licenses/gpl.html GPL 3
 * @version		0.8
 * @link		http://www.statedecoded.com/
 * @since		0.8
 *
 */

class APISearchController extends BaseAPIController
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
		 * Determine if the search results should display detailed information about each law.
		 */
		if (!isset($_GET['detailed']) || empty($_GET['detailed']))
		{
			$detailed = FALSE;
		}
		else
		{

			$detailed = filter_var($_GET['detailed'], FILTER_SANITIZE_STRING);
			if ($detailed == "true")
			{
				$detailed = TRUE;
			}
			elseif ($detailed != "false")
			{
				$detailed = FALSE;
			}
			else
			{
				$detailed = FALSE;
			}

		}

		/*
		 * Intialize Solarium.
		 */
		$client = new Solarium_Client($GLOBALS['solr_config']);

		/*
		 * Set up our query.
		 */
		$query = $client->createSelect();
		$query->setQuery($term);

		/*
		 * We want the most useful bits extracted as search results snippets.
		 */
		$hl = $query->getHighlighting();
		$hl->setFields('catch_line, text');

		/*
		 * Specify that we want the first 100 results.
		 */
		$query->setStart(0)->setRows(100);

		/*
		 * Execute the query.
		 */
		$search_results = $client->select($query);

		/*
		 * Display uses of the search terms in a preview of the result.
		 */
		$highlighted = $search_results->getHighlighting();

		/*
		 * If there are no results.
		 */
		if (count($search_results) == 0)
		{

			$response->records = 0;
			$response->total_records = 0;

		}

		/*
		 * If we have results.
		 */

		/*
		 * Instantiate the Law class.
		 */
		$law = new Law;

		/*
		 * Save an array of the legal code's structure, which we'll use to properly identify the structural
		 * data returned by Solr. We hack off the last element of the array, since that identifies the laws
		 * themselves, not a structural unit.
		 */
		$code_structures = array_slice(explode(',', STRUCTURE), 0, -1);

		$i=0;
		foreach ($search_results as $document)
		{

			/*
			 * Attempt to display a snippet of the indexed law.
			 */
			$snippet = $highlighted->getResult($document->id);
			if ($snippet != FALSE)
			{

				/*
				 * Build the snippet up from the snippet object.
				 */
				foreach ($snippet as $field => $highlight)
				{
					$response->results->{$i}->excerpt .= strip_tags( implode(' ... ', $highlight) )
						. ' ... ';
				}

				/*
				 * Use an appropriate closing ellipsis.
				 */
				if (substr($response->results->{$i}->excerpt, -6) == '. ... ')
				{
					$response->results->{$i}->excerpt = substr($response->results->{$i}->excerpt, 0, -6)
						. '....';
				}

				$response->results->{$i}->excerpt = trim($response->results->{$i}->excerpt);

			}

			/*
			 * At the default level of verbosity, just give the data indexed by Solr, plus the URL.
			 */
			if ($detailed === FALSE)
			{

				/*
				 * Store the relevant fields within the response we'll send.
				 */
				$response->results->{$i}->section_number = $document->section;
				$response->results->{$i}->catch_line = $document->catch_line;
				$response->results->{$i}->text = $document->text;
				$response->results->{$i}->url = $law->get_url($document->section);
				$response->results->{$i}->score = $document->score;
				$response->results->{$i}->ancestry = (object) array_combine($code_structures, explode('/', $document->structure));

			}

			/*
			 * At a higher level of verbosity, replace the data indexed by Solr with the data provided
			 * by Law::get_law(), at *its* default level of verbosity.
			 */
			else
			{
				$law->section_number = $document->section;
				$response->results->{$i} = $law->get_law();
				$response->results->{$i}->score = $document->score;
			}

			$i++;

		}

		/*
		 * Provide the total number of available documents, beyond the number returned by or available
		 * via the API.
		 */
		$response->total_records = $search_results->getNumFound();



		/*
		 * If the request contains a specific list of fields to be returned.
		 */
		if (isset($args['fields']))
		{

			/*
			 * Turn that list into an array.
			 */
			$returned_fields = explode(',', urldecode(filter_var($args['fields'], FILTER_SANITIZE_STRING)));
			foreach ($returned_fields as &$field)
			{
				$field = trim($field);
			}

			/*
			 * It's essential to unset $field at the conclusion of the prior loop.
			 */
			unset($field);

			/*
			 * Step through our response fields and eliminate those that aren't in the requested list.
			 */
			foreach($response as $field => &$value)
			{
				if (in_array($field, $returned_fields) === false)
				{
					unset($response->$field);
				}
			}
		}

		$this->render($response, 'OK');

	} /* handle() */
} /* class APIStructureController */
