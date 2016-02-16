<?php

/**
 * The state-specific function library for The State Decoded.
 *
 * PHP version 5
 *
 * @license		http://www.gnu.org/licenses/gpl.html GPL 3
 * @version		0.9
 * @link		http://www.statedecoded.com/
 * @since		0.3
*/

require_once(INCLUDE_PATH . 'class.Edition.inc.php');
require_once(INCLUDE_PATH . 'class.Permalink.inc.php');

/**
 * This class may be populated with custom functions.
 */

class State
{

	/**
	 * Generate the URL to view a law on the official government website
	 *
	 * @return the URL or false
	 */
	/*official_url()
	{

		if (!isset($this->section_number))
		{
			return FALSE;
		}

		return 'http://example.gov/laws/' . $this->section_number . '/';

	}*/

	/**
	 * Render the often-confusing history text for a law as plain English.
	 *
	 * @return the history text or false
	 */
	/*function translate_history()
	{

	}
	*/

	/**
	 * Generate one or more citations for a law
	 *
	 * Should create an object named "citation" (singular) with one numbered entry for each citation
	 * style, with values of "label" and "text," the label describing the type of citation (e.g.
	 * "Official," "Universal") and the text being the citation itself.
	 *
	 * @return true or false
	 */
	/*function citations()
	{

		if (!isset($this->section_number))
		{
			return FALSE;
		}

		$this->citation->{0}->label = 'Official';
		$this->citation->{0}->text = 'St. Code § '.$this->section_number;

		return TRUE;
	}
	*/

	/**
	 * Retrieve a list of every attempt to amend a law
	 *
	 * Should create an object named "bills" (plural) with one numbered entry for each bill that
	 * proposed to amend the law, with values of "year," "number," "catch_line," "outcome," and
	 * "url."
	 *
	 * @return true or false
	 */
	/*function get_amendment_attempts()
	{

		if (!isset($this->section_number))
		{
			return FALSE;
		}

		# Below is an example of how $this->bills should be formatted. Every field must be present,
		# and they should be sorted chronologically, from most oldest to newest.
		#
		#		Object
		#		(
		#			[0] => stdClass Object
		#				(
		#					[year] => 2009
		#					[number] => SB1316
		#					[catch_line] => Freedom of Information Act; strikes requirement to publish a database index, etc.
		#					[outcome] => passed
		#					[url] => http://www.richmondsunlight.com/bill/2009/sb1316/
		#				)
		#
		#			[1] => stdClass Object
		#				(
		#					[year] => 2010
		#					[number] => HB449
		#					[catch_line] => Freedom of Information Act; injunctive relief for public bodies under certain circumstances.
		#					[outcome] => failed
		#					[url] => http://www.richmondsunlight.com/bill/2010/hb449/
		#				)
		#
		#			[2] => stdClass Object
		#				(
		#					[year] => 2010
		#					[number] => HB518
		#					[catch_line] => Freedom of Information Act; public body shall remain responsible for retrieving public records, etc.
		#					[outcome] => passed
		#					[url] => http://www.richmondsunlight.com/bill/2010/hb518/
		#				)

		return TRUE;

	} // end get_amendment_attempts()
	*/

	/**
	 * Retrieve a list of every court decision that cites a given law.
	 *
	 * A customization is necessary to get this working for your legal code.
	 *
	 * You need to experiment with searches on CourtListener and figure out how to build a query
	 * that will return court decisions that refer to your legal code. In the below example, for
	 * Virginia, we've created $url by prefixing the section number query with "Virginia Code" (URL
	 * encoded).
	 *
	 * An optional customization is to set the value of court_html. This is the name of court as
	 * displayed for each ruling, which can look better if abbreviated. You can modify the example
	 * Virginia text that's provided. Or, if you do nothing, the entire court name will be
	 * displayed.
	 *
	 * @return true or false
	 */
	/*function get_court_decisions()
	{

		//  We need a section number in order to search for court decisions that cite that law.
		if (!isset($this->section_number))
		{
			return FALSE;
		}

		// Assemble the URL for our query to the CourtListener API.
		$url = 'https://www.courtlistener.com/api/rest/v3/search/?q="'
			. urlencode($this->section_number) . '"&court=ca4,vaeb,vawb,vaed,vawd,va,vactapp'
			. '&order_by=score+desc&format=json';

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
		curl_setopt($ch, CURLOPT_TIMEOUT_MS, 1200);
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
		curl_setopt($ch, CURLOPT_USERPWD, COURTLISTENER_USERNAME . ':' . COURTLISTENER_PASSWORD);
		$allowed_protocols = CURLPROTO_HTTP | CURLPROTO_HTTPS;
		curl_setopt($ch, CURLOPT_PROTOCOLS, $allowed_protocols);
		curl_setopt($ch, CURLOPT_REDIR_PROTOCOLS, $allowed_protocols & ~(CURLPROTO_FILE | CURLPROTO_SCP));
		$json = curl_exec($ch);

		// If the query failed.
		if ($json == FALSE)
		{
			return FALSE;
		}

		// Turn this JSON into an object.
		$cl_list = json_decode($json);

		// If the JSON is invalid.
		if ($cl_list == FALSE)
		{
			return FALSE;
		}

		// If no results were found, save an empty variable. In this way we cache a lack of court
		// decisions that cite a given section.
		if ($cl_list->count === 0)
		{

			$this->decisions = '';

		}

		// Otherwise if results were found.
		else
		{

			// Create an object to store the decisions that we're going to return.
			$this->decisions = new stdClass();

			// Iterate through the decisions and assign the first 10 to $this->decisions.
			$i=0;
			foreach ($cl_list->results as $opinion)
			{

				if ($i == 10)
				{
					break;
				}

				// Port the fields that we need from $opinion to $this->decisions.
				if (html_entity_decode(strlen(strip_tags($opinion->case_name))) > 60)
				{
					$this->decisions->{$i}->name = ' . . . ' . array_shift(explode("\n", wordwrap(html_entity_decode(strip_tags($opinion->caseName)), 60))) . ' . . . ';
				}
				else
				{
					$this->decisions->{$i}->name = html_entity_decode(strip_tags($opinion->caseName));
				}
				$this->decisions->{$i}->case_number = $opinion->docketNumber;
				$this->decisions->{$i}->citation = $opinion->citation[0];
				$this->decisions->{$i}->date = date('Y-m-d', strtotime($opinion->dateFiled));
				$this->decisions->{$i}->url = 'https://www.courtlistener.com' . $opinion->absolute_url;
				$this->decisions->{$i}->abstract = ' . . . ' . array_shift(explode("\n", wordwrap(html_entity_decode(strip_tags($opinion->snippet)), 100))) . ' . . . ';

				if ($opinion->court == 'Court of Appeals of Virginia')
				{
					$this->decisions->{$i}->court_html = '<abbr title="Court of Appeals">COA</abbr>';
				}
				elseif ($opinion->court == 'Supreme Court of Virginia')
				{
					$this->decisions->{$i}->court_html = '<abbr title="Supreme Court of Virginia">SCV</abbr>';
				}
				else
				{
					$this->decisions->{$i}->court_html = $opinion->court;
				}

				$i++;

			}

		}

		// Store these decisions in the metadata table.
		$law = new Law();
		$law->section_id = $this->section_id;
		$law->metadata->{0}->key = 'court_decisions';
		$law->metadata->{0}->value = json_encode($this->decisions);
		$law->store_metadata();

		return TRUE;

	}*/

}


/**
 * The parser for importing legal codes. This is fully functional for importing The State Decoded's
 * prescribed XML format <https://github.com/statedecoded/statedecoded/wiki/XML-Format-for-Parser>,
 * and serves as a guide for those who want to parse an alternate format.
 */
class Parser
{

