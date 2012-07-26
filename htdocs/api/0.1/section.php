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

/*if (!isset($_GET['key']) || empty($_GET['key']))
{
	json_error('API key not provided.');
	die();
}*/

# Permissible API keys.
$keys = array(
	'xm6v408xadodig0p', // Virginia Decoded
	'zxo8k592ztiwbgre', // Richmond Sunlight
);

/*if (!in_array($_GET['key'], $keys))
{
	json_error('Invalid API key.');
	die();
}*/

# Create a new instance of the class that handles information about individual laws.
$laws = new Law();

# Instruct the Law class to refrain from querying Richmond Sunlight's API.
$laws->skip_amendment_attempts = true;

# Pass the requested section number to Law.
$laws->section_number = $_GET['section'];

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

echo json_encode($response);

?>