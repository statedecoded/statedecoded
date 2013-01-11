<?php

header("HTTP/1.0 200 OK");
header('Content-type: application/json');

# Include the PHP declarations that drive this page.
require $_SERVER['DOCUMENT_ROOT'].'/../includes/page-head.inc.php';

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

# If no identifier has been specified, explicitly make it a null variable. This is when the request
# is for the top level -- that is, a listing of the fundamental units of the code (e.g., titles).
if ( !isset($_GET['identifier']) || empty($_GET['identifier']) )
{
	$identifier = '';
}

# If an identifier has been specified (which may come in the form of multiple identifiers, separated
# by slashes), localize that variable.
else
{
	# Localize the identifier, filtering out unsafe characters.
	$identifier = filter_input(INPUT_GET, 'identifier', FILTER_SANITIZE_STRING);
}

# Create a new instance of the class that handles information about individual laws.
$struct = new Structure();

# Pass the requested URL to Structure, and then get structural data from that URL. We're faking the
# URL to emulate the public listing (e.g., <http://example.com/12/6/>), which is what is expected
# by the url_to_structure() method.
$struct->url = 'http://'.$_SERVER['HTTP_HOST'].'/'.$identifier;
$struct->url_to_structure();
$response = $struct->structure;

# If this structural element does not exist.
if ($response === false)
{
	json_error('Section not found.');
	die();
}

# List all child structural units.
$response->children = $struct->list_children();

# List all laws.
$response->laws = $struct->list_laws();

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

# Include the API version in this response, by pulling it out of the path.
$tmp = explode('/', $_SERVER['SCRIPT_NAME']);
$response->api_version = $tmp[2];

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