	public $file = 0;
	public $directory;
	public $files = array();

	public $db;
	public $permalink_obj;

	public $edition_id;
	public $previous_edition_id;
	public $structure_labels;

	/**
	 * Indicators of dictionary terms.
	 */

	/*
	 * The candidate phrases that indicate that the scope of one or more definitions are about
	 * to be provided. Some phrases are left-padded with a space if they would never occur
	 * without being preceded by a space; this is to prevent over-broad matches.
	 */
	public $scope_indicators = array(
		' are used in this ',
		'when used in this ',
		'for purposes of this ',
		'for the purposes of this ',
		'for the purpose of this ',
		'in this ',
	);

	/*
	 * Create a list of every phrase that can be used to link a term to its defintion, e.g.,
	 * "'People' has the same meaning as 'persons.'" When appropriate, pad these terms with
	 * spaces, to avoid erroneously matching fragments of other terms.
	 */
	public $linking_phrases = array(
		' mean ',
		' means ',
		' shall include ',
		' includes ',
		' has the same meaning as ',
		' shall be construed ',
		' shall also be construed to mean ',
	);

	public function __construct($options)
	{

		/**
		 * Set our defaults
		 */
		foreach ($options as $key => $value)
		{
			$this->$key = $value;
		}

		/**
		 * Set the directory to parse
		 */
		if ($this->directory)
		{

			if (!isset($this->directory))
			{
				$this->directory = getcwd();
			}

			if (file_exists($this->directory) && is_dir($this->directory))
			{
				$directory = dir($this->directory);
			}
			else
			{
				throw new Exception('Import directory does not exist "' .
					$this->directory . '"');
			}

			while (false !== ($filename = $directory->read()))
			{

				/*
				 * We should make sure we've got an actual file that's readable.
				 * Ignore anything that starts with a dot.
				 */
				$filepath = $this->directory . $filename;
				if (is_file($filepath) &&
					is_readable($filepath) &&
					substr($filename, 0, 1) !== '.')
				{
					$this->files[] = $filepath;
				}

			}

			/*
			 * Check that we found at least one file
			 */
			if (count($this->files) < 1)
			{
				throw new Exception('No Import Files found in path "' .
					$this->directory . '"');
			}

		}

		if (!$this->structure_labels)
		{
			$this->structure_labels = $this->get_structure_labels();
		}

		if(!isset($this->permalink_obj))
		{
			$this->permalink_obj = new Permalink(
				array(
					'db' => $this->db
				)
			);
		}

	}
	/**
	 * Step through every line of every file that contains the contents of the code.
	 */
	public function iterate()
	{

		/*
		 * Iterate through our resulting file listing.
		 */
		$file_count = count($this->files);
		for ($i = $this->file; $i < $file_count; $i++)
		{

			/*
			 * Operate on the present file.
			 */
			$filename = $this->files[$i];

			/*
			 * Determine data type and import
			 */
			// TODO : Make this smarter.  We can use the PECL Fileinfo package
			// instead, but I'd rather not have to require that at this point. -BH

			$extension = substr($filename, strrpos($filename, '.')+1);

			switch($extension)
			{
				case 'xml' :
					$this->import_xml($filename);
					break;

				case 'json' :
					$this->import_json($filename);
					break;

				// TODO: Fix this.
				default:
					// Anything else, we can't handle.
			}

			/*
			 * Increment our placeholder counter.
			 */
			$this->file++;

			/*
			 * Send this object back, out of the iterator.
			 */
			return $this->section;
		}

	} // end iterate() function


	/**
	 * Convert the XML into an object.
	 */
	public function import_xml($filename)
	{
		$xml = file_get_contents($filename);

		try
		{
			$this->section = new SimpleXMLElement($xml);
		}
		catch(Exception $e)
		{
			/*
			 * If we can't convert to XML, try cleaning the data first.
			 */
			if (class_exists('tidy', FALSE))
			{

				$tidy_config = array('input-xml' => TRUE);
				$tidy = new tidy();
				$tidy->parseString($xml, $tidy_config, 'utf8');
				$tidy->cleanRepair();
				$xml = (string) $tidy;

			}
			elseif (exec('which tidy'))
			{
				exec('tidy -xml '.$filename, $output);
				$xml = join('', $output);
			}
			$this->section = new SimpleXMLElement($xml);
		}

		/*
		 * Send this object back, out of the iterator.
		 */
		return $this->section;
	}


	/**
	 * Convert the JSON into an object.
	 */
	public function import_json($filename)
	{
		$section_data = file_get_contents($filename);

		$this->section = json_decode($section_data);

		return $this->section;
	}


	/**
	 * Accept the raw content of a section of code and normalize it.
	 */
	public function parse()
	{

		/*
		 * If a section of code hasn't been passed to this, then it's of no use.
		 */
		if (!isset($this->section))
		{
			return FALSE;
		}

		/*
		 * Create a new, empty object to store our code's data.
		 */
		$this->code = new stdClass();

		/*
		 * Transfer some data to our object.
		 */
		$this->code->catch_line = (string) $this->section->catch_line[0];
		$this->code->section_number = (string) $this->section->section_number;
		$this->code->order_by = (string) $this->section->order_by;
		$this->code->history = (string)  $this->section->history;

		/*
		 * If additional metadata is present in a "metadata" container, copy it over to our code
		 * object.
		 */
		if (isset($this->section->metadata))
		{

			foreach ($this->section->metadata as $field)
			{

				foreach ($field as $key => $value)
				{
					/*
					 * Convert true/false values to y/n values.
					 */
					if ($value == 'true')
					{
						$value = 'y';
					}
					elseif ($value == 'true')
					{
						$value = 'n';
					}
					$this->code->metadata->$key = $value;
				}

			}

		}

		/*
		 * Iterate through the structural headers.
		 */
		foreach ($this->section->structure->unit as $unit)
		{
			$level = (string) $unit['level'];
			if(!isset($this->code->structure))
			{
				$this->code->structure = new stdClass();
			}
			if(!isset($this->code->structure->{$level}))
			{
				$this->code->structure->{$level} = new stdClass();
			}

			$this->code->structure->{$level}->name = (string) $unit;
			$this->code->structure->{$level}->label = (string) $unit['label'];
			$this->code->structure->{$level}->level = (string) $unit['level'];
			$this->code->structure->{$level}->identifier = (string) $unit['identifier'];
			if ( !empty($unit['order_by']) )
			{
				$this->code->structure->{$level}->order_by = (string) $unit['order_by'];
			}
		}

		/*
		 * Iterate through the text.
		 */
		$this->i=0;
		foreach ($this->section->text as $section)
		{
			/*
			 * If there are no subsections, but just a single block of text, then simply save that.
			 */
			if (count($section) === 0)
			{
				if(!isset($this->code->section))
				{
					$this->code->section = new stdClass();
				}
				if(!isset($this->code->section->{$this->i}))
				{
					$this->code->section->{$this->i} = new stdClass();
				}
				$this->code->section->{$this->i}->text = trim((string) $section);
				$this->code->text = trim((string) $section);
				break;
			}

			/*
			 * If this law is broken down into subsections, iterate through those.
			 */
			foreach ($section as $subsection)
			{

				if(!isset($this->code->section))
				{
					$this->code->section = new stdClass();
				}
				if(!isset($this->code->section->{$this->i}))
				{
					$this->code->section->{$this->i} = new stdClass();
				}

				$this->code->section->{$this->i}->text = trim((string) $subsection);

				/*
				 * If this subsection has text, save it. Some subsections will not have text, such
				 * as those that are purely structural, existing to hold sub-subsections, but
				 * containing no text themselves.
				 */
				if ( !empty( $this->code->section->{$this->i}->text ) )
				{
					$this->code->text .= (string) $subsection['prefix'] . ' '
						. trim((string) $subsection) . "\r\r";
				}

				$this->code->section->{$this->i}->prefix = (string) $subsection['prefix'];
				$this->prefix_hierarchy[] = (string) $subsection['prefix'];

				if(!isset($this->code->section->{$this->i}->prefix_hierarchy))
				{
					$this->code->section->{$this->i}->prefix_hierarchy = new stdClass();
				}
				$this->code->section->{$this->i}->prefix_hierarchy->{0} = (string) $subsection['prefix'];

				/*
				 * If this subsection has a specified type (e.g., "table"), save that.
				 */
				if (!empty($subsection['type']))
				{
					$this->code->section->{$this->i}->type = (string) $subsection['type'];
				}
				$this->code->section->{$this->i}->prefix = (string) $subsection['prefix'];

				$this->i++;

				/*
				 * Recurse through any subsections.
				 */
				if (count($subsection) > 0)
				{
					$this->recurse($subsection);
				}

				/*
				 * Having come to the end of the loop, reset the prefix hierarchy.
				 */
				$this->prefix_hierarchy = array();
			}
		}

		/*
		 * If there any tags, store those, too.
		 */
		if (isset($this->section->tags))
		{

			/*
			 * Create an object to store the tags.
			 */
			$this->code->tags = new stdClass();

			/*
			 * Iterate through each of the tags and move them over to $this->code.
			 */
			foreach ($this->section->tags->tag as $tag)
			{
				$this->code->tags->tag = trim($tag);
			}

		}

		return TRUE;
	}

