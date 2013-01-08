<?php

header("HTTP/1.0 200 OK");
header('Content-type: application/json');

# Include the PHP declarations that drive this page.
require $_SERVER['DOCUMENT_ROOT'].'/../includes/page-head.inc.php';

if (!isset($_GET['term']) || empty($_GET['term']))
{
	json_error('Dictionary term not provided.');
	die();
}

$api = new API;
$api->list_all_keys();

# Make sure that the key is the correct (safe) length.
if ( strlen($_GET['key']) != 16 )
{
	json_error('Invalid API key.');
	die();
}

# Localize the key, filtering out unsafe characters.
$key = filter_input(INPUT_GET, 'key', FILTER_SANITIZE_STRING);

# If no key has been passed with this query, or if there are no registered API keys.
if ( empty($key) || (count($api->all_keys) == 0) )
{
	json_error('API key not provided. Please register for an API key.');
	die();
}
elseif (!isset($api->all_keys->$key))
{
	json_error('Invalid API key.');
	die();
}

# Use a provided JSONP callback, if it's safe.
if (isset($_REQUEST['callback']))
{
	$callback = $_REQUEST['callback'];
	
	# If this callback contains any reserved terms that raise XSS concerns, refuse to proceed.
	if (valid_jsonp_callback($callback) === false)
	{
		json_error('The provided JSONP callback uses a reserved word.');
		die();
	}
}

# Clean up the term.
$term = filter_input(INPUT_GET, 'term', FILTER_SANITIZE_STRING);

# If a section has been specified, then clean that up.
if (isset($_GET['section']))
{
	$section = filter_input(INPUT_GET, 'section', FILTER_SANITIZE_STRING);
}

# Get the definitions for the requested term.
$dict = new Dictionary();
$dict->section_number = $_GET['section'];
$dict->term = $_GET['term'];
$dictionary = $dict->define_term();

# If, for whatever reason, this word is not found, return an error.
if ($dictionary === false)
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
	
	# Create a simple array, with a prettied-up version of the definition text, to be encoded as
	# JSON.
	$response = array(
		'term' => $dictionary->term,
		'definition' => wptexturize($dictionary->definition),
		'section' => $dictionary->section_number,
		'scope' => $dictionary->scope,
		'url' => $dictionary->url,
		'formatted' => wptexturize($dictionary->definition).' (<a href="'.$dictionary->url.'">§&nbsp;'.$dictionary->section_number.'</a>)'
	);
	
	# If this is a definition with a source citation (that is, a generic dictionary definition),
	# then include that, too.
	if (isset($dictionary->source))
	{
		$response['source'] = $dictionary->source;
	}
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
	
	# Step through our response fields and eliminate those that aren't in the requested list.
	foreach($response as $field => &$value)
	{
		if (in_array($field, $returned_fields) === false)
		{
			unset($response->$field);
		}
	}
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

?>