<?php

header("HTTP/1.0 200 OK");
header('Content-type: application/json');

# Include the PHP declarations that drive this page.
require $_SERVER['DOCUMENT_ROOT'].'/../includes/page-head.inc.php';

# Get the definitions for the requested term.
$dictionary = new Dictionary();
$dictionary->section_number = $_GET['section'];
$dictionary->term = $_GET['term'];
$definition = $dictionary->define_term();

# If, for whatever reason, this word is not found, return an error.
if ($definition === false)
{
	$response = array('definition' => 'Definition not available.');
}
else
{
	# Create a simple array, with a prettied-up version of the definition text, to be encoded as
	# JSON.
	$response = array(
		'term' => $definition->term,
		'definition' => wptexturize($definition->definition),
		'section' => $definition->section_number,
		'scope' => $definition->scope,
		'url' => $definition->url,
		'formatted' => wptexturize($definition->definition).' (<a href="'.$definition->url.'">§&nbsp;'.$definition->section_number.'</a>)'
	);
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