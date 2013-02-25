<?php

/**
 * The administrative parser page
 * 
 * PHP version 5
 *
 * @author		Waldo Jaquith <waldo at jaquith.org>
 * @copyright	2010-2013 Waldo Jaquith
 * @license		http://www.gnu.org/licenses/gpl.html GPL 3
 * @version		0.6
 * @link		http://www.statedecoded.com/
 * @since		0.1
 *
 */


/*
 * During this import phase, report all errors.
 */
ini_set('display_errors', 1);
ini_set('error_reporting', 'E_ALL');
error_reporting(1);

/*
 * Give PHP lots of RAM.
 */
ini_set('memory_limit', '128M');

/*
 * Include the PHP declarations that drive this page.
 */
require $_SERVER['DOCUMENT_ROOT'].'/../includes/page-head.inc.php';

/*
 * Fire up our templating engine.
 */
$template = new Page;

/*
 * Define some page elements.
 */
$template->field->browser_title = 'Parser';
$template->field->page_title = 'Parser';

/*
 * When first loading the page, show options.
 */
if (count($_POST) === 0)
{
	$body = '
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

/*
 * If the request is to empty the database.
 */
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
			$body .= '<p>'.$sql.'</p>';
			die($result->getMessage());
		}
		$body .= '<p>Deleted '.$table.'.</p>';
	}
	
	# Reset the auto-increment counter, to avoid unreasonably large numbers.
	$sql = 'ALTER TABLE structure
			AUTO_INCREMENT=1';
	$result =& $db->exec($sql);
	
	/*
	 * Delete law histories.
	 */
	$sql = 'DELETE FROM laws_meta
			WHERE meta_key = "history"';
	$result =& $db->exec($sql);
	
}

elseif ($_POST['action'] == 'parse')
{
		
	/*
	 * Include HTML Purifier, which we use to clean up the code and character sets.
	 */
	require_once(INCLUDE_PATH.'/htmlpurifier/HTMLPurifier.auto.php');
	$purifier = new HTMLPurifier();

	/*
	 * Let this script run for as long as is necessary to finish.
	 */
	set_time_limit(0);
	
	/*
	 * Create a new instance of Parser.
	 */
	$parser = new Parser();
	
	# Tell the parser what the working directory should be for the XML files.
	$parser->directory = $_SERVER['DOCUMENT_ROOT'].'/xml/';
	
	/*
	 * Iterate through the files.
	 */
	while ($section = $parser->iterate())
	{
		$parser->section = $section;
		$parser->parse();
		$parser->store();
		$body .= '. ';
	}

	/*
	 * Crosslink laws_references. This needs to be done after the time of the creation of these
	 * references, because many of the references are at that time to not-yet-inserted sections.
	 */
	$sql = 'UPDATE laws_references
			SET target_law_id =
				(SELECT laws.id
				FROM laws
				WHERE section = laws_references.target_section_number)
			WHERE target_law_id = 0';
	$db->exec($sql);
	
	/*
	 * Any unresolved target section numbers are spurious (strings that happen to match our section
	 * PCRE), and can be deleted.
	 */
	$sql = 'DELETE FROM laws_references
			WHERE target_law_id = 0';
	$db->exec($sql);

	/*
	 * Break up law histories into their components and save those.
	 */
	$sql = 'SELECT id, history
			FROM laws';
	$result =& $db->query($sql);
	if ($result->numRows() > 0)
	{
		
		$parser = new Parser;
		
		/*
	 	 * Step through the history of every law that we have a record of.
	 	 */
		while ($law = $result->fetchRow(MDB2_FETCHMODE_OBJECT))
		{
			
			/*
			 * Turn the string of text that comprises the history into an object of atomic history
			 * history data.
			 */
			$parser->history = $law->history;
			$history = $parser->extract_history();
			
			/*
			 * Save this object to the metadata table pair.
			 */
			$sql = 'INSERT INTO laws_meta
					SET law_id='.$db->escape($law->id).',
					meta_key="history", meta_value="'.$db->escape(serialize($history)).'",
					date_created=now();';
			$db->exec($sql);
		}
	}

	
	/*
	 * If we already have a view, replace it with this new one.
	 */
	$sql = 'DROP VIEW IF EXISTS structure_unified';
	$db->exec($sql);
	
	/*
	 * The depth of the structure is the number of entries in STRUCTURE, minus one.
	 */
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
	
	/*
	 * We want to to order from highest to lowest, so flip around this array.
	 */
	$order = array_reverse($order);
	
	/*
	 * First, the preamble.
	 */
	$sql = 'CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW structure_unified AS SELECT ';

	/*
	 * Then the actual SELECT statement.
	 */
	$sql .= implode(',', $select);
	
	/*
	 * Start the FROM statement.
	 */
	$sql .= ' FROM (structure AS ';
	
	/*
	 * Build up the FROM statement using the array of table names.
	 */
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
	
	/*
	 * Conclude the FROM statement.
	 */
	$sql .= ')';
	
	/*
	 * Finally, construct the ORDER BY statement.
	 */
	$sql .= ' ORDER BY '.implode(',', $order);
	
	$db->exec($sql);
	
	
	/**
	 * If the site's internal API key is undefined, register a new key and activate it.
	 */
	if (API_KEY == '')
	{
		// I have no idea why __autoload() isn't loading this automatically, but it's not.
		include '../../includes/class.API.inc.php';
		$api = new API();
		$api->form->email = EMAIL_ADDRESS;
		$api->form->url = 'http://'.$_SERVER['SERVER_NAME'].'/';
		$api->suppress_activation_email = TRUE;
		$api->register_key();
		$api->activate_key();
		
		/*
		 * Add the API key to the config file, if it's writable. Otherwise, display it on the
		 * screen, along with instructions.
		 */
		$config_file = $_SERVER['DOCUMENT_ROOT'].'/../includes/config.inc.php';
		if (is_writable($config_file))
		{
			$config = file_get_contents($config_file);
			$config = str_replace("('API_KEY', '')", "('API_KEY', '".$api->key."')", $config);
			file_put_contents($config_file, $config);
		}
		else
		{
			$body .= '<p>Your <code>includes/config.inc.php</code> file could not be modified
				automatically. Please edit that file and set the value of <code>API_KEY</code> to
				<code>'.$api->key.'</code></p>';
		}
	}
	
	
	# Define the location of the downloads directory.
	$downloads_dir = $_SERVER['DOCUMENT_ROOT'].'/downloads/';

	if (is_writable($downloads_dir) === false)
	{
		$body .= '<p>Error: '.$downloads_dir.' could not be written to, so bulk download files could
			not be exported.</p>';
	}
	
	else
	{
		/*
		 * Get a listing of all laws, to be exported as JSON.
		 */
		$sql = 'SELECT laws.section, laws.catch_line, laws.catch_line, laws.text, laws.history,
				structure_unified.*
				FROM laws
				LEFT JOIN structure_unified
					ON laws.structure_id=structure_unified.s1_id
				WHERE edition_id='.EDITION_ID.'
				ORDER BY order_by ASC';
		$result =& $db->query($sql);
		if ($result->numRows() > 0)
		{
			
			# Create a new ZIP file object.
			$zip = new ZipArchive();
			$filename = $downloads_dir.'code.json.zip';
			
			if (file_exists($filename))
			{
				unlink($filename);
			}
			
			# If we cannot create a new ZIP file, bail.
			if ($zip->open($filename, ZIPARCHIVE::CREATE) !== TRUE)
			{
				$body .= '<p>Cannot open '.$filename.' to create a new ZIP file.</p>';
			}
			else
			{
				# Establish the depth of this code's structure. Though this constant includes the laws
				# themselves, we don't subtract 1 from the tally because the structural labels start at 1.
				$structure_depth = count(explode(',', STRUCTURE));
				
				# Iterate through every law.
				while ($law = $result->fetchRow(MDB2_FETCHMODE_OBJECT))
				{
					
					# We don't need either of these fields.
					unset($law->s1_id);
					unset($law->s2_id);
					
					# Rename the structural fields.
					for ($i=1; $i<$structure_depth; $i++)
					{
						# Assign these variables to new locations.
						$law->structure->{$i-1}->label = $law->{'s'.$i.'_label'};
						$law->structure->{$i-1}->name = $law->{'s'.$i.'_name'};
						$law->structure->{$i-1}->number = $law->{'s'.$i.'_number'};
						
						# Unset the old variables.
						unset($law->{'s'.$i.'_label'});
						unset($law->{'s'.$i.'_name'});
						unset($law->{'s'.$i.'_number'});
					}
			
					# Reverse the order of the structure, from broadest to most narrow.
					$law->structure = array_reverse((array) $law->structure);
					
					# Renumber the structure. To avoid duplicates, we must do this awkwardly.
					$tmp = $law->structure;
					unset($law->structure);
					$i=0;
					foreach ($tmp as $structure)
					{
						$law->structure->$i = $structure;
						$i++;
					}
					
					# Add this law to our ZIP archive, creating a pseudofile to do so. Eliminate colons
					# from section numbers, since Windows can't handle colons in filenames.
					$zip->addFromString(str_replace(':', '_', $law->section).'.json', json_encode($law));
				}
				
				# Close out our ZIP file.
				$zip->close();
			}
		}
	
	
		
		/*
		 * Save dictionary as JSON.
		 */
		$sql = 'SELECT laws.section, dictionary.term, dictionary.definition, dictionary.scope
				FROM dictionary
				LEFT JOIN laws ON dictionary.law_id = laws.id
				ORDER BY dictionary.term ASC , laws.order_by ASC';
		$result =& $db->query($sql);
		if ($result->numRows() > 0)
		{
			# Retrieve the entire dictionary as a single object.
			$dictionary = $result->fetchAll(MDB2_FETCHMODE_OBJECT);
		
			# Define the filename for our dictionary.
			$filename = $downloads_dir.'dictionary.json.zip';
			
			# Create a new ZIP file object.
			$zip = new ZipArchive();
			
			if (file_exists($filename))
			{
				unlink($filename);
			}
		
			# If we cannot create a new ZIP file, bail.
			if ($zip->open($filename, ZIPARCHIVE::CREATE) !== TRUE)
			{
				echo '<p>Cannot open '.$filename.' to create a new ZIP file.</p>';
			}
			else
			{
				
				# Add this law to our ZIP archive.
				$zip->addFromString('dictionary.json', json_encode($dictionary));
				
				# Close out our ZIP file.
				$zip->close();
			}
		}
	}

	# If APC exists on this server, clear everything in the user space. That consists of information
	# that the State Decoded has stored in APC, which is now suspect, as a result of having reloaded
	# the laws.
	if (extension_loaded('apc') && ini_get('apc.enabled') == 1)
	{
		apc_clear_cache('user');
	}
}


/*
 * Put the shorthand $body variable into its proper place.
 */
$template->field->body = $body;
unset($body);

/*
 * Parse the template, which is a shortcut for a few steps that culminate in sending the content
 * to the browser.
 */
$template->parse();
