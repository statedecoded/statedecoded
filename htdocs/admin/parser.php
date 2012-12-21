<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
                      "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
</head>
<body>

<?php

# Give PHP 128MB in which to run. Running out of memory can be a real problem. Sometimes the XML
# for laws is stored in what amounts to a random order in the filesystem, so it can appear that the
# import has finished when, in fact, it's simply run out of memory, but the resulting content on
# the website just has random-seeming chunks of the laws missing.
ini_set('memory_limit', '128M');

# Include a master settings include file.
require_once $_SERVER['DOCUMENT_ROOT'].'/../includes/config.inc.php';

# Include MDB2
require_once 'MDB2.php';

# Include the code with the functions that drive this parser.
require_once CUSTOM_FUNCTIONS;

# Connect to the database.
$db =& MDB2::connect(MYSQL_DSN);
if (PEAR::isError($db))
{
	die('Could not connect to the database.');
}
	
# We must always connect with UTF-8.
$db->setCharset('utf8');

# When first loading the page, show options.
if (count($_POST) == 0)
{
	echo '
		<p>What do you want to do?</p>
		<form method="post" action="/admin/parser.php">
			<input type="hidden" name="action" value="parse" />
			<input type="submit" value="Parse" />
		</form>
		<form method="post" action="/admin/parser.php">
			<input type="hidden" name="action" value="empty" />
			<input type="submit" value="Empty the DB" />
		</form>';
}

# If the request is to empty the database.
elseif ($_POST['action'] == 'empty')
{
	$tables = array('dictionary', 'laws', 'laws_references', 'text', 'laws_views', 'text_sections');
	foreach ($tables as $table)
	{
		$sql = 'TRUNCATE '.$table;
		# Execute the query.
		$result =& $db->exec($sql);
		if (PEAR::isError($result))
		{
			echo '<p>'.$sql.'</p>';
			die($result->getMessage());
		}
		echo '<p>Deleted '.$table.'.</p>';
	}
	
	# Reset the auto-increment counter, to avoid unreasonably large numbers.
	$sql = 'ALTER TABLE structure
			AUTO_INCREMENT=1';
	# Execute the query.
	$result =& $db->exec($sql);
	
	# Delete law histories.
	$sql = 'DELETE FROM laws_meta
			WHERE meta_key = "history"';
	$result =& $db->exec($sql);
	
	echo '<p>Removed everything but titles from structure.</p>';
	
}

elseif ($_POST['action'] == 'parse')
{
		
	# Include HTML Purifier, which we use to clean up the code and character sets.
	require_once(INCLUDE_PATH.'/htmlpurifier/HTMLPurifier.auto.php');
	
	# Fire up HTML Purifier.
	$purifier = new HTMLPurifier();

	# Let this script run for as long as is necessary to finish running.
	set_time_limit(0);
	
	# Create a new instance of Parser.
	$parser = new Parser();
	
	# Tell the parser what the working directory should be for the XML files.
	$parser->directory = $_SERVER['DOCUMENT_ROOT'].'/xml/';
	
	# Iterate through the files.
	while ($section = $parser->iterate())
	{
		$parser->section = $section;
		$parser->parse();
		$parser->store();
		echo '. ';
	}
	
	# Crosslink laws_references. This needs to be done after the time of the creation of these
	# references, because many of the references are at that time to not-yet-inserted sections.
	$sql = 'UPDATE laws_references
			SET target_law_id =
				(SELECT laws.id
				FROM laws
				WHERE section = laws_references.target_section_number)
			WHERE target_law_id = 0';
	$db->exec($sql);
	
	# Any unresolved target section numbers are spurious (strings that happen to match our section
	# PCRE), and can be deleted.
	$sql = 'DELETE FROM laws_references
			WHERE target_law_id = 0';
	$db->exec($sql);

	# Break up law histories into their components and save those.
	$sql = 'SELECT id, history
			FROM laws';
	$result =& $db->query($sql);
	if ($result->numRows() > 0)
	{
		
		$parser = new Parser;
		
		# Step through the history of every law that we have a record of.
		while ($law = $result->fetchRow(MDB2_FETCHMODE_OBJECT))
		{
			
			# Turn the string of text that comprises the history into an object of atomic
			# history data.
			$parser->history = $law->history;
			$history = $parser->extract_history();
			
			# Save this object to the metadata table pair.
			$sql = 'INSERT INTO laws_meta
					SET law_id='.$db->escape($law->id).',
					meta_key="history", meta_value="'.$db->escape(serialize($history)).'",
					date_created=now();';
			$db->exec($sql);
		}
	}

	
	# If we already have a view, replace it with this new one.
	$sql = 'DROP VIEW IF EXISTS structure_unified';
	$db->exec($sql);
	
	# The depth of the structure is the number of entries in STRUCTURE, minus one.
	$structure_depth = count(explode(',', STRUCTURE))-1;
	
	$select = array();
	$from = array();
	$order = array();
	for ($i=1; $i<=$structure_depth; $i++)
	{
		$select[] = 's'.$i.'.id AS s'.$i.'_id, s'.$i.'.name AS s'.$i.'_name,
				s'.$i.'.number AS s'.$i.'_number, s'.$i.'.label AS s'.$i.'_label';
		$from[] = 's'.$i;
		$order[] = 's'.$i.'.number';
	}
	
	# We want to to order from highest to lowest, so flip around this array.
	$order = array_reverse($order);
	
	# First, the preamble.
	$sql = 'CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW structure_unified AS SELECT ';

	# Then the actual SELECT statement.
	$sql .= implode(',', $select);
	
	# Start the FROM statement.
	$sql .= ' FROM (structure AS ';
	
	# Build up the FROM statement using the array of table names.
	$prev = '';
	foreach ($from as $table)
	{
		if ($table == 's1')
		{
			$sql .= $table;
		}
		else
		{
			$sql .= ' LEFT JOIN structure AS '.$table.' ON ('.$table.'.id = '.$prev.'.parent_id)';
		}
		$prev = $table;
	}
	
	# Conclude the FROM statement.
	$sql .= ')';
	
	# Finally, construct the ORDER BY statement.
	$sql .= ' ORDER BY '.implode(',', $order);
	
	$db->exec($sql);
	

	# If APC exists on this server, clear everything in the user space. That consists of information
	# that the State Decoded has stored in APC, which is now suspect, as a result of having reloaded
	# the laws.
	if (extension_loaded('apc') && ini_get('apc.enabled') == 1)
	{
		apc_clear_cache('user');
	}
}

?>
</body>
</html>