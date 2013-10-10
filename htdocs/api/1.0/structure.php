<?php

/**
 * The API's structure method
 *
 * PHP version 5
 *
 * @author		Waldo Jaquith <waldo at jaquith.org>
 * @copyright	2013 Waldo Jaquith
 * @license		http://www.gnu.org/licenses/gpl.html GPL 3
 * @version		0.7
 * @link		http://www.statedecoded.com/
 * @since		0.6
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

	/*
	 * If this callback contains any reserved terms that raise XSS concerns, refuse to proceed.
	 */
	if (valid_jsonp_callback($callback) === false)
	{
		json_error('The provided JSONP callback uses a reserved word.');
		die();
	}
}

/*
 * If no identifier has been specified, explicitly make it a null variable. This is when the request
 * is for the top level -- that is, a listing of the fundamental units of the code (e.g., titles).
 */
if ( !isset($args['identifier']) || empty($args['identifier']) )
{
	$identifier = '';
}

/*
 * If an identifier has been specified (which may come in the form of multiple identifiers,
 * separated by slashes), localize that variable.
 */
else
{
	/*
	 * Localize the identifier, filtering out unsafe characters.
	 */
	$identifier = filter_var($args['identifier'], FILTER_SANITIZE_STRING);
}

/*
 * If the request is for the structural units sorted by a specific criteria.
 */
if (isset($args['sort']))
{

	/*
	 * Explicitly reassign the external value to an internal one, for safety's sake.
	 */
	if ($args['sort'] == 'views')
	{
		$order_by = 'views';
	}
	
}

/*
 * Create a new instance of the class that handles information about individual laws.
 */
$struct = new Structure();


/*
 * Get the structure based on our identifier.
 */
$struct->token_to_structure($identifier);
$response = $struct->structure;

/*
 * If this structural element does not exist.
 */
if ($response === FALSE)
{
	json_error('Section not found.');
	die();
}

/*
 * List all child structural units.
 */
$struct->order_by = $order_by;
$response->children = $struct->list_children();

/*
 * List all laws.
 */
$response->laws = $struct->list_laws();

/*
 * If the request contains a specific list of fields to be returned.
 */
if (isset($_GET['fields']))
{

	/*
	 * Turn that list into an array.
	 */
	$returned_fields = explode(',', urldecode($_GET['fields']));
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

/*
 * Include the API version in this response.
 */
if (isset($args['api_version']) && strlen($args['api_version']))
{
	$response->api_version = filter_var($args['api_version'], FILTER_SANITIZE_STRING);
}
else {
	$response->api_version = CURRENT_API_VERSION;
}

/*
 * Optionally wrap our response in a callback, and flush all data to the client.
 */
if (isset($callback))
{
	echo $callback.' (';
}
echo json_encode($response);
if (isset($callback))
{
	echo ');';
}
