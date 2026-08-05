<?php

/**
 * The API's search method
 *
 * PHP version 8
 *
 * @license		http://www.gnu.org/licenses/gpl.html GPL 3
 * @version		1.1
 * @link		https://www.statedecoded.com/
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
		$term = filter_var($args['term'], FILTER_DEFAULT);

		/*
		 * Determine if the search results should display detailed information about each law.
		 */
		if (!isset($_GET['detailed']) || empty($_GET['detailed']))
		{
			$detailed = false;
		}
		else
		{

			$detailed = filter_var($_GET['detailed'], FILTER_DEFAULT);
			if ($detailed == "true")
			{
				$detailed = true;
			}
			elseif ($detailed != "false")
			{
				$detailed = false;
			}
			else
			{
				$detailed = false;
			}

		}

		/*
		 * Initialize the configured search engine.
		 */
		$client = new SearchIndex(
			[
				'config' => json_decode(SEARCH_CONFIG, true)
			]
		);

		/*
		 * Execute the query, returning the first 100 results.
		 */
		try
		{
			$search_results = $client->search(
				[
					'q' => decode_entities($term),
					'page' => 1,
					'per_page' => 100
				]
			);
		}
		catch (Exception $error)
		{
			json_error('Search failed with the error "' . $error->getMessage() . '".');
			die();
		}

		$response = new stdClass();
		$response->results = new stdClass();

		/*
		 * If there are no results.
		 */
		if ($search_results->get_count() == 0)
		{

			$response->records = 0;
			$response->total_records = 0;

		}

		$i = 0;
		foreach ($search_results->get_results() as $result)
		{

			/*
			 * The engine returns both laws and structural units; only laws belong in this
			 * method's results.
			 */
			if ($result->object_type != 'law')
			{
				continue;
			}

			/*
			 * Retrieve the full law, which the search engine's lean result rows do not carry.
			 */
			$law = new Law;
			$law->law_id = $result->id;
			$document = $law->get_law();

			if ($document === false)
			{
				continue;
			}

			/*
			 * Display uses of the search terms in a preview of the result.
			 */
			$excerpt = $this->excerpt($document->full_text ?? '', $term);

			if (!isset($response->results->{$i}))
			{
				$response->results->{$i} = new stdClass();
			}

			if ($excerpt !== false)
			{
				$response->results->{$i}->excerpt = $excerpt;
			}

			/*
			 * At the default level of verbosity, just give the law's basic data, plus the URL.
			 */
			if ($detailed === false)
			{

				/*
				 * Store the relevant fields within the response we'll send.
				 */
				$response->results->{$i}->section_number = $document->section_number;
				$response->results->{$i}->catch_line = $document->catch_line;
				$response->results->{$i}->text = $document->full_text;
				$url = $law->get_url($result->object_id, $result->edition_id);
				$response->results->{$i}->url = $url->url ?? null;
				if (isset($document->ancestry))
				{
					$response->results->{$i}->ancestry = $document->ancestry;
				}

			}

			/*
			 * At a higher level of verbosity, provide the data returned by Law::get_law(), at
			 * *its* default level of verbosity.
			 */
			else
			{
				$excerpt_value = $response->results->{$i}->excerpt ?? null;
				$response->results->{$i} = $document;
				if ($excerpt_value !== null)
				{
					$response->results->{$i}->excerpt = $excerpt_value;
				}
			}

			$i++;

		}

		/*
		 * Provide the total number of available records, beyond the number returned by or
		 * available via the API. Note that this includes matching structural units, not just laws.
		 */
		$response->total_records = $search_results->get_count();

		/*
		 * If the request contains a specific list of fields to be returned.
		 */
		if (isset($args['fields']))
		{

			/*
			 * Turn that list into an array.
			 */
			$returned_fields = explode(',', urldecode(filter_var($args['fields'], FILTER_DEFAULT)));
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

	/*
	 * Build a short excerpt of the law's text, centered on the first appearance of the search
	 * term. Returns false if the text is empty.
	 */
	public function excerpt($text, $term, $radius = 150)
	{

		$text = trim(strip_tags($text));
		if (strlen($text) == 0)
		{
			return false;
		}

		/*
		 * Find the first search keyword that appears in the text, and center the excerpt on it.
		 */
		$position = false;
		foreach (SqlSearchEngine::tokenize($term) as $keyword)
		{
			$position = stripos($text, $keyword);
			if ($position !== false)
			{
				break;
			}
		}

		if ($position === false)
		{
			$position = 0;
		}

		$start = max(0, $position - $radius);
		$excerpt = substr($text, $start, $radius * 2);

		/*
		 * Trim to word boundaries, with ellipses marking elided text.
		 */
		if ($start > 0)
		{
			$excerpt = '... ' . preg_replace('/^\S*\s/', '', $excerpt);
		}
		if ( ($start + $radius * 2) < strlen($text) )
		{
			$excerpt = preg_replace('/\s\S*$/', '', $excerpt) . ' ...';
		}

		return $excerpt;

	}

} /* class APISearchController */
