<?php

header("HTTP/1.0 200 OK");
header('Content-type: application/json');

# Include the PHP declarations that drive this page.
require $_SERVER['DOCUMENT_ROOT'].'/../includes/page-head.inc.php';

if (!isset($_GET['section']) || empty($_GET['section']))
{
	json_error('Code section not provided.');
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

# Localize the section identifier, filtering out unsafe characters.
$section = filter_input(INPUT_GET, 'section', FILTER_SANITIZE_STRING);

# If there's a trailing slash, remove it.
if (substr($section, -1) == '/')
{
	$section = substr($section, 0, -1);
}

# Create a new instance of the class that handles information about individual laws.
$laws = new Law();

# Instruct the Law class on what, specifically, it should retrieve.
$laws->config->get_text = TRUE;
$laws->config->get_structure = TRUE;
$laws->config->get_amendment_attempts = FALSE;
$laws->config->get_court_decisions = TRUE;
$laws->config->get_metadata = TRUE;
$laws->config->get_references = TRUE;
$laws->config->get_related_laws = TRUE;

# Pass the requested section number to Law.
$laws->section_number = $section;

# Get a list of all of the basic information that we have about this section.
$response = $laws->get_law();

# If, for whatever reason, this section is not found, return an error.
if ($response === false)
{
	json_error('Section not found.');
	die();
}
else
{

	# Eliminate the listing of all other sections in the chapter that contains this section. That's
	# returned by our internal API by default, but it's not liable to be useful to folks receiving
	# this data.
	unset($response->chapter_contents);
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