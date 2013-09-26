<?php

/**
 * The API's search method
 *
 * PHP version 5
 *
 * @author		Waldo Jaquith <waldo at krues8dr.com>
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
 * Retrieve a list of all valid API keys.
 */
$api = new API;
$api->list_all_keys();

/*
 * Make sure that the provided API key is the correct length.
 */
if ( strlen($_GET['key']) != 16 )
{
	json_error('Invalid API key.');
	die();
}

/*
 * Localize the provided API key, filtering out unsafe characters.
 */
$key = filter_input(INPUT_GET, 'key', FILTER_SANITIZE_STRING);

/*
 * If the provided API key has no content, post-filtering, or if there are no registered API keys.
 */
if ( empty($key) || (count($api->all_keys) == 0) )
{
	json_error('API key not provided. Please register for an API key.');
	die();
}

/*
 * But if there are API keys, and our key is valid-looking, check whether the key is registered.
 */
elseif (!isset($api->all_keys->$key))
{
	json_error('Invalid API key.');
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
 * Intialize Solarium.
 */
Solarium_Autoloader::register();
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
 * If we have results, iterate through them and include them in our output.
 */
$i=0;
foreach ($search_results as $document)
{
			
	/*
	 * Attempt to display a snippet of the indexed law.
	 */
	$snippet = $highlighted->getResult($result->id);
	if ($snippet != FALSE)
	{
		$response->results->{$i}->text = '';
		foreach ($snippet as $field => $highlight)
		{
			$response->results->{$i}->text .= implode(' [...] ', $highlight);
		}
	}

	$response->results->{$i}->section = $response->section;
	$response->results->{$i}->catch_line = $response->catch_line;
	$response->results->{$i}->text = $response->text;
	$i++;
	
}
	
/*
 * Provide the total number of available documents, beyond the number returned by or available
 * via the API.
 */
$response->total_records = $search_results->getNumFound();
	


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