	/**
	 * Create permalinks from what's in the database
	 */
	public function build_permalinks()
	{
		/*
		 * Reset permalinks.
		 */
		$this->move_old_permalinks($this->edition_id);

		$this->delete_permalinks($this->edition_id);
		$this->build_permalink_subsections($this->edition_id);
	}

	/**
	 * Move old permalinks
	 */
	public function move_old_permalinks($edition_id)
	{
		/*
		 * Get the current edition.
		 */
		$edition_obj = new Edition(array('db' => $this->db));
		$current_edition = $edition_obj->current();

		/*
		 * First, delete anything that's not a real permalink for any edition
		 * that's not current.
		 */
		$sql = 'DELETE FROM permalinks
			WHERE permalink = 0 AND edition_id <> :edition_id';
		$sql_args = array(':edition_id' => $current_edition->id);
		$statement = $this->db->prepare($sql);
		$statement->execute($sql_args);

		/*
		 * Then make all remaining permalinks preferred for any edition that's
		 * not current.
		 */
		$sql = 'UPDATE permalinks
			SET preferred = 1
			WHERE permalink = 1 AND preferred = 0 AND
			edition_id <> :edition_id';
		$sql_args = array(':edition_id' => $edition_id);
		$statement = $this->db->prepare($sql);

		$statement->execute($sql_args);
	}


	/**
	 * Remove all old permalinks
	 */
	// TODO: eventually, we'll want to keep these and have multiple versions.
	// See issues #314 #362 #363
	public function delete_permalinks($edition_id)
	{

		$sql = 'DELETE FROM permalinks WHERE edition_id = :edition_id';
		$sql_args = array(':edition_id' => $edition_id);
		$statement = $this->db->prepare($sql);

		$statement->execute($sql_args);
	}

	/**
	 * Recurse through all subsections to build permalink data.
	 */
	public function build_permalink_subsections($edition_id, $parent_id = null)
	{

		$edition_obj = new Edition(array('db' => $this->db));
		$edition = $edition_obj->find_by_id($edition_id);

		/*
		 * If we don't have a parent, set the base url.
		 * We only want to do this once.
		 */
		if (!isset($parent_id))
		{
			/*
			 * By default, the actual permalink is preferred.
			 */
			$preferred = 1;

			/*
			 * If this is the current edition, add links to the urls
			 * without the edition slug.  This becomes the preferred
			 * link url.
			 */
			if ($edition->current)
			{
				$insert_data = array(
					':object_type' => 'structure',
					':relational_id' => '',
					':identifier' => '',
					':token' => '',
					':url' => '/browse/',
					':edition_id' => $edition_id,
					':preferred' => $preferred,
					':permalink' => 0
				);
				$this->permalink_obj->create($insert_data);

				$preferred = 0;
			}

			$insert_data = array(
				':object_type' => 'structure',
				':relational_id' => '',
				':identifier' => '',
				':token' => '',
				':url' => '/' . $edition->slug . '/',
				':edition_id' => $edition_id,
				':preferred' => $preferred,
				':permalink' => 1
			);
			$this->permalink_obj->create($insert_data);

		}

		$structure_sql =
			'SELECT structure_unified.*
			FROM structure
			LEFT JOIN structure_unified
				ON structure.id = structure_unified.s1_id
			WHERE structure.edition_id = :edition_id';

		/*
		 * We use prepared statements for efficiency.  As a result,
		 * we need to keep an array of our arguments rather than
		 * hardcoding them in the SQL.
		 */
		$structure_args = array(
			':edition_id' => $edition_id
		);

		if (isset($parent_id))
		{
			$structure_sql .= ' AND parent_id = :parent_id';
			$structure_args[':parent_id'] = $parent_id;
		}
		else
		{
			$structure_sql .= ' AND parent_id IS NULL';
		}

		$structure_statement = $this->db->prepare($structure_sql, array(PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY));
		$structure_statement->execute($structure_args);

		/*
		 * Get results as an array to save memory
		 */
		while ($item = $structure_statement->fetch(PDO::FETCH_ASSOC))
		{
			/*
			 * Figure out the URL for this structural unit by iterating through the "identifier"
			 * columns in this row.
			 */
			$identifier_parts = array();

			foreach ($item as $key => $value)
			{
				if (preg_match('/s[0-9]_identifier/', $key) == 1)
				{
					/*
					 * Higher-level structural elements (e.g., titles) will have blank columns in
					 * structure_unified, so we want to omit any blank values. Because a valid
					 * structural unit identifier is "0" (Virginia does this), we check the string
					 * length, rather than using empty().
					 */
					if (strlen($value) > 0)
					{
						$identifier_parts[] = urlencode($value);
					}
				}
			}
			$identifier_parts = array_reverse($identifier_parts);
			$structure_token = implode('/', $identifier_parts);

			/*
			 * Insert the structure
			 */

			/*
			 * By default, the actual permalink is preferred.
			 */
			$preferred = 1;

			/*
			 * If this is the current edition, add links to the urls
			 * without the edition slug.  This becomes the preferred
			 * link url.
			 */
			if ($edition->current)
			{
				$insert_data = array(
					':object_type' => 'structure',
					':relational_id' => $item['s1_id'],
					':identifier' => $item['s1_identifier'],
					':token' => $structure_token,
					':url' => '/' . $structure_token . '/',
					':edition_id' => $edition_id,
					':preferred' => 1,
					':permalink' => 0
				);
				$this->permalink_obj->create($insert_data);

				$preferred = 0;
			}

			/*
			 * Insert actual permalinks.
			 */
			$insert_data = array(
				':object_type' => 'structure',
				':relational_id' => $item['s1_id'],
				':identifier' => $item['s1_identifier'],
				':token' => $structure_token,
				':url' => '/' . $edition->slug . '/' . $structure_token . '/',
				':edition_id' => $edition_id,
				':preferred' => $preferred,
				':permalink' => 1
			);
			$this->permalink_obj->create($insert_data);

			/*
			 * Now we can use our data to build the child law identifiers
			 */
			if (INCLUDES_REPEALED !== TRUE)
			{
				$laws_sql = '	SELECT id, structure_id, section AS section_number, catch_line
								FROM laws
								WHERE structure_id = :s_id
								AND laws.edition_id = :edition_id
								ORDER BY order_by, section';
			}
			else
			{
				$laws_sql = '	SELECT laws.id, laws.structure_id, laws.section AS section_number,
								laws.catch_line
								FROM laws
								LEFT OUTER JOIN laws_meta
									ON laws_meta.law_id = laws.id AND laws_meta.meta_key = "repealed"
								WHERE structure_id = :s_id
								AND (laws_meta.meta_value = "n" OR laws_meta.meta_value IS NULL)
								AND laws.edition_id = :edition_id
								ORDER BY order_by, section';
			}
			$laws_statement = $this->db->prepare($laws_sql, array(PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY));
			$laws_sql_args = array(
				':s_id' => $item['s1_id'],
				':edition_id' => $edition_id
			);
			$laws_statement->execute( $laws_sql_args );

			while($law = $laws_statement->fetch(PDO::FETCH_ASSOC))
			{

				/*
				 * Note that we descend from our most-preferred url option
				 * to our least, depending on what flags have been set.
				 */
				$preferred = 1;

				if(!defined('LAW_LONG_URLS') || LAW_LONG_URLS === FALSE)
				{
					/*
					 * Current-and-short is the most-preferred (shortest) url.
					 */

					if ($edition->current)
					{
						$insert_data = array(
							':object_type' => 'law',
							':relational_id' => $law['id'],
							':identifier' => $law['section_number'],
							':token' => $structure_token . '/' . $law['section_number'],
							':url' => '/' . $law['section_number'] . '/',
							':edition_id' => $edition_id,
							':permalink' => 0,
							':preferred' => 1
						);
						$this->permalink_obj->create($insert_data);

						$preferred = 0;
					}

					/*
					 * If this is not-current, then short is the most-preferred.
					 */
					$insert_data = array(
						':object_type' => 'law',
						':relational_id' => $law['id'],
						':identifier' => $law['section_number'],
						':token' => $structure_token . '/' . $law['section_number'],
						':url' => '/' . $edition->slug . '/' . $law['section_number'] . '/',
						':edition_id' => $edition_id,
						':permalink' => 0,
						':preferred' => $preferred
					);
					$this->permalink_obj->create($insert_data);

					$preferred = 0;
				}

				/*
				 * Long and current is our third choice.
				 */
				if ($edition->current)
				{
					$insert_data = array(
						':object_type' => 'law',
						':relational_id' => $law['id'],
						':identifier' => $law['section_number'],
						':token' => $structure_token . '/' . $law['section_number'],
						':url' => '/' . $structure_token . '/' . $law['section_number'] . '/',
						':edition_id' => $edition_id,
						':permalink' => 0,
						':preferred' => $preferred
					);
					$this->permalink_obj->create($insert_data);

					$preferred = 0;
				}

				/*
				 * Failing everything else, use the super-long url.
				 */
				$insert_data = array(
					':object_type' => 'law',
					':relational_id' => $law['id'],
					':identifier' => $law['section_number'],
					':token' => $structure_token . '/' . $law['section_number'],
					':url' => '/' . $edition->slug . '/' . $structure_token . '/' . $law['section_number'] . '/',
					':edition_id' => $edition_id,
					':permalink' => 1,
					':preferred' => $preferred
				);
				$this->permalink_obj->create($insert_data);
			}

			$this->build_permalink_subsections($edition_id, $item['s1_id']);

		}
	}

