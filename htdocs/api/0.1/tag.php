<?php

# Include the PHP declarations that drive this page.
require $_SERVER['DOCUMENT_ROOT'].'/../includes/page-head.inc.php';

# LOCALIZE VARIABLES
$action = urldecode($_REQUEST['action']);
if (isset($_REQUEST['callback']) && !empty($_REQUEST['callback']))
{
	$callback = $_REQUEST['callback'];
}

# If we're saving a new tag or tags. Tags are to be passed as an array named "tags."
if ($action == 'save')
{
	# If we're missing the section ID. (It's actually OK to be missing the tags array, because
	# that's what happens when the sole tag is deleted.
	if (empty($_GET['section_id']))
	{
		header("HTTP/1.0 404 Not Found");
		exit();
	}
	
	# Create a new instance of the Tags class.
	$tags = new Tags();
	
	# Pass the section ID to the Tags class.
	$tags->section_id = $_GET['section_id'];
	
	# Localize the data and pass it along to the Tags class.
	if (!empty($_POST['tags']))
	{
		$tags->tags = $_POST['tags'];
	}
	# If we didn't receive any tags, then we're deleting the last one -- store a blank array.
	else
	{
		$tags->tags = array();
	}
	
	# Save the tags.
	$succeeded = $tags->save();
	
	# Did it work out?
	if ($succeeded === true)
	{
		header("HTTP/1.0 200 OK");
		exit();
	}
	else
	{
		json_error('Tags could not be saved.');
		die();
	}
	
}

# If we're getting the tags for a given section. This returns not just the tags for the section,
# but also a listing of tags that are voluntered for the autocomplete function.
elseif ($action == 'get')
{
	# If we're missing the section ID.
	if (!isset($_GET['section_id']))
	{
		header("HTTP/1.0 404 Not Found");
		exit();
	}
	
	# Create a new instance of the Tags class.
	$tags = new Tags();
	
	# Pass the section ID to the Tags class.
	$tags->section_id = $_GET['section_id'];
	
	# Get a list of the tags.
	$list = $tags->get();
	
	# Now move onto getting a list of candidate tags for this section.
	
	# Specify that we want the simple format of this data, meaning only the tags.
	$tags->format = 'bare';
	
	# Get a listing of candidate tags.
	$candidates = $tags->list_candidates();
	
	if ($candidates !== false)
	{
		# Iterate through the listing of candidate tags and turn it into a simplified array, as is
		# required for the jQuery UI's Autocomplete widget.
		$tmp = array();
		foreach ($candidates as $candidate)
		{
			$tmp[] = $candidate;
		}
		$candidates = $tmp;
		unset($tmp);
	}
	
	# Intialize our array that we'll be storing the JSON in.
	$json = array();
	
	# If we've got a tag list, add that to our array.
	if ($list !== false)
	{
		# The Tag-Handler jQuery UI plugin requires that the response be named assignedTags.
		$json['assignedTags'] = $list;
	}
	else
	{
		$json['assignedTags'] = array();
	}
	
	# If we've got a list of candidates to volunteer via autocomplete, add that to our array.
	if ($candidates !== false)
	{
		# The Tag-Handler jQuery UI plugin requires that the response be named availableTags.
		$json['availableTags'] = $candidates;
	}
	else
	{
		$json['availableTags'] = array();
	}
	
	# Send an HTTP header defining the content as JSON.
	header('Content-type: application/json');
	# Optionally display a callback term.
	if (isset($callback))
	{
		echo $callback.' (';
	}
	echo json_encode($json);
	if (isset($callback))
	{
		echo ');';
	}
	exit();
}

?>