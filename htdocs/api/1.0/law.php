<?php

/**
 * The API's law method
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
	if (valid_jsonp_callback($callback) === false)
	{
		json_error('The provided JSONP callback uses a reserved word.');
		die();
	}
}

# Make sure we have a esction.
if (!isset($args['section']) || empty($args['section']))
{
	json_error('Code section not provided.');
	die();
}
# Localize the section identifier, filtering out unsafe characters.
$section = filter_var($args['section'], FILTER_SANITIZE_STRING);

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