	/**
	 * Do any setup.
	 */
	public function pre_parse()
	{
	}
	/**
	 * Do any cleanup.
	 */
	public function post_parse()
	{
	}

	/**
	 * Recurse through subsections of arbitrary depth. Subsections can be nested quite deeply, so
	 * we call this method recursively to gather their content.
	 */
	public function recurse($section)
	{

		if ( !isset($section) || !isset($this->code) )
		{
			return FALSE;
		}

		/* Track how deep we've recursed, in order to create the prefix hierarchy. */
		if (!isset($this->depth))
		{
			$this->depth = 1;
		}
		else
		{
			$this->depth++;
		}

		/*
		 * Iterate through each subsection.
		 */
		foreach ($section as $subsection)
		{

			/*
			 * Store this subsection's data in our code object.
			 */
			if(!isset($this->code->section->{$this->i}))
			{
				$this->code->section->{$this->i} = new stdClass();
			}

			$this->code->section->{$this->i}->text = (string) $subsection;
			if (!empty($subsection['type']))
			{
				$this->code->section->{$this->i}->type = (string) $subsection['type'];
			}
			$this->code->section->{$this->i}->prefix = (string) $subsection['prefix'];
			$this->prefix_hierarchy[] = (string) $subsection['prefix'];

			$this->code->section->{$this->i}->prefix_hierarchy = (object) $this->prefix_hierarchy;

			/*
			 * We increment our counter at this point, rather than at the end of the loop, because
			 * of the use of the recurse() method after it.
			 */
			$this->i++;

			/*
			 * If this recurses further, keep going.
			 */
			if (isset($subsection->section))
			{
				$this->recurse($subsection->section);
			}

			/*
			 * Reduce the prefix hierarchy back to where it started, for our next loop through.
			 */
			$this->prefix_hierarchy = array_slice($this->prefix_hierarchy, 0, ($this->depth));

		}

		/*
		 * Reset the prefix depth back to its default of 1.
		 */
		$this->depth--;

		return TRUE;

	}


