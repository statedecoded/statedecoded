<?php

header("HTTP/1.0 200 OK");
header('Content-type: application/json');

# Include the PHP declarations that drive this page.
require $_SERVER['DOCUMENT_ROOT'].'/../includes/page-head.inc.php';

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

echo json_encode($response);

?>