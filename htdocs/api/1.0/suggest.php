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

header("HTTP/1.0 200 OK");
header('Content-type: application/json');

/*
 * Validate the provided API key.
 */
$api = new API;
$api->key = $_GET['key'];
try
{
	$api->validate_key();
}
catch (Exception $e)
{
	json_error($e->getMessage());
	die();
}

/*
 * Use a provided JSONP callback, if it's safe.
 */
if (isset($_REQUEST['callback']))
{
	$callback = $_REQUEST['callback'];

	# If this callback contains any reserved terms that raise XSS concerns, refuse to proceed.
	if (valid_jsonp_callback($callback) === FALSE)
	{
		json_error('The provided JSONP callback uses a reserved word.');
		die();
	}
}

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
		
		foreach ($term_result as $suggestion)
		{
			$response->terms[] = $suggestion . ' ';
		}
		
	}
}

/*
 * Include the API version in this response.
 */
if(isset($args['api_version']) && strlen($args['api_version']))
{
	$response->api_version = filter_var($args['api_version'], FILTER_SANITIZE_STRING);
}
else
{
	$response->api_version = CURRENT_API_VERSION;
}

if (isset($callback))
{
	echo $callback.' (';
}
echo json_encode($response);
if (isset($callback))
{
	echo ');';
}