	/**
	 * Take an object containing the normalized code data and store it.
	 */
	public function store()
	{
		if (!isset($this->code))
		{
			die('No data provided.');
		}

		/*
		 * This first section creates the record for the law, but doesn't do anything with the
		 * content of it just yet.
		 */

		/*
		 * Try to create this section's structural element(s). If they already exist,
		 * create_structure() will handle that silently. Either way a structural ID gets returned.
		 */
		$structure = new Parser(
			array(
				'db' => $this->db,
				'edition_id' => $this->edition_id,
				'previous_edition_id' => $this->previous_edition_id,
				'structure_labels' => $this->structure_labels
			)
		);

		if(isset($this->code->structure))
		{

			foreach ($this->code->structure as $struct)
			{

				$structure->identifier = $struct->identifier;
				$structure->name = $struct->name;
				$structure->label = $struct->label;
				$structure->level = $struct->level;
				if(isset($struct->metadata))
				{
					$structure->metadata = $struct->metadata;
				}
				else
				{
					$structure->metadata = '';
				}

				/* If we've gone through this loop already, then we have a parent ID. */
				if (isset($this->code->structure_id))
				{
					$structure->parent_id = $this->code->structure_id;
				}
				$this->code->structure_id = $structure->create_structure();

			}

			/*
			 * When that loop is finished, because structural units are ordered from most general to
			 * most specific, we're left with the section's parent ID. Preserve it.
			 */
			$query['structure_id'] = $this->code->structure_id;
		}
		else
		{
			$this->logger->error('ERROR Section without structure found: '. print_r($this->code, TRUE), 10);
		}

		/*
		 * Build up an array of field names and values, using the names of the database columns as
		 * the key names.
		 */
		$query['catch_line'] = $this->code->catch_line;
		$query['section'] = $this->code->section_number;
		$query['text'] = $this->code->text;
		if (!empty($this->code->order_by))
		{
			$query['order_by'] = $this->code->order_by;
		}
		if (isset($this->code->history))
		{
			$query['history'] = $this->code->history;
		}

		/*
		 * Create the beginning of the insertion statement.
		 */
		$sql = 'INSERT INTO laws
				SET date_created=now()';
		$sql_args = array();
		$query['edition_id'] = $this->edition_id;

		/*
		 * Iterate through the array and turn it into SQL.
		 */
		foreach ($query as $name => $value)
		{
			$sql .= ', ' . $name . ' = :' . $name;
			$sql_args[':' . $name] = $value;
		}

		$statement = $this->db->prepare($sql);
		$result = $statement->execute($sql_args);

		if ($result === FALSE)
		{
			echo '<p>Failure: ' . $sql . '</p>';
			var_dump($sql_args);
		}

		/*
		 * Preserve the insert ID from this law, since we'll need it below.
		 */
		$law_id = $this->db->lastInsertID();

		/*
		 * This second section inserts the textual portions of the law.
		 */

		/*
		 * Pull out any mentions of other sections of the code that are found within its text and
		 * save a record of those, for crossreferencing purposes.
		 */
		$references = new Parser(
			array(
				'db' => $this->db,
				'edition_id' => $this->edition_id,
				'previous_edition_id' => $this->previous_edition_id,
				'structure_labels' => $this->structure_labels
			)
		);
		$references->text = $this->code->text;
		$sections = $references->extract_references();
		if ( ($sections !== FALSE) && (count($sections) > 0) )
		{
			$references->section_id = $law_id;
			$references->sections = $sections;
			$success = $references->store_references();
			if ($success === FALSE)
			{
				echo '<p>References for section ID '.$law_id.' were found, but could not be
					stored.</p>';
			}
		}

		/*
		 * Store any metadata.
		 */
		if (isset($this->code->metadata))
		{

			/*
			 * Step through every metadata field and add it.
			 */
			$sql = 'INSERT INTO laws_meta
					SET law_id = :law_id,
					meta_key = :meta_key,
					meta_value = :meta_value,
					edition_id = :edition_id';
			$statement = $this->db->prepare($sql);

			foreach ($this->code->metadata as $key => $value)
			{
				$sql_args = array(
					':law_id' => $law_id,
					':meta_key' => $key,
					':meta_value' => $value,
					':edition_id' => $this->edition_id
				);
				$result = $statement->execute($sql_args);

				if ($result === FALSE)
				{
					echo '<p>Failure: '.$sql.'</p>';
				}
			}

		}

		/*
		 * Store any tags associated with this law.
		 */
		if (isset($this->code->tags))
		{
			$sql = 'INSERT INTO tags
					SET law_id = :law_id,
					section_number = :section_number,
					text = :tag,
					edition_id = :edition_id';
			$statement = $this->db->prepare($sql);

			foreach ($this->code->tags as $tag)
			{
				$sql_args = array(
					':law_id' => $law_id,
					':section_number' => $this->code->section_number,
					':tag' => $tag,
					':edition_id' => $this->edition_id
				);
				$result = $statement->execute($sql_args);

				if ($result === FALSE)
				{
					echo '<p>Failure: '.$sql.'</p>';
					var_dump($sql_args);
				}
			}
		}

		/*
		 * Step through each section.
		 */
		$i=1;
		foreach ($this->code->section as $section)
		{

			/*
			 * If no section type has been specified, make it your basic section.
			 */
			if (empty($section->type))
			{
				$section->type = 'section';
			}

			/*
			 * Insert this subsection into the text table.
			 */
			$sql = 'INSERT INTO text
					SET law_id = :law_id,
					sequence = :sequence,
					type = :type,
					date_created=now(),
					edition_id = :edition_id';
			$sql_args = array(
				':law_id' => $law_id,
				':sequence' => $i,
				':type' => $section->type,
				':edition_id' => $this->edition_id
			);
			if (!empty($section->text))
			{
				$sql .= ', text = :text';
				$sql_args[':text'] = $section->text;
			}

			$statement = $this->db->prepare($sql);
			$result = $statement->execute($sql_args);

			if ($result === FALSE)
			{
				echo '<p>Failure: '.$sql.'</p>';
			}

			/*
			 * Preserve the insert ID from this section of text, since we'll need it below.
			 */
			$text_id = $this->db->lastInsertID();

			/*
			 * Start a new counter. We'll use it to track the sequence of subsections.
			 */
			$j = 1;

			/*
			 * Step through every portion of the prefix (i.e. A4b is three portions) and insert
			 * each.
			 */
			if (isset($section->prefix_hierarchy))
			{

				foreach ($section->prefix_hierarchy as $prefix)
				{
					$sql = 'INSERT INTO text_sections
							SET text_id = :text_id,
							identifier = :identifier,
							sequence = :sequence,
							date_created=now(),
							edition_id = :edition_id';
					$sql_args = array(
						':text_id' => $text_id,
						':identifier' => $prefix,
						':sequence' => $j,
						':edition_id' => $this->edition_id
					);

					$statement = $this->db->prepare($sql);
					$result = $statement->execute($sql_args);

					if ($result === FALSE)
					{
						echo '<p>Failure: ' . $sql . '</p>';
					}

					$j++;
				}

			}

			$i++;
		}


		/*
		 * Trawl through the text for definitions.
		 */
		$dictionary = new Parser(
			array(
				'db' => $this->db,
				'edition_id' => $this->edition_id,
				'previous_edition_id' => $this->previous_edition_id,
				'structure_labels' => $this->structure_labels
			)
		);

		/*
		 * Pass this section of text to $dictionary.
		 */
		$dictionary->text = $this->code->text;

		/*
		 * Get a normalized listing of definitions.
		 */
		$definitions = $this->extract_definitions($this->code->text, $this->get_structure_labels());

		/*
		 * Check to see if this section or its containing structural unit were specified in the
		 * config file as a container for global definitions. If it was, then we override the
		 * presumed scope and provide a global scope.
		 */
		$ancestry = array();
		if (isset($this->code->structure))
		{
			foreach ($this->code->structure as $struct)
			{
				$ancestry[] = $struct->identifier;
			}
		}
		$ancestry = implode(',', $ancestry);
		$ancestry_section = $ancestry . ','.$this->code->section_number;
		if (defined('GLOBAL_DEFINITIONS') &&
				(GLOBAL_DEFINITIONS === $ancestry
				||
				GLOBAL_DEFINITIONS === $ancestry_section)
			)
		{
			$definitions->scope = 'global';
		}
		unset($ancestry);
		unset($ancestry_section);

		/*
		 * If any definitions were found in this text, store them.
		 */
		if ($definitions !== FALSE)
		{

			/*
			 * Populate the appropriate variables.
			 */
			$dictionary->terms = $definitions->terms;
			$dictionary->law_id = $law_id;
			$dictionary->scope = $definitions->scope;
			$dictionary->structure_id = $this->code->structure_id;
			$dictionary->edition_id = $this->edition_id;

			/*
			 * If the scope of this definition isn't section-specific, and isn't global, then
			 * find the ID of the structural unit that is the limit of its scope.
			 */
			if ( ($dictionary->scope != 'section') && ($dictionary->scope != 'global') )
			{
				$find_scope = new Parser(
					array(
						'db' => $this->db,
						'edition_id' => $this->edition_id,
						'previous_edition_id' => $this->previous_edition_id,
						'structure_labels' => $this->structure_labels
					)
				);
				$find_scope->label = $dictionary->scope;
				$find_scope->structure_id = $dictionary->structure_id;

				if($dictionary->structure_id)
				{
					$dictionary->structure_id = $find_scope->find_structure_parent();
					if ($dictionary->structure_id == FALSE)
					{
						unset($dictionary->structure_id);
					}
				}
			}

			/*
			 * If the scope isn't a structural unit, then delete it, so that we don't store it
			 * and inadvertently limit the scope.
			 */
			else
			{
				unset($dictionary->structure_id);
			}

			/*
			 * Determine the position of this structural unit.
			 */
			$structure = array_reverse($this->get_structure_labels());
			array_push($structure, 'global');

			/*
			 * Find and return the position of this structural unit in the hierarchical stack.
			 */
			$dictionary->scope_specificity = array_search($dictionary->scope, $structure);

			/*
			 * Store these definitions in the database.
			 */
			$dictionary->store_definitions();

		}

		/*
		 * Memory management.
		 */
		unset($references);
		unset($dictionary);
		unset($definitions);
		unset($chapter);
		unset($sections);
		unset($query);
	}


