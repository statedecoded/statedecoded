<?php

/**
 * Parser for Municode's XML data format.
 *
 ******************************************
 * NOTE: This is very new and very rough! *
 * Use at your own risk!                  *
 * Please submit issues as you find them! *
 ******************************************
 *
 * PHP version 5
 *
 * @license		http://www.gnu.org/licenses/gpl.html GPL 3
 * @version		0.9
 * @link		http://www.statedecoded.com/
 * @since		0.3
*/

/**
 * This class may be populated with custom functions.
 */
class State
{
}

/**
 * The parser for importing legal codes. This is fully functional for importing The State Decoded's
 * prescribed XML format <https://github.com/statedecoded/statedecoded/wiki/XML-Format-for-Parser>,
 * and serves as a guide for those who want to parse an alternate format.
 */
class Parser
{

	public $file = 0;
	public $data;
	public $db;
	public $edition_id;
	public $structure_labels;
	public $structures;
	public $sections;
	public $order_count = 1;

	public $export_file = 'Book_Final.xml';

	public $structure_regex = '/^(?P<type>PART|ARTICLE|CHAPTER|DIVISION|APPENDIX) (?P<number>.+?)\.?\s*$/i';
	public $section_regex = '/^(SECTION|Sec(s)?\.?) (?P<number>.*?)\.?\s*$/i';
	public $subsection_regex = '/^(SECTION|Sec(s)?\.?) (?P<number>[A-Z0-9a-z-\.]{1,10})\.?\s*$/i';

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
				$this->directory = dirname(dirname(__FILE__));
			}

			if (!file_exists($this->directory) || !is_dir($this->directory))
			{
				throw new Exception('Import directory does not exist "' .
					$this->directory . '"');
			}

			$filename = $this->directory . $this->export_file;

			if(file_exists($filename) && is_file($filename) && is_readable($filename))
			{
				$data = simplexml_load_string(file_get_contents($filename));

				/*
				 * Put the data into a recursive object for iterating.
				 * RecursiveDataStructure is defined at the bottom of this file, it uses the
				 * PHP standard RecursiveIterator interface.
				 */

				$this->data = new RecursiveDataStructure($data->xpath('./level1'));

				/*
				 * Our iterator to loop over the data structure.
				 */

				$this->iterator = new RecursiveIteratorIterator($this->data, RecursiveIteratorIterator::SELF_FIRST);
			}
			else
			{
				throw new Exception('Cannot access data file "' . $filename . '"');
				return;
			}

		}

		if (!$this->structure_labels)
		{
			$this->structure_labels = $this->get_structure_labels();
		}

	}


	/**
	 * Step through every structure
	 */
	public function iterate()
	{
		if($this->iterator->valid())
		{
			$return_value = &$this->iterator->current();
			$this->iterator->next();
			return $return_value;
		}
		else
		{
			return FALSE;
		}
	} // end iterate() function

	public function parse()
	{
		$structure_data = &$this->section;

		if(isset($structure_data->data->subtitle))
		{
			$this->logger->message('Parsing Structure ' . $structure_data->data->title . ' - ' . $structure_data->data->subtitle, 5);
			$structure = $this->parse_structure($structure_data);

			foreach($structure_data->data->xpath('./section') as $section)
			{
				$this->logger->message('Parsing Section ' . $section->title . ' - ' . $section->subtitle, 4);
				$section_clean = $this->parse_section($section, $structure);
				$this->store_section($section_clean);
			}
		}

	}

	public function parse_structure(&$structure_data)
	{

		$structure = new stdClass();

		list($structure_data, $structure) = $this->pre_parse_structure($structure_data, $structure);

		if(isset($structure_data->data->subtitle))
		{
			$structure->name = $this->clean_title($structure_data->data->subtitle);
		}

		preg_match($this->structure_regex, $structure_data->data->title, $identifier_parts);

		if(isset($identifier_parts['number']) && isset($identifier_parts['type']))
		{
			$structure->identifier = $identifier_parts['number'];
			$structure->label = $identifier_parts['type'];

			$structure->order_by = $this->get_structure_order_by($structure_data, $structure);
		}
		else {
			$this->logger->message('Could not get structure info from "' . (string) $structure_data->data->title .'"', 9);
			return FALSE;
		}

		$structure->depth = count($structure_data->data->breadcrumbs->crumb);

		list($structure_data, $structure) = $this->post_parse_structure($structure_data, $structure);

		if(isset($structure_data->object->parent) && isset($structure_data->object->parent->id))
		{
			$structure->parent_id = $structure_data->object->parent->id;
		}

		$structure_data->object->id = $structure->id = $this->create_structure($structure);

		return $structure;
	}

	public function pre_parse_structure(&$structure_data, &$structure)
	{
		return array($structure_data, $structure);
	}

	public function post_parse_structure(&$structure_data, &$structure)
	{
		return array($structure_data, $structure);
	}

	public function get_structure_order_by($structure_data, $structure)
	{
		return $this->order_count++;
	}

	public function parse_section($section_xml, $structure)
	{
		$section = new StdClass();
		$section->structure_id = $structure->id;

		$section = $this->pre_parse_section($section, $section_xml, $structure);

		$title = (string) $section_xml->title;

		// The title actually only has the identifier.
		if(strlen($title) < 1)
		{
			$this->logger->message('No section title.', 10);
			return FALSE;
		}

		preg_match($this->section_regex, $title, $title_matches);

		if(isset($title_matches['number']))
		{
			$section->section_number = $title_matches['number'];
			$section->catch_line = $section_xml->subtitle;
			if(!isset($section_xml->subtitle))
			{
				$this->logger->message('Missing subtitle "' . $section_xml->title . '"', 10);
			}
		}
		else
		{
			$this->logger->message('Cannot get identifier from section title "' .
				$title . '"', 10);
			return FALSE;
		}

		// Container to hold discrete text sections.
		$section->section = array();

		$section = $this->recurse_text($section, $section_xml);

		// Container to hold entire text.
		$section->text = $section_xml->asXML();


		$section = $this->post_parse_section($section, $section_xml, $structure);

		// var_dump($section->text);

		return $section;

	}

	/**
	 * Our text is nested, so we have to dig it out while
	 * preserving the hierarchy.
	 */
	public function recurse_text($section, $text, $prefix_hierarchy = array())
	{

		if($text->content) {

			$text_obj = new StdClass();

			$text_obj->text = $this->clean_text($text->content->asXML());

			$text_obj->prefix_hierarchy = $prefix_hierarchy;

			// If we have a section heading, let's get that.
			if($text->getName() === 'listitem' && $text->incr)
			{
				$text_obj->identifier = $this->clean_subtitle($text->incr);
			}
			elseif($text->getName() === 'para' && $text->ital)
			{
				$text_obj->identifier = $this->clean_subtitle($text->ital);
			}

			if(isset($text_obj->identifier))
			{
				$text_obj->prefix_hierarchy[] = $text_obj->identifier;
			}

			$section->section[] = $text_obj;
		}

		foreach($text->xpath('./listitem|para') as $paragraph)
		{
			if(isset($text_obj->identifier))
			{
				$prefix_hierarchy[] = $text_obj->identifier;
			}

			$this->recurse_text($section, $paragraph, $prefix_hierarchy);

			if(isset($text_obj->identifier))
			{
				array_pop($prefix_hierarchy);
			}
		}

		return $section;
	}

	public function pre_parse_section(&$section, $section_xml, $structure)
	{
		return $section;
	}

	public function post_parse_section(&$section, $section_xml, $structure)
	{
		return $section;
	}

	public function clean_title($raw_text)
	{
		// Make sure we have a string, not an XML node.
		$text = (string) $raw_text->asXML();

		// It looks like "xpp qa" is used for linebreaks.
		$text = preg_replace('/\<\?xpp.*?\?\>/', ' ', $text);

		// Strip any remaining junk
		$text = trim(strip_tags($text));

		return $text;
	}

	// For most cases, this is the same as clean_title.
	public function clean_subtitle($raw_text)
	{
		// Make sure we have a string, not an XML node.
		$text = (string) $raw_text->asXML();

		// It looks like "xpp qa" is used for linebreaks.
		$text = preg_replace('/\<\?xpp.*?\?\>/', ' ', $text);

		// Strip any remaining junk
		$text = strip_tags($text);
		$text = trim($text);
		$text = trim($text, '().');

		return $text;
	}

	public function clean_text($content_string)
	{
		// Unset the levels we recurse over.  These may be nested, so remove
		// the closing tags an extra time.
		$content_string = preg_replace('/<listitem.*?<\/listitem>/sm', '',
			$content_string);
		$content_string = str_replace('</listitem>', '', $content_string);

		$content_string = preg_replace('/<para.*?<\/para>/sm', '',
			$content_string);
		$content_string = str_replace('</para>', '', $content_string);

		// Remove incr tags, as we already have those as identifiers.
		$content_string = preg_replace('/<incr.*?<\/incr>/sm', '',
			$content_string);

		// We turn italics into emphasis tags.
		$content_string = preg_replace('/<ital.*?>(.*?)<\/ital>/sm', '<em>$1</em>',
			$content_string);

		// Remove content tags.
		$content_string = preg_replace('/<content.*?>(.*)<\/content>/sm', '$1',
			$content_string);

		// Clean up.
		$content_string = trim($content_string);

		// Debugging.
		// var_dump('Content', $content_string);

		return $content_string;

	}

	public function store()
	{
		// Don't do anything, we're already done.
	}


	/**
	 * Accept the raw content of a section of code and normalize it.
	 */
	public function old_parse()
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

		$this->move_old_permalinks();
		$this->build_permalink_subsections();

	}

	/**
	 * Remove all old permalinks
	 */
	// TODO: eventually, we'll want to keep these and have multiple versions.
	// See issues #314 #362 #363
	public function move_old_permalinks()
	{

		$sql = 'DELETE FROM permalinks';
		$statement = $this->db->prepare($sql);

		$result = $statement->execute();
		if ($result === FALSE)
		{
			echo '<p>Query failed: '.$sql.'</p>';
			return;
		}

	}

	/**
	 * Recurse through all subsections to build permalink data.
	 */
	public function build_permalink_subsections($parent_id = null)
	{

		$structure_sql = '	SELECT structure_unified.*,
							editions.current AS current_edition,
							editions.slug AS edition_slug
							FROM structure
							LEFT JOIN structure_unified
								ON structure.id = structure_unified.s1_id
							LEFT JOIN editions
								ON structure.edition_id = editions.id';

		/*
		 * We use prepared statements for efficiency.  As a result,
		 * we need to keep an array of our arguments rather than
		 * hardcoding them in the SQL.
		 */
		$structure_args = array();

		if (isset($parent_id))
		{
			$structure_sql .= ' WHERE parent_id = :parent_id';
			$structure_args[':parent_id'] = $parent_id;
		}
		else
		{
			$structure_sql .= ' WHERE parent_id IS NULL';
		}

		$structure_statement = $this->db->prepare($structure_sql, array(PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY));
		$structure_result = $structure_statement->execute($structure_args);

		if ($structure_result === FALSE)
		{
			echo '<p>' . $structure_sql . '</p>';
			echo '<p>' . $structure_result->getMessage() . '</p>';
			return;
		}

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
			$token = implode('/', $identifier_parts);

			if ($item['current_edition'])
			{
				$url = '/' . $token . '/';
			}
			else
			{
				$url = '/' . $item['edition_slug'] . '/' . $token .'/';
			}

			/*
			 * Insert the structure
			 */
			$insert_sql = 'INSERT INTO permalinks SET
				object_type = :object_type,
				relational_id = :relational_id,
				identifier = :identifier,
				token = :token,
				url = :url';
			$insert_statement = $this->db->prepare($insert_sql);
			$insert_data = array(
				':object_type' => 'structure',
				':relational_id' => $item['s1_id'],
				':identifier' => $item['s1_identifier'],
				':token' => $token,
				':url' => $url,
			);


			$insert_result = $insert_statement->execute($insert_data);
			if ($insert_result === FALSE)
			{
				echo '<p>'.$sql.'</p>';
				echo '<p>'.$structure_result->getMessage().'</p>';
				return;
			}

			/*
			 * Now we can use our data to build the child law identifiers
			 */
			if (INCLUDES_REPEALED !== TRUE)
			{
				$laws_sql = '	SELECT id, structure_id, section AS section_number, catch_line
								FROM laws
								WHERE structure_id = :s_id
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
								ORDER BY order_by, section';
			}
			$laws_statement = $this->db->prepare($laws_sql, array(PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY));
			$laws_result = $laws_statement->execute( array( ':s_id' => $item['s1_id'] ) );

			if ($structure_result === FALSE)
			{
				echo '<p>'.$laws_sql.'</p>';
				echo '<p>'.$laws_result->getMessage().'</p>';
				return;
			}

			while($law = $laws_statement->fetch(PDO::FETCH_ASSOC))
			{
				if(defined('LAW_LONG_URLS') && LAW_LONG_URLS === TRUE)
				{
					$law_token = $token . '/' . $law['section_number'];
					$law_url = $url . $law['section_number'] . '/';
				}
				else
				{
					$law_token = $law['section_number'];

					if ($item['current_edition'])
					{
						$law_url = '/' . $law['section_number'] . '/';
					}
					else
					{
						$law_url = '/' . $item['edition_slug'] . '/' . $law['section_number'] . '/';
					}
				}
				/*
				 * Insert the structure
				 */
				$insert_sql =  'INSERT INTO permalinks SET
								object_type = :object_type,
								relational_id = :relational_id,
								identifier = :identifier,
								token = :token,
								url = :url';
				$insert_statement = $this->db->prepare($insert_sql);
				$insert_data = array(
					':object_type' => 'law',
					':relational_id' => $law['id'],
					':identifier' => $law['section_number'],
					':token' => $law_token,
					':url' => $law_url,
				);

				$insert_result = $insert_statement->execute($insert_data);

				if ($insert_result === FALSE)
				{
					echo '<p>'.$insert_sql.'</p>';
					echo '<p>'.$insert_result->getMessage().'</p>';
					return;
				}
			}

			$this->build_permalink_subsections($item['s1_id']);

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
	 * Take an object containing the normalized code data and store it.
	 */
	public function store_section($code)
	{
		if (!isset($code))
		{
			die('No data provided.');
		}

		/*
		 * This first section creates the record for the law, but doesn't do anything with the
		 * content of it just yet.
		 */

		$query['structure_id'] = $code->structure_id;

		/*
		 * Build up an array of field names and values, using the names of the database columns as
		 * the key names.
		 */
		$query['catch_line'] = $code->catch_line;
		$query['section'] = $code->section_number;
		$query['text'] = $code->text;
		if (!empty($code->order_by))
		{
			$query['order_by'] = $code->order_by;
		}
		if (isset($code->history))
		{
			$query['history'] = $code->history;
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
				'logger' => $this->logger,
				'edition_id' => $this->edition_id,
				'structure_labels' => $this->structure_labels
			)
		);
		$references->text = $code->text;
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
		if (isset($code->metadata))
		{

			/*
			 * Step through every metadata field and add it.
			 */
			$sql = 'INSERT INTO laws_meta
					SET law_id = :law_id,
					meta_key = :meta_key,
					meta_value = :meta_value';
			$statement = $this->db->prepare($sql);

			foreach ($code->metadata as $key => $value)
			{
				$sql_args = array(
					':law_id' => $law_id,
					':meta_key' => $key,
					':meta_value' => $value
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
		if (isset($code->tags))
		{
			$sql = 'INSERT INTO tags
					SET law_id = :law_id,
					section_number = :section_number,
					text = :tag';
			$statement = $this->db->prepare($sql);

			foreach ($code->tags as $tag)
			{
				$sql_args = array(
					':law_id' => $law_id,
					':section_number' => $code->section_number,
					':tag' => $tag
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
		foreach ($code->section as $section)
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
					date_created=now()';
			$sql_args = array(
				':law_id' => $law_id,
				':sequence' => $i,
				':type' => $section->type
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
							date_created=now()';
					$sql_args = array(
						':text_id' => $text_id,
						':identifier' => $prefix,
						':sequence' => $j
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
				'logger' => $this->logger,
				'edition_id' => $this->edition_id,
				'structure_labels' => $this->structure_labels
			)
		);

		/*
		 * Pass this section of text to $dictionary.
		 */
		$dictionary->text = $code->text;

		/*
		 * Get a normalized listing of definitions.
		 */
		$definitions = $dictionary->extract_definitions();

		/*
		 * Check to see if this section or its containing structural unit were specified in the
		 * config file as a container for global definitions. If it was, then we override the
		 * presumed scope and provide a global scope.
		 */
		$ancestry = array();
		if (isset($code->structure))
		{
			foreach ($code->structure as $struct)
			{
				$ancestry[] = $struct->identifier;
			}
		}
		$ancestry = implode(',', $ancestry);
		$ancestry_section = $ancestry . ','.$code->section_number;
		if 	(
				(GLOBAL_DEFINITIONS === $ancestry)
				||
				(GLOBAL_DEFINITIONS === $ancestry_section)
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
			$dictionary->structure_id = $code->structure_id;

			/*
			 * If the scope of this definition isn't section-specific, and isn't global, then
			 * find the ID of the structural unit that is the limit of its scope.
			 */
			if ( ($dictionary->scope != 'section') && ($dictionary->scope != 'global') )
			{
				$find_scope = new Parser(
					array(
						'db' => $this->db,
						'logger' => $this->logger,
						'edition_id' => $this->edition_id,
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
			$structure = array_reverse($this->structure_labels);
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
	public function structure_exists($structure)
	{

		if (!isset($structure->identifier))
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
			':identifier' => $structure->identifier,
			':edition_id' => $structure->edition_id
		);

		/*
		 * If a parent ID is present (that is, if this structural unit isn't a top-level unit), then
		 * include that in our query.
		 */
		if ( !empty($structure->parent_id) )
		{
			$sql .= ' AND parent_id = :parent_id';
			$sql_args[':parent_id'] = $structure->parent_id;
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

		$found_structure = $statement->fetch(PDO::FETCH_OBJ);
		return $found_structure->id;
	}


	public function create_structure(&$structure)
	{
		if(!isset($structure->edition_id))
		{
			$structure->edition_id = $this->edition_id;
		}
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
				( empty($structure->identifier) && (strlen($structure->identifier) === 0) )
				||
				( empty($structure->label) )
			)
		{
			$this->logger->message('Can\'t create structure "' . $structure->name . '" "' .
				$structure->identifier . '" "' . $structure->label . '"', 5);
			return FALSE;
		}

		/*
		 * Begin by seeing if this structural unit already exists. If it does, return its ID.
		 */
		$structure_id = $this->structure_exists($structure);
		if ($structure_id !== FALSE)
		{
			$this->logger->message('Structure_exists "' . $structure->name . '"', 1);

			$structure->id = $structure_id;
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
			':identifier' => $structure->identifier
		);
		if (!empty($structure->name))
		{
			$sql .= ', name = :name';
			$sql_args[':name'] = $structure->name;
		}
		$sql .= ', label = :label, edition_id = :edition_id';
		$sql .= ', depth = :depth, order_by = :order_by';
		$sql .= ', date_created=now()';
		$sql_args[':label'] = $structure->label;
		$sql_args[':edition_id'] = $structure->edition_id;
		$sql_args[':depth'] = $structure->level;
		$sql_args[':order_by'] = $structure->order_by;
		if (isset($structure->parent_id))
		{
			$sql .= ', parent_id = :parent_id';
			$sql_args[':parent_id'] = $structure->parent_id;

		}
		if(isset($structure->metadata))
		{
			$sql .= ', metadata = :metadata';
			$sql_args[':metadata'] = serialize($structure->metadata);
		}

		$this->logger->message('Structure created: "' . $structure->name . '"', 2);

		$statement = $this->db->prepare($sql);
		$statement->execute($sql_args);

		$structure->id = $this->db->lastInsertID();

		return $structure->id;
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
	function extract_definitions()
	{

		if (!isset($this->text))
		{
			return FALSE;
		}

		/*
		 * The candidate phrases that indicate that the scope of one or more definitions are about
		 * to be provided. Some phrases are left-padded with a space if they would never occur
		 * without being preceded by a space; this is to prevent over-broad matches.
		 */
		$scope_indicators = array(	' are used in this ',
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
		$linking_phrases = array(	' mean ',
									' means ',
									' shall include ',
									' includes ',
									' has the same meaning as ',
									' shall be construed ',
									' shall also be construed to mean ',
								);

		/* Measure whether there are more straight quotes or directional quotes in this passage
		 * of text, to determine which type are used in these definitions. We double the count of
		 * directional quotes since we're only counting one of the two directions.
		 */
		if ( substr_count($this->text, '"') > (substr_count($this->text, '”') * 2) )
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
		if (strpos($this->text, '<p>') !== FALSE)
		{
			$paragraphs = explode('<p>', $this->text);
		}
		else
		{
			$this->text = str_replace("\n", "\r", $this->text);
			$paragraphs = explode("\r", $this->text);
		}

		/*
		 * Create the empty array that we'll build up with the definitions found in this section.
		 */
		$definitions = array();

		/*
		 * Step through each paragraph and determine which contain definitions.
		 */
		foreach ($paragraphs as &$paragraph)
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
			if (reset($paragraphs) == $paragraph)
			{

				/*
				 * Gather up a list of structural labels and determine the length of the longest
				 * one, which we'll use to narrow the scope of our search for the use of structural
				 * labels within the text.
				 */
				$structure_labels = $this->structure_labels;

				usort($structure_labels, 'sort_by_length');
				$longest_label = strlen(current($structure_labels));

				/*
				 * Iterate through every scope indicator.
				 */
				foreach ($scope_indicators as $scope_indicator)
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
				foreach ($linking_phrases as $linking_phrase)
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
				structure_id, date_created)
				VALUES (:law_id, :term, :definition, :scope, :scope_specificity,
				:structure_id, now())';
		$statement = $this->db->prepare($sql);

		foreach ($this->terms as $term => $definition)
		{

			$sql_args = array(
				':law_id' => $this->law_id,
				':term' => $term,
				':definition' => $definition,
				':scope' => $this->scope,
				':scope_specificity' => $this->scope_specificity,
				':structure_id' => $this->structure_id
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
				(law_id, target_section_number, mentions, date_created)
				VALUES (:law_id, :section_number, :mentions, now())
				ON DUPLICATE KEY UPDATE mentions=mentions';
				$statement = $this->db->prepare($sql);
		$i=0;
		foreach ($this->sections as $section => $mentions)
		{
			$sql_args = array(
				':law_id' => $this->section_id,
				':section_number' => $section,
				':mentions' => $mentions
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

/**
 * A recursive iterator to loop over our data structure from XML.
 */
class RecursiveDataStructure implements RecursiveIterator
{
	public $depth;
	public $data;
	public $index;

	// Glue on some data structures.
	public $parent;
	public $parent_data;
	public $parent_id;
	public $id;

	/* Methods */
	public function __construct($data, $depth = 1)
	{
		$this->data = $data;
		$this->depth = $depth;
		$this->index = 0;
	}

	public function getChildren()
	{
		$child_element = './level' . ($this->depth + 1);
		$current = $this->data[$this->index];
		$children = new RecursiveDataStructure($current->xpath($child_element), $this->depth+1);
		$children->parent =& $this;
		return $children;
	}

	public function hasChildren()
	{
		$child_element = './level' . ($this->depth + 1);
		$current = $this->data[$this->index];
		if(count($current->xpath($child_element)))
		{
			return TRUE;
		}
		else {
			return FALSE;
		}
	}

	/* Inherited methods */
	public function current()
	{
		$return_value = new stdClass();
		$return_value->data = $this->data[$this->index];
		$return_value->object =& $this;

		return $return_value;
	}

	public function key()
	{
		return $this->index;
	}

	public function next()
	{
		$this->index++;
	}
	public function rewind()
	{
		$this->index = 0;
	}
	public function valid()
	{
		if(isset($this->data[$this->index]))
		{
			return TRUE;
		}

		return FALSE;
	}
}

