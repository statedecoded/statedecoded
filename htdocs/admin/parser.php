<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
                      "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
</head>
<body>

<?php

# During this debug phase, I want to know about errors.
ini_set('display_errors', 1);
ini_set('error_reporting', 'E_ALL');

# Give PHP 128MB.
ini_set('memory_limit', '72M');

# Include a master settings include file.
require_once $_SERVER['DOCUMENT_ROOT'].'/../includes/config.inc.php';

# Include MDB2
require_once 'MDB2.php';

# Include the code with the functions that drive this parser.
require_once 'parser.inc.php';

# Connect to the database.
$db =& MDB2::connect(MYSQL_DSN);
if (PEAR::isError($db))
{
	die('Could not connect to the database.');
}
	
# We must, must, must always connect with UTF-8.
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
	$tables = array('definitions', 'laws', 'laws_references', 'text', 'laws_views', 'text_sections');
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
	
	# We leave titles alone because they have to be added manually.
	$sql = 'DELETE FROM structure
			WHERE label != "title"';
	# Execute the query.
	$result =& $db->exec($sql);
	
	# Reset the auto-increment counter, to avoid unreasonably large numbers.
	$sql = 'ALTER TABLE structure
			AUTO_INCREMENT=1';
	# Execute the query.
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
	
	# Tell the parser what the working directory should be for the SGML file fragments.
	$parser->directory = $_SERVER['DOCUMENT_ROOT'].'/sgml/';
	
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
	// This belongs in a utility class.
	$sql = 'UPDATE laws_references
			SET target_section_id =
				(SELECT laws.id
				FROM laws
				WHERE section = laws_references.target_section_number)
			WHERE target_section_id = 0';
	$db->exec($sql);
	
	# Any unresolved target section numbers are spurious (strings that happen to match our section
	# PCRE), and can be deleted.
	$sql = 'DELETE FROM laws_references
			WHERE target_section_id = 0';
	$db->exec($sql);
	
	# Update tags to reflect the new law_id.
	// This belongs in a utility class.
	$sql = 'UPDATE tags
			SET law_id =
				(SELECT laws.id
				FROM laws
				WHERE section = tags.section_number)';
	$db->exec($sql);
	
	// establish (or replace) the structure_unified view, basing its width on the STRUCTURE
	// constant.
}

?>
</body>
</html>