	/**
	 * When provided with a structural identifier, verifies whether that structural unit exists.
	 * Returns the structural database ID if it exists; otherwise, returns false.
	 */
	function structure_exists()
	{

		if (!isset($this->identifier))
		{
			return FALSE;
		}

		/*
		 * Assemble the query.
		 */
		$sql = 'SELECT id
				FROM structure
				WHERE identifier = :identifier
				AND edition_id = :edition_id';
		$sql_args = array(
			':identifier' => $this->identifier,
			':edition_id' => $this->edition_id
		);

		/*
		 * If a parent ID is present (that is, if this structural unit isn't a top-level unit), then
		 * include that in our query.
		 */
		if ( !empty($this->parent_id) )
		{
			$sql .= ' AND parent_id = :parent_id';
			$sql_args[':parent_id'] = $this->parent_id;
		}
		else
		{
			$sql .= ' AND parent_id IS NULL';
		}

		$statement = $this->db->prepare($sql);
		$result = $statement->execute($sql_args);

		if ( ($result === FALSE) || ($statement->rowCount() === 0) )
		{
			return FALSE;
		}

		$structure = $statement->fetch(PDO::FETCH_OBJ);
		return $structure->id;
	}


	/**
	 * When provided with a structural unit identifier and type, it creates a record for that
	 * structural unit. Save for top-level structural units (e.g., titles), it should always be
	 * provided with a $parent_id, which is the ID of the parent structural unit. Most structural
	 * units will have a name, but not all.
	 */
	function create_structure()
	{

		/*
		 * Sometimes the code contains references to no-longer-existent chapters and even whole
		 * titles of the code. These are void of necessary information. We want to ignore these
		 * silently. Though you'd think we should require a chapter name, we actually shouldn't,
		 * because sometimes chapters don't have names. In the Virginia Code, for instance, titles
		 * 8.5A, 8.6A, 8.10, and 8.11 all have just one chapter ("part"), and none of them have a
		 * name.
		 *
		 * Because a valid structural identifier can be "0" we can't simply use empty(), but must
		 * also verify that the string is longer than zero characters. We do both because empty()
		 * will valuate faster than strlen(), and because these two strings will almost never be
		 * empty.
		 */
		if (
				( empty($this->identifier) && (strlen($this->identifier) === 0) )
				||
				( empty($this->label) )
			)
		{
			return FALSE;
		}

		/*
		 * Begin by seeing if this structural unit already exists. If it does, return its ID.
		 */
		$structure_id = $this->structure_exists();
		if ($structure_id !== FALSE)
		{
			return $structure_id;
		}

		/* Now we know that this structural unit does not exist, so Insert this structural record
		 * into the database. It's tempting to use ON DUPLICATE KEY here, and eliminate the use of
		 * structure_exists(), but then MDB2's lastInsertID() becomes unreliable. That means we need
		 * a second query to determine the ID of this structural unit. Better to check if it exists
		 * first and insert it if it doesn't than to insert it every time and then query its ID
		 * every time, since the former approach will require many less queries than the latter.
		 */
		$sql = 'INSERT INTO structure
				SET identifier = :identifier';
		$sql_args = array(
			':identifier' => $this->identifier
		);
		if (!empty($this->name))
		{
			$sql .= ', name = :name';
			$sql_args[':name'] = $this->name;
		}
		$sql .= ', label = :label, edition_id = :edition_id';
		$sql .= ', depth = :depth, date_created=now()';
		$sql_args[':label'] = $this->label;
		$sql_args[':edition_id'] = $this->edition_id;
		$sql_args[':depth'] = $this->level;
		if (isset($this->parent_id))
		{
			$sql .= ', parent_id = :parent_id';
			$sql_args[':parent_id'] = $this->parent_id;

		}
		if(isset($this->metadata))
		{
			$sql .= ', metadata = :metadata';
			$sql_args[':metadata'] = serialize($this->metadata);
		}

		$statement = $this->db->prepare($sql);
		$result = $statement->execute($sql_args);

		if ($result === FALSE)
		{
			echo '<p>Failure: '.$sql.'</p>';
			var_dump($sql_args);
			return FALSE;
		}

		return $this->db->lastInsertID();

	}


	/**
	 * When provided with a structural unit ID and a label, this function will iteratively search
	 * through that structural unit's ancestry until it finds a structural unit with that label.
	 * This is meant for use while identifying definitions, within the store() method, specifically
	 * to set the scope of applicability of a definition.
	 */
	function find_structure_parent()
	{

		/*
		 * We require a beginning structure ID and the label of the structural unit that's sought.
		 */
		if ( !isset($this->structure_id) || !isset($this->label) )
		{
			return FALSE;
		}

		/*
		 * Make the sought parent ID available as a local variable, which we'll repopulate with each
		 * loop through the below while() structure.
		 */
		$parent_id = $this->structure_id;

		/*
		 * Establish a blank variable.
		 */
		$returned_id = '';

		/*
		 * Loop through a query for parent IDs until we find the one we're looking for.
		 */
		while ($returned_id == '')
		{

			$sql = 'SELECT id, parent_id, label
					FROM structure
					WHERE id = :id';
			$sql_args = array(
				':id' => $parent_id
			);

			$statement = $this->db->prepare($sql);
			$result = $statement->execute($sql_args);

			if ( ($result === FALSE) || ($statement->rowCount() == 0) )
			{
				echo '<p>Query failed: '.$sql.'</p>';
				var_dump($sql_args);
				return FALSE;
			}

			/*
			 * Return the result as an object.
			 */
			$structure = $statement->fetch(PDO::FETCH_OBJ);

			/*
			 * If the label of this structural unit matches the label that we're looking for, return
			 * its ID.
			 */
			if ($structure->label == $this->label)
			{
				return $structure->id;
			}

			/*
			 * Else if this structural unit has no parent ID, then our effort has failed.
			 */
			elseif (empty($structure->parent_id))
			{
				return FALSE;
			}

			/*
			 * If all else fails, then loop through again, searching one level farther up.
			 */
			else
			{
				$parent_id = $structure->parent_id;
			}
		}
	}


