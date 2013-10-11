<?php

/**
 * The API's dictionary method
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

# Use a provided JSONP callback, if it's safe.
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

# Make sure we have a term.
if (!isset($args['term']) || empty($args['term']))
{
	json_error('Dictionary term not provided.');
	die();
}

# Clean up the term.
$term = filter_var($args['term'], FILTER_SANITIZE_STRING);

# If a section has been specified, then clean that up.
if (isset($_GET['section']))
{
	$section = filter_input(INPUT_GET, 'section', FILTER_SANITIZE_STRING);
}

# Get the definitions for the requested term.
$dict = new Dictionary();
if (isset($section))
{
	$dict->section_number = $section;
}
$dict->term = $term;
$dictionary = $dict->define_term();

# If, for whatever reason, this word is not found, return an error.
if ($dictionary === FALSE)
{
	$response = array('definition' => 'Definition not available.');
}

else
{

	# Uppercase the first letter of the first (quoted) word. We perform this twice because some
	# legal codes begin the definition with a quotation mark and some do not. (That is, some write
	# '"Whale" is a large sea-going mammal' and some write 'Whale is a large sea-going mammal.")
	if (preg_match('/[A-Za-z]/', $dictionary->definition[0]) === 1)
	{
		$dictionary->definition[0] = strtoupper($dictionary->definition[0]);
	}
	elseif (preg_match('/[A-Za-z]/', $dictionary->definition[1]) === 1)
	{
		$dictionary->definition[1] = strtoupper($dictionary->definition[1]);
	}

	# If the request contains a specific list of fields to be returned.
	if (isset($_GET['fields']))
	{
		# Turn that list into an array.
		$returned_fields = explode(',', urldecode($_GET['fields']));
		foreach ($returned_fields as &$field)
		{
			$field = trim($field);
		}

		# It's essential to unset $field at the conclusion of the prior loop.
		unset($field);

		foreach ($dictionary as &$term)
		{
			# Step through our response fields and eliminate those that aren't in the requested
			# list.
			foreach($term as $field => &$value)
			{
				if (in_array($field, $returned_fields) === FALSE)
				{
					unset($term->$field);
				}
			}
		}
	}

	# If a section has been specified, then simplify this response by returning just a single
	# definition.
	if (isset($section))
	{
		$dictionary = $dictionary->{0};
	}

	# Rename this variable to use the expected name.
	$response = $dictionary;
}

# Include the API version in this response.
if(isset($args['api_version']) && strlen($args['api_version'])) {
	$response->api_version = filter_var($args['api_version'], FILTER_SANITIZE_STRING);
}
else {
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