	/**
	 * When fed a section of the code that contains definitions, extracts the definitions from that
	 * section and returns them as an object. Requires only a block of text.
	 */
	function extract_definitions($text, $structure_labels)
	{
		$scope = 'global';

		if (!isset($text))
		{
			return FALSE;
		}

		/* Measure whether there are more straight quotes or directional quotes in this passage
		 * of text, to determine which type are used in these definitions. We double the count of
		 * directional quotes since we're only counting one of the two directions.
		 */
		if ( substr_count($text, '"') > (substr_count($text, '”') * 2) )
		{
			$quote_type = 'straight';
			$quote_sample = '"';
		}
		else
		{
			$quote_type = 'directional';
			$quote_sample = '”';
		}

		/*
		 * Break up this section into paragraphs. If HTML paragraph tags are present, break it up
		 * with those. If they're not, break it up with carriage returns.
		 */
		if (strpos($text, '<p>') !== FALSE)
		{
			$paragraphs = explode('<p>', $text);
		}
		else
		{
			$this->text = str_replace("\n", "\r", $text);
			$paragraphs = explode("\r", $text);
		}

		/*
		 * Discard any empty paragraphs.
		 */
		$paragraphs = array_values(array_filter($paragraphs));

		/*
		 * Create the empty array that we'll build up with the definitions found in this section.
		 */
		$definitions = array();

		/*
		 * Step through each paragraph and determine which contain definitions.
		 */
		foreach ($paragraphs as $index => $paragraph)
		{

			/*
			 * Any remaining paired paragraph tags are within an individual, multi-part definition,
			 * and can be turned into spaces.
			 */
			$paragraph = str_replace('</p><p>', ' ', $paragraph);

			/*
			 * Strip out any remaining HTML.
			 */
			$paragraph = strip_tags($paragraph);

			/*
			 * Calculate the scope of these definitions using the first line.
			 */
			if ($index === 0)
			{

				/*
				 * Gather up a list of structural labels and determine the length of the longest
				 * one, which we'll use to narrow the scope of our search for the use of structural
				 * labels within the text.
				 */

				usort($structure_labels, 'sort_by_length');
				$longest_label = strlen(current($structure_labels));

				/*
				 * Iterate through every scope indicator.
				 */
				foreach ($this->scope_indicators as $scope_indicator)
				{

					/*
					 * See if the scope indicator is present in this paragraph.
					 */
					$pos = stripos($paragraph, $scope_indicator);

					/*
					 * The term was found.
					 */
					if ($pos !== FALSE)
					{

						/*
						 * Now figure out the specified scope by examining the text that appears
						 * immediately after the scope indicator. Pull out as many characters as the
						 * length of the longest structural label.
						 */
						$phrase = substr( $paragraph, ($pos + strlen($scope_indicator)), $longest_label );

						/*
						 * Iterate through the structural labels and check each one to see if it's
						 * present in the phrase that we're examining.
						 */
						foreach ($structure_labels as $structure_label)
						{

							if (stripos($phrase, $structure_label) !== FALSE)
							{

								/*
								 * We've made a match -- we've successfully identified the scope of
								 * these definitions.
								 */
								$scope = $structure_label;

								/*
								 * Now that we have a match, we can break out of both the containing
								 * foreach() and its parent foreach().
								 */
								break(2);

							}

							/*
							 * If we can't calculate scope, then let’s assume that it's specific to
							 * the most basic structural unit -- the individual law -- for the sake
							 * of caution. We pull that off of the end of the structure labels array
							 */
							$scope = end($structure_labels);

						}

					}

				}

				/*
				 * That's all we're going to get out of this paragraph, so move onto the next one.
				 */
				continue;

			}

			/*
			 * All defined terms are surrounded by quotation marks, so let's use that as a criteria
			 * to round down our candidate paragraphs.
			 */
			if (strpos($paragraph, $quote_sample) !== FALSE)
			{

				/*
				 * Iterate through every linking phrase and see if it's present in this paragraph.
				 * We need to find the right one that will allow us to connect a term to its
				 * definition.
				 */
				foreach ($this->linking_phrases as $linking_phrase)
				{

					if (strpos($paragraph, $linking_phrase) !== FALSE)
					{

						/*
						 * Extract every word in quotation marks in this paragraph as a term that's
						 * being defined here. Most definitions will have just one term being
						 * defined, but some will have two or more.
						 */
						preg_match_all('/("|“)([A-Za-z]{1})([A-Za-z,\'\s-]*)([A-Za-z]{1})("|”)/', $paragraph, $terms);

						/*
						 * If we've made any matches.
						 */
						if ( ($terms !== FALSE) && (count($terms) > 0) )
						{

							/*
							 * We only need the first element in this multi-dimensional array, which
							 * has the actual matched term. It includes the quotation marks in which
							 * the term is enclosed, so we strip those out.
							 */
							if ($quote_type == 'straight')
							{
								$terms = str_replace('"', '', $terms[0]);
							}
							elseif ($quote_type == 'directional')
							{
								$terms = str_replace('“', '', $terms[0]);
								$terms = str_replace('”', '', $terms);
							}

							/*
							 * Eliminate whitespace.
							 */
							$terms = array_map('trim', $terms);

							/* Lowercase most (but not necessarily all) terms. Any term that
							 * contains any lowercase characters will be made entirely lowercase.
							 * But any term that is in all caps is surely an acronym, and should be
							 * stored in its original case so that we don't end up with overzealous
							 * matches. For example, a two-letter acronym like "CA" is a valid
							 * (real-world) definition, and we don't want to match every time "ca"
							 * appears within a word. (Though note that we only match terms
							 * surrounded by word boundaries.)
							 */
							foreach ($terms as &$term)
							{
								/*
								 * Drop noise words that occur in lists of words.
								 */
								if (($term == 'and') || ($term == 'or'))
								{
									unset($term);
									continue;
								}

								/*
								 * Step through each character in this word.
								 */
								for ($i=0; $i<strlen($term); $i++)
								{
									/*
									 * If there are any lowercase characters, then make the whole
									 * thing lowercase.
									 */
									if ( (ord($term{$i}) >= 97) && (ord($term{$i}) <= 122) )
									{
										$term = strtolower($term);
										break;
									}
								}
							}

							/*
							 * This is absolutely necessary. Without it, the following foreach()
							 * loop will simply use $term as-is through each loop, rather than
							 * spawning new instances based on $terms. This is presumably a bug in
							 * the current version of PHP (5.2), because it surely doesn't make any
							 * sense.
							 */
							unset($term);

							/*
							 * Step through all of our matches and save them as discrete
							 * definitions.
							 */
							foreach ($terms as $term)
							{

								/*
								 * It's possible for a definition to be preceded by a subsection
								 * number. We want to pare down our definition down to the minimum,
								 * which means excluding that. Solution: Start definitions at the
								 * first quotation mark.
								 */
								if ($quote_type == 'straight')
								{
									$paragraph = substr($paragraph, strpos($paragraph, '"'));
								}
								elseif ($quote_type == 'directional')
								{
									$paragraph = substr($paragraph, strpos($paragraph, '“'));
								}

								/*
								 * Comma-separated lists of multiple words being defined need to
								 * have the trailing commas removed.
								 */
								if (substr($term, -1) == ',')
								{
									$term = substr($term, 0, -1);
								}

								/*
								 * If we don't yet have a record of this term.
								 */
								if (!isset($definitions[$term]))
								{
									/*
									 * Append this definition to our list of definitions.
									 */
									$definitions[$term] = $paragraph;
								}

								/* If we already have a record of this term. This is for when a word
								 * is defined twice, once to indicate what it means, and one to list
								 * what it doesn't mean. This is actually pretty common.
								 */
								else
								{
									/*
									 * Make sure that they're not identical -- this can happen if
									 * the defined term is repeated, in quotation marks, in the body
									 * of the definition.
									 */
									if ( trim($definitions[$term]) != trim($paragraph) )
									{
										/*
										 * Append this definition to our list of definitions.
										 */
										$definitions[$term] .= ' '.$paragraph;
									}
								}
							} // end iterating through matches
						} // end dealing with matches

						/*
						 * Because we have identified the linking phrase for this paragraph, we no
						 * longer need to continue to iterate through linking phrases.
						 */
						break;

					} // end matched linking phrase
				} // end iterating through linking phrases
			} // end this candidate paragraph

			/*
			 * We don't want to accidentally use this the next time we loop through.
			 */
			unset($terms);
		}

		if (count($definitions) == 0)
		{
			return FALSE;
		}

		/*
		 * Make the list of definitions a subset of a larger variable, so that we can store things
		 * other than terms.
		 */
		$tmp = array();
		$tmp['terms'] = $definitions;
		$tmp['scope'] = $scope;
		$definitions = $tmp;
		unset($tmp);

		/*
		 * Return our list of definitions, converted from an array to an object.
		 */
		return (object) $definitions;

	} // end extract_definitions()


	/**
	 * When provided with an object containing a list of terms, their definitions, their scope,
	 * and their section number, this will store them in the database.
	 */
	function store_definitions()
	{

		if ( !isset($this->terms) || !isset($this->law_id) || !isset($this->scope) )
		{
			return FALSE;
		}

		/*
		 * If we have no structure ID, just substitute NULL, to avoid creating blank entries in the
		 * structure_id column.
		 */
		if (!isset($this->structure_id))
		{
			$this->structure_id = 'NULL';
		}

		/*
		 * Iterate through our definitions to build up our SQL.
		 */

		/*
		 * Start assembling our SQL string.
		 */
		$sql = 'INSERT INTO dictionary (law_id, term, definition, scope, scope_specificity,
				structure_id, date_created, edition_id)
				VALUES (:law_id, :term, :definition, :scope, :scope_specificity,
				:structure_id, now(), :edition_id)';
		$statement = $this->db->prepare($sql);

		foreach ($this->terms as $term => $definition)
		{

			$sql_args = array(
				':law_id' => $this->law_id,
				':term' => $term,
				':definition' => $definition,
				':scope' => $this->scope,
				':scope_specificity' => $this->scope_specificity,
				':structure_id' => $this->structure_id,
				':edition_id' => $this->edition_id
			);
			$result = $statement->execute($sql_args);

		}


		/*
		 * Memory management.
		 */
		unset($this);

		return $result;

	} // end store_definitions()


	function query($sql)
	{
		$result = $this->db->exec($sql);
		if ($result === FALSE)
		{
			return $this->db->errorInfo();
		}
		else
		{
			return TRUE;
		}
	}

	/**
	 * Find mentions of other sections within a section and return them as an array.
	 */
	function extract_references()
	{

		/*
		 * If we don't have any text to analyze, then there's nothing more to do be done.
		 */
		if (!isset($this->text))
		{
			return FALSE;
		}

		/*
		 * Find every string that fits the acceptable format for a state code citation.
		 */
		preg_match_all(SECTION_REGEX, $this->text, $matches);

		/*
		 * We don't need all of the matches data -- just the first set. (The others are arrays of
		 * subset matches.)
		 */
		$matches = $matches[0];

		/*
		 * We assign the count to a variable because otherwise we're constantly diminishing the
		 * count, meaning that we don't process the entire array.
		 */
		$total_matches = count($matches);
		for ($j=0; $j<$total_matches; $j++)
		{

			$matches[$j] = trim($matches[$j]);

			/*
			 * Lop off trailing periods, colons, and hyphens.
			 */
			if ( (substr($matches[$j], -1) == '.') || (substr($matches[$j], -1) == ':')
				|| (substr($matches[$j], -1) == '-') )
			{
				$matches[$j] = substr($matches[$j], 0, -1);
			}

		}

		/*
		 * Make unique, but with counts.
		 */
		$sections = array_count_values($matches);
		unset($matches);

		return $sections;

	} // end extract_references()


	/**
	 * Take an array of references to other sections contained within a section of text and store
	 * them in the database.
	 */
	function store_references()
	{

		/*
		 * If we don't have any section numbers or a section number to tie them to, then we can't
		 * do anything at all.
		 */
		if ( (!isset($this->sections)) || (!isset($this->section_id)) )
		{
			return FALSE;
		}

		/*
		 * Start creating our insertion query.
		 */
		$sql = 'INSERT INTO laws_references
				(law_id, target_section_number, target_law_id, mentions, date_created, edition_id)
				VALUES (:law_id, :section_number, :target_law_id, :mentions, now(), :edition_id)
				ON DUPLICATE KEY UPDATE mentions=mentions';
				$statement = $this->db->prepare($sql);
		$i=0;
		foreach ($this->sections as $section => $mentions)
		{
			$sql_args = array(
				':law_id' => $this->section_id,
				':section_number' => $section,
				':target_law_id' => '0',
				':mentions' => $mentions,
				':edition_id' => $this->edition_id
			);

			$result = $statement->execute($sql_args);

			if ($result === FALSE)
			{
				echo '<p>Failed: '.$sql.'</p>';
				return FALSE;
			}
		}

		return TRUE;

	} // end store_references()


	/**
	 * Turn the history sections into atomic data.
	 */
	function extract_history()
	{

		/*
		 * If we have no history text, then we're done here.
		 */
		if (!isset($this->history))
		{
			return FALSE;
		}

		/*
		 * The list is separated by semicolons and spaces.
		 */
		$updates = explode('; ', $this->history);

		$i=0;
		foreach ($updates as &$update)
		{

			/*
			 * Match lines of the format "2010, c. 402, § 1-15.1"
			 */
			$pcre = '/([0-9]{4}), c\. ([0-9]+)(.*)/';

			/*
			 * First check for single matches.
			 */
			$result = preg_match($pcre, $update, $matches);
			if ( ($result !== FALSE) && ($result !== 0) )
			{

				if (!empty($matches[1]))
				{
					$final->{$i}->year = $matches[1];
				}
				if (!empty($matches[2]))
				{
					$final->{$i}->chapter = trim($matches[2]);
				}
				if (!empty($matches[3]))
				{
					$result = preg_match(SECTION_REGEX, $update, $matches[3]);
					if ( ($result !== FALSE) && ($result !== 0) )
					{
						$final->{$i}->section = $matches[0];
					}
				}

			}

			/*
			 * Then check for multiple matches.
			 */
			else
			{

				/*
				 * Match lines of the format "2009, cc. 401,, 518, 726, § 2.1-350.2"
				 */
				$pcre = '/([0-9]{2,4}), cc\. ([0-9,\s]+)/';
				$result = preg_match_all($pcre, $update, $matches);

				if ( ($result !== FALSE) && ($result !== 0) )
				{

					/*
					 * Save the year.
					 */
					$final->{$i}->year = $matches[1][0];

					/*
					 * Save the chapter listing. We eliminate any trailing slash and space to avoid
					 * saving empty array elements.
					 */
					$chapters = rtrim(trim($matches[2][0]), ',');

					/*
					 * We explode on a comma, rather than a comma and a space, because of occasional
					 * typographical errors in histories.
					 */
					$chapters = explode(',', $chapters);

					/*
					 * Step through each of these chapter references and trim down the leading
					 * spaces (a result of creating the array based on commas rather than commas and
					 * spaces) and eliminate any that are blank.
					 */
					$chapter_count = count($chapters);

					for ($j=0; $j<$chapter_count; $j++)
					{
						$chapters[$j] = trim($chapters[$j]);
						if (empty($chapters[$j]))
						{
							unset($chapters[$j]);
						}
					}

					$final->{$i}->chapter = $chapters;

					/*
					 * Locate any section identifier.
					 */
					$result = preg_match(SECTION_REGEX, $update, $matches);
					if ( ($result !== FALSE) && ($result !== 0) )
					{
						$final->{$i}->section = $matches[0];
					}

				}

			}

			$i++;

		}

		if ( isset($final) && is_object($final) )
		{
			return $final;
		}

	} // end extract_history()

	public function get_structure_labels()
	{
		$sql = 'SELECT label FROM structure GROUP BY label ' .
			'ORDER BY depth ASC';
		$statement = $this->db->prepare($sql);
		$result = $statement->execute();


		$structure_labels = array();

		if ( ($result === FALSE) )
		{
			echo '<p>Query failed: '.$sql.'</p>';
			var_dump($sql_args);
			return FALSE;
		}
		else
		{
			if($statement->rowCount() == 0)
			{
				/*
				 * We may not have a structure yet.  That's ok.
				 */
				return null;
			}
			else{
				while($row = $statement->fetch(PDO::FETCH_ASSOC))
				{
					$structure_labels[] = $row['label'];
				}
			}
		}

		/*
		 * Our lowest level, not represented in the structure table, is 'section'
		 */
		$structure_labels[] = 'Section';

		return $structure_labels;
	} // end get_structure_labels()

} // end Parser class
