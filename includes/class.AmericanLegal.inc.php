<?php

/**
 * Library for importing XML formatted by American Legal.
 *
 * PHP version 5
 *
 * @license		http://www.gnu.org/licenses/gpl.html GPL 3
 * @version		0.9
 * @link		http://www.statedecoded.com/
 * @since		0.3
 *
 * This library is Abstract - meaning you must derive it to use it!
 *
 * Example usage (to replace State-sample):
********************************************************************
// class.MyCity.inc.php

 <?php

require 'class.AmericanLegal.inc.php';

// All we need is a derivative of both State and Parser.
class State extends AmericanLegalState {}

// We should probably list the images to ignore, though!
class Parser extends AmericanLegalParser
{
	public $image_blacklist = array(
		'seal.png',
		'seal.jpg'
	);
}

// class.MyCity.inc.php
*******************************************************************/

/**
 * This class may be populated with custom functions.
 */
abstract class AmericanLegalState
{

}


/**
 * The parser for importing legal codes. This is fully functional for importing American Legals's
 * usual XML format <https://github.com/statedecoded/statedecoded/wiki/XML-Format-for-Parser>,
 * and serves as a guide for those who want to parse an alternate format.
 */
abstract class AmericanLegalParser
{

	public $file = 0;
	public $directory;
	public $files = array();

	public $db;
	public $logger;
	public $permalink_obj;

	public $edition_id;
	public $previous_edition_id;
	public $structure_labels;

	public $section_count = 1;

	public $structures = array();

	/*
	 * Regexes.
	 * These will need to be customized for your purposes.
	 */
	//                            | type of section                                  | section number                    (opt ' - section number')        |       | - or : | catch line
	public $section_regex = '/^\[?((?P<type>§|SEC(TION|S\.|\.)|APPENDIX|ARTICLE)\s+)?(?P<number>[0-9A-Z]+[0-9A-Za-z_\.\-]*(.?\s-\s[0-9]+[0-9A-Za-z\.\-]*)?)\.?\s*(?:-|:*)?\s*(?P<catch_line>.*?)\.?\]?$/i';

	public $structure_regex = '/^(?P<type>SECS\.|APPENDIX|CHAPTER|ARTICLE|TITLE|SUBCODE|SUBCHAPTER|SUBSECTION)\s+(?P<number>[A-Za-z0-9\-\.]+)(?:[:\. -]+)(?P<name>.*?)$/i';

	public $appendix_regex = '/^APPENDI(CES|X):\s+(?P<name>.*?)$/i';

	/*
	 * Xpaths.
	 */
	public $structure_xpath = "./LEVEL[not(@style-name='Section')]";
	public $structure_heading_xpath = "./RECORD/HEADING";
	public $section_xpath = "./LEVEL[@style-name='Section']";

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

	/*
	 * Files to ignore.
	 */
	public $ignore_files = array(
		'0-0-0-1.xml',
		'0-0-0-2.xml'
	);

	/*
	 * Unfortunately, there are some images that we cannot use, for a variety of reasons.
	 * Most notably are city seals - most localities have laws preventing their use by
	 * anyone other than the city.  This is going to be locality-specific, so put them here.
	 * If you need more complex rules, override check_image()
	 */
	public $image_blacklist = array('ALP Icon', 'SFSeal');

	/*
	 * Images to store.
	 */
	public $images = array();

	/*
	 * Count the structures and appendices statically
	 * so this will persist across instances.
	 */
	public static $appendix_count = 1;

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
			$fileparts = explode('/', $filename);
			$file = end($fileparts);

			/*
			 * We only care about xml files.
			 */
			$extension = substr($filename, strrpos($filename, '.')+1);

			/*
			 * Increment our placeholder counter.
			 */
			$this->file++;

			if($extension == 'xml' && !in_array($file, $this->ignore_files))
			{
				$this->import_xml($filename);

				/*
				 * If we have a valid file.
				 */
				if(@isset($this->chapter->REFERENCE->TITLE)){
					/*
					 * Send this object back, out of the iterator.
					 */

					$this->logger->message('Importing "' . $filename . '"', 3);
					return $this->chapter;
				}
				else {
					$this->logger->message('No sections found in "' . $filename . '"', 3);
					continue;
				}
			}
			else
			{
				$this->logger->message('Ignoring "' . $filename . '"', 3);
				continue;
			}
		}

	} // end iterate() function


	/**
	 * Convert the XML into an object.
	 */
	public function import_xml($filename)
	{
		$this->logger->message('Importing '.$filename, 5);
		$xml = trim(file_get_contents($filename));
		if(strlen($xml) == 0)
		{
			return null;
		}

		try
		{
			$this->chapter = new SimpleXMLElement($xml);
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
			$this->chapter = new SimpleXMLElement($xml);
		}

		/*
		 * Send this object back, out of the iterator.
		 */
		return $this->chapter;
	}

	/**
	 * Accept the raw content of a section of code and normalize it.
	 */
	public function parse()
	{
		unset($this->structures);
		$this->structures = array();

		/*
		 * If a section of code hasn't been passed to this, then it's of no use.
		 */
		if (!isset($this->chapter))
		{
			return FALSE;
		}

		/*
		 * The first child LEVEL we encounter is actually the table of contents, so we skip it.
		 */
		$this->pre_parse_chapter($this->chapter);

		/*
		 * The real chapter starts at the first level.
		 */
		$chapter = $this->chapter->LEVEL;

		/*
		 * There are multiple sections per file.
		 */
		$this->sections = array();
		$this->section_count = 1;

		$this->parse_recurse($chapter);
	}

	/**
	 * In most cases, there will be a table of contents that we want to drop.
	 */

	public function pre_parse_chapter(&$chapter)
	{}

	public function parse_recurse($levels)
	{
		$this->logger->message('parse_recurse', 1);

		if(is_array($levels))
		{
			foreach($levels as $level) {
				$this->parse_recurse($level);
			}
		}
		else {
			$level = $levels;

			$title = '';
			if(isset($level) && isset($level->RECORD) && isset($level->RECORD->HEADING))
			{
				$title = (string) $level->RECORD->HEADING;
			}

			/*
			 * Check to see if we have another layer of nesting
			 */
			if(isset($level->LEVEL))
			{

				if($level->LEVEL[0]->xpath('./RECORD/PARA[@style-name-escaped="Chapter-Analysis"]')) {
					$this->logger->message('Skipping table of contents', 2);
					unset($level->LEVEL[0]);
				}

				/*
				 * If we have one level deeper, this is a section.
				 */
				if($level->xpath('./LEVEL[@style-name-escaped="Normal-Level"]')
					&& $level->xpath('./RECORD/HEADING')
					&& !$level->xpath('./LEVEL/LEVEL'))
				{
					$this->logger->message('SECTION', 2);

					$new_section = $this->parse_section($level, $this->structures);

					if($new_section)
					{
						$this->sections[] = $new_section;
					}
					else {
						/*
						 * See if maybe we have a structure after all.
						 */
						// TODO
					}
				}

				/*
				 * If we have two levels deeper, this is a structure.
				 */
				else
				{
					$structure = FALSE;
					$title = (string) $level->RECORD->HEADING;

					$this->logger->message('STRUCTURE "' . $title . '"', 2);

					// If we have a structure heading, add it to the structures.
					if(count($level->xpath($this->structure_heading_xpath))) {
						$structure = $this->parse_structure( $level );

						if($structure) {
							$this->logger->message('Descending : ' . $structure->name, 2);

							$previous_structure = end($this->structures);

							if($previous_structure)
							{
								$structure->parent_id = $previous_structure->id;
							}

							$this->create_structure($structure);

							$this->structures[] = $structure;

						}
					}
					foreach($level->LEVEL as $sublevel)
					{
						// But recurse, either way.
						$this->parse_recurse($sublevel);
					}

					// If we had a structure heading, pop it from the structures.
					if($structure) {
						$this->logger->message('Ascending', 2);

						array_pop($this->structures);
					}
				}
			}
			/*
			 * If we have no children, somehow we've gone too far!
			 */
			else
			{
				$this->logger->message('Empty', 1);
			}
		}

		$this->logger->message('Exit parse_recurse', 1);
	}

	public function parse_structure($level)
	{
		$structure = $this->pre_parse_structure($level);

		if($structure)
		{
			/*
			 * Set the level.
			 */
			$structure->level = count($this->structures) + 1;
			$structure->edition_id = $this->edition_id;

			if(!isset($structure->identifier))
			{
				$this->logger->message('No identifier, so creating one for "'. $structure->name . '"', 3);

				$structure->identifier = $this->clean_identifier(preg_replace('/[^a-zA-Z0-9- ]/m', '', $structure->name));

				if(strlen($structure->identifier) > 16)
				{
					$this->logger->message('Identifier is longer than 16 characters and will be truncated!', 3);
				}

				if(strtolower($structure->label) === 'appendix' &&
					strtolower(substr($structure->identifier, 0, 8)) !== 'appendix')
				{
					$this->logger->message('Overriding Appendix', 2);

					$structure->identifier = 'Appendix ' . $this->clean_identifier($structure->identifier);
				}
			}

			if(!isset($structure->order_by))
			{
				$structure->order_by = $this->get_structure_order_by($structure);
			}

			/*
			 * Check to see if this structure has text of its own.
			 */
			if($paragraphs = $level->xpath('./LEVEL[@style-name="Normal Level"]/RECORD'))
			{
				foreach($paragraphs as $paragraph)
				{
					$attributes = $paragraph->PARA->attributes();

					$type = '';

					if(isset($attributes['style-name']))
					{
						$type = (string) $attributes['style-name'];
					}

					switch($type)
					{
						case 'History' :
						case 'Section-Deleted' :
							$structure->metadata->history .= $this->clean_text($paragraph->PARA->asXML());
							break;

						case 'EdNote' :
							$structure->metadata->notes .= $this->clean_text($paragraph->PARA->asXML());
							break;

						default :
							$table_children = $paragraph->PARA->xpath('./TABLE|SCROLL_TABLE');

							$para_text = $paragraph->PARA->asXML();

							if(!isset($structure->metadata->text))
							{
								$structure->metadata->text = '';
							}

							// Remove tables of contents.
							if($table_children && count($table_children))
							{
								$this->logger->message('Has tables.', 1);

								foreach($table_children as $child)
								{
									$para_text = str_replace($child->asXML(), '', $para_text);
								}
							}

							$structure->metadata->text .= $this->clean_text($para_text);

							break;
					}
				}
			}

		}

		$this->logger->message('Structure Data: ' . print_r($structure, TRUE), 1);

		$structure = $this->post_parse_structure($level, $structure);

		return $structure;
	}

	/**
	 * We may want to do custom handling based on any number of
	 * different aspect of this element. These next two methods
	 * open up for extension.
	 */
	public function pre_parse_structure($level)
	{
		/*
		 * The minimum structure that must be yielded from this function:
		 *
		 * $structure = new stdClass();
		 * $structure->name = 'My Structure';
		 * $structure->identifier = 'MyStruct';
		 * $structure->label = 'structure';
		 * return $structure;
		 */

		$structure_name = $this->clean_title((string) $level->RECORD->HEADING);
		$structure = FALSE;

		if(preg_match($this->structure_regex, $structure_name, $chapter_parts))
		{
			$this->logger->message('Structure name: ' . $structure_name, 1);

			$structure = new stdClass();
			$structure->metadata = new stdClass();

			if(isset($chapter_parts['name']) && strlen(trim($chapter_parts['name'])))
			{
				$structure->name = $chapter_parts['name'];
			}
			else
			{
				$structure->name = $structure_name;
			}
			$structure->label = ucwords(strtolower($chapter_parts['type']));

			if(!$structure->label)
			{
				$structure->label = 'Structure';
			}
		}
		elseif(preg_match($this->appendix_regex, $structure_name, $chapter_parts))
		{
			$this->logger->message('Appendix name: ' . $structure_name, 1);

			$structure = new stdClass();
			$structure->name = $chapter_parts['name'];

			$structure->label = 'Appendix';

			self::$appendix_count++;
		}
		else
		{
			$this->logger->message('Failed to match structure title: ' . $structure_name, 3);
		}

		if(isset($chapter_parts['number']))
		{
			if(substr($chapter_parts['number'], -1, 1) == '.')
			{
				$chapter_parts['number'] = substr($chapter_parts['number'], 0, -1);
			}

			$structure->identifier = $this->clean_identifier($chapter_parts['number']); // Put these at the end.
		}

		return $structure;
	}

	public function post_parse_structure($level, $structure)
	{
		return $structure;
	}

	public function parse_section($section, $structures)
	{
		$code = new stdClass();

		if(isset($structures) && count($structures) > 0)
		{
			$structure = end($structures);
			$code->structure_id = $structure->id;
		}
		else
		{
			$this->logger->error('ERROR Section without structure found: '. print_r($section, TRUE), 10);
			return FALSE;
		}

		$section_parts = $this->get_section_parts($section);

		if($section_parts === false)
		{
			$this->logger->message('Invalid section: ' . print_r($code, TRUE), 2);
			return FALSE;
		}

		if(!isset($section_parts['number']) || !isset($section_parts['catch_line']))
		{
			$this->logger->message('Could not get Section info from title, "' . (string) $section->RECORD->HEADING . '"', 5);

			$section_title = trim((string) $section->RECORD->HEADING);

			$code->section_number = $section_title;
			$code->catch_line = $section_title;
		}
		else
		{
			$code->section_number = $section_parts['number'];
			$code->catch_line = $section_parts['catch_line'];
			if(isset($section_parts['order_by']))
			{
				$code->order_by = $section_parts['order_by'];
			}
		}

		$code->section_number = $this->clean_identifier($code->section_number);
		$code->catch_line = $this->clean_identifier($code->catch_line);


		/*
		 * If this is an appendix, use the whole line as the title.
		 */
		if(isset($section_parts['type']) && $section_parts['type'] === 'APPENDIX')
		{
			$code->catch_line = $section_parts[0];
		}
		$code->text = '';
		$code->history = '';
		$code->metadata = array(
			'repealed' => 'n'
		);

		if(!isset($code->order_by))
		{
			$code->order_by = $this->get_section_order_by($code);
		}

		/*
		 * Get the paragraph text from the children RECORDs.
		 */

		$code->section = new stdClass();
		$i = 0;

		foreach($section->LEVEL->RECORD as $paragraph) {

			$attributes = $paragraph->PARA->attributes();

			$type = '';

			if(isset($attributes['style-name']))
			{
				$type = (string) $attributes['style-name'];
			}

			switch($type)
			{
				case 'History' :
					$code->history .= $this->clean_text($paragraph->PARA->asXML());
					break;

				case 'Section-Deleted' :
					$code->catch_line = '[REPEALED]';
					$code->metadata['repealed'] = 'y';
					break;

				case 'EdNote' :
					$code->metadata['notes'] = $this->clean_text($paragraph->PARA->asXML());
					break;

				default :
					$code->section->{$i} = new stdClass();

					$section_text = $this->clean_text($paragraph->PARA->asXML());

					$code->text .= $section_text . "\r\r";
					/*
					 * Get the section identifier if it exists.
					 */

					if(preg_match("/^<p>\s*\((?P<letter>[a-zA-Z0-9]{1,3})\) /", $section_text, $paragraph_id))
					{
						$code->section->{$i}->prefix = $paragraph_id['letter'];
						/*
						 * TODO: !IMPORTANT Deal with hierarchy.  This is just a hack.
						 */
						$code->section->{$i}->prefix_hierarchy = array($paragraph_id['letter']);

						/*
						 * Remove the section letter from the section.
						 */
						$section_text = str_replace($paragraph_id[0], '<p>', $section_text);
					}
					// TODO: Clean up tags in the paragraph.

					$code->section->{$i}->text = $section_text;

					$i++;
			}
		}

		if(isset($code->catch_line) && strlen($code->catch_line))
		{
			$this->section_count++;

			$this->logger->message('Section Data: ' . print_r($code, TRUE), 1);

			return $code;
		}
		else
		{
			$this->logger->message('Invalid section: ' . print_r($code, TRUE), 2);
			return FALSE;
		}
	}

	public function get_section_parts($section)
	{
		/*
		 * Parse the catch line and section number.
		 */
		$section_title = trim((string) $section->RECORD->HEADING);

		$this->logger->message('Title: ' . $section_title, 1);

		preg_match($this->section_regex, $section_title, $section_parts);

		return $section_parts;
	}

	/**
	 * Wrap up the convoluted logic for creating the order_by value.
	 * Feel free to override this, but keep in mind it's a natural sort.
	 */

	public function get_structure_order_by($structure)
	{
		if($structure->label == 'Appendix')
		{
			$order_by = '1' . str_pad(self::$appendix_count, 3, '0', STR_PAD_LEFT);
		}
		else
		{
			$order_by = str_pad($structure->identifier, 4, '0', STR_PAD_LEFT);
		}

		return $order_by;
	}

	public function get_section_order_by($code)
	{
		// Do some wrangling to get an orderable number.
		$order_by = $code->section_number;
		if(substr($order_by, -1, 1) == '.')
		{
			$order_by = substr($order_by, 0, -1);
		}

		$order_by = floatval($order_by);
		$order_by = intval($order_by * 100.0);

		$order_by = str_pad($order_by, 8, '0', STR_PAD_LEFT);

		return $order_by;
	}

	/**
	 * Clean up XML into nice HTML.
	 */
	public function clean_text($xml)
	{
		//$this->logger->message('Before formatting XML: "' . $xml . '"', 1);
		// Remove TABLEFORMAT.
		$xml = preg_replace('/<TABLEFORMAT[^>]*>.*?<\/TABLEFORMAT>/sm', '', $xml);

		// Replace SCROLL_TABLE
		$xml = preg_replace('/<SCROLL_TABLE[^>]*>(.*?)<\/SCROLL_TABLE>/sm', '<table>$1</table>', $xml);

		// Replace ROW with tr.
		$xml = str_replace(array('<ROW>', '</ROW>'), array('<tr>', '</tr>'), $xml);

		// Replace COL with td.
		$xml = str_replace(array('<COL>', '</COL>'), array('<td>', '</td>'), $xml);

		// Replace CELLFORMAT.
		$xml = preg_replace('/<CELLFORMAT[^>]*>(.*?)<\/CELLFORMAT>/sm', '$1', $xml);

		// Replace CELL.
		$xml = preg_replace('/<CELL[^>]*>(.*?)<\/CELL>/sm', '$1', $xml);

		// Replace LINK.
		$xml = preg_replace('/<LINK[^>]*>(.*?)<\/LINK>/sm', '$1', $xml);

		// Replace empty tables.
		$xml = preg_replace('/<TABLE>\s*<\/TABLE>/sm', '', $xml);

		// Replace PARA with P.
		$xml = preg_replace('/<PARA[^>]*>/sm', '<p>', $xml);
		$xml = str_replace('</PARA>', '</p>', $xml);

		$xml = preg_replace('/<PARAFORMAT[^>]*>/sm', '<p>', $xml);
		$xml = str_replace('</PARAFORMAT>', '</p>', $xml);

		// Replace <td><p> with <td>
		$xml = preg_replace('/<td>\s*<p>/sm', '<td>', $xml);
		$xml = preg_replace('/<\/p>\s*<\/td>/sm', '</td>', $xml);

		// At this point, we should have clean tables.
		// In cases where we have two consecutive tables, with the first having only one row,
		// that's probably a table heading and then the table body.
		preg_match_all('/<table>(.*?)<\/table>\s*<table>(.*?)<\/table>/smi', $xml, $tables, PREG_SET_ORDER);
		if($tables && count($tables))
		{
			foreach($tables as $table_pair)
			{
				if(substr_count($table_pair[1], '<tr>') === 1)
				{
					$table_pair[1] = str_replace(
						array('<tbody>', '</tbody>', '<td>', '</td>'),
						array('<thead>', '</thead>', '<th>', '</th>'),
						$table_pair[1]);

					$table_pair[1] = trim($table_pair[1]);
					$table_pair[2] = trim($table_pair[2]);

					$xml = str_replace($table_pair[0], '<table>' . $table_pair[1] . $table_pair[2] . '</table>', $xml);
				}
			}
		}

		// Add some semantic elements.
		$xml = preg_replace('/<table>\s*<tr>\s*<th>/sm', '<table><thead><tr><th>', $xml);
		$xml = preg_replace('/<table>\s*<tr>\s*<td>/sm', '<table><tbody><tr><td>', $xml);
		$xml = preg_replace('/<\/th>\s*<\/tr>/sm', '</th></tr></thead><tbody>', $xml);
		$xml = preg_replace('/<\/tr>\s*<\/table>/sm', '</tr></tbody></table>', $xml);




		// Replace CHARFORMAT.
		$xml = preg_replace('/<CHARFORMAT[^>]*>(.*?)<\/CHARFORMAT>/sm', '$1', $xml);

		// Replace TAB
		// TODO: !IMPORTANT Handle nested paragraphs here.
		$xml = str_replace('<TAB tab-count="1"/>', ' ', $xml);

		// Deal with images
		preg_match_all('/<PICTURE(?P<args>[^>]*?)\/>/', $xml, $images, PREG_SET_ORDER);
		foreach($images as $current_image)
		{
			// Parse the arguments into an array.
			preg_match_all('/(?P<name>[a-zA-Z_-]+)="(?P<value>[^"]*)"/',
				$current_image['args'], $image_attrs, PREG_SET_ORDER);

			$image = array();

			foreach($image_attrs as $image_attr)
			{
				$image[ $image_attr['name'] ] = $image_attr['value'];
			}

			if( $this->check_image($image) )
			{
				$image['filename'] = str_replace('-img', '', $image['id']) . '.jpg';

				$this->images[] = $image;

				$image_url = $this->downloads_url . 'images/' . $image['filename'];
				$image_source = $this->directory . '../IMAGES/' . $image['filename'];
				$image_download = $this->downloads_dir . 'images/' . $image['filename'];

				// All images have been converted to jpg for export, so we should be safe.
				$xml = str_replace($current_image[0],
					'<a href=" ' . $image_url . '" title="click to zoom" class="lightbox"><img src="' . $image_url . '"/></a>',
					$xml);

				if(!copy($image_source, $image_download))
				{
					$message = 'Can\'t copy image from "' . $image_source . '" to "' . $image_download . '"';
					if(ini_get('track_errors'))
					{
						$message .= ' message: "' . $php_errormsg . '"';
					}

					$this->logger->error($message, 10);
				}

			}
			else
			{
				$this->logger->message('Skipping image "' . $current_image[0] . '"', 2);
				$xml = str_replace($current_image[0], '', $xml);

			}

		}

		// Trim.
		$xml = trim($xml);

		//$this->logger->message('After formatting XML: "' . $xml . '"', 1);

		return $xml;
	}

	public function clean_title($text)
	{
		// We often see <LINEBRK/> inside of titles.
		$text = str_replace('<LINEBRK/>', ' ', $text);

		// Sometimes, different parts of the code will have different
		// numbers of spaces in the title.
		$text = preg_replace('/\s+/', ' ', $text);

		// Default cleaning.
		$text = $this->clean_identifier($text);

		return $text;
	}

	public function clean_identifier($text)
	{
		// Trim the text for any spaces or periods.
		return trim($text, ". \t\n\r\0\x0B");
	}

	/**
	 * Check that the image is valid.
	 */
	public function check_image($image)
	{
		foreach($this->image_blacklist as $blacklisted)
		{
			if(strpos($image['name'], $blacklisted) !== FALSE)
			{
				return FALSE;
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

	public function store()
	{
		foreach($this->sections as $code)
		{
			$this->code = $code;
			$this->store_section();
		}
	}

	/**
	 * Take an object containing the normalized code data and store it.
	 */
	public function store_section()
	{
		if (!isset($this->code))
		{
			die('No data provided.');
		}

		/*
		 * This first section creates the record for the law, but doesn't do anything with the
		 * content of it just yet.
		 */

		if(isset($this->code->structure_id))
		{
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
				'structure_labels' => $this->structure_labels,
				'downloads_dir' => $this->downloads_dir,
				'downloads_url' => $this->downloads_url
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
					$this->logger->error('SQL ERROR: ' . $sql . ' ' . print_r($sql_args, TRUE), 10);
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
				'structure_labels' => $this->structure_labels,
				'downloads_dir' => $this->downloads_dir,
				'downloads_url' => $this->downloads_url
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
						'structure_labels' => $this->structure_labels,
						'downloads_dir' => $this->downloads_dir,
						'downloads_url' => $this->downloads_url
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
				AND edition_id = :edition_id
				AND depth = :depth
				AND name = :name';
		$sql_args = array(
			':identifier' => $structure->identifier,
			':edition_id' => $structure->edition_id,
			':depth' => $structure->level,
			':name' => $structure->name
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

		$structure = $statement->fetch(PDO::FETCH_OBJ);
		return $structure->id;
	}


	/**
	 * When provided with a structural unit identifier and type, it creates a record for that
	 * structural unit. Save for top-level structural units (e.g., titles), it should always be
	 * provided with a $parent_id, which is the ID of the parent structural unit. Most structural
	 * units will have a name, but not all.
	 */
	public function create_structure(&$structure)
	{

		if(!isset($structure->edition_id) || empty($structure->edition_id))
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
			return FALSE;
		}

		/*
		 * Begin by seeing if this structural unit already exists. If it does, return its ID.
		 */
		$structure_id = $this->structure_exists($structure);
		if ($structure_id !== FALSE)
		{
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
		$sql .= ', depth = :depth, date_created=now()';
		$sql_args[':label'] = $structure->label;
		$sql_args[':edition_id'] = $structure->edition_id;
		$sql_args[':depth'] = $structure->level;
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

		$statement = $this->db->prepare($sql);
		$result = $statement->execute($sql_args);

		if ($result === FALSE)
		{
			$this->logger->error('SQL ERROR: ' . $sql . ' ' . print_r($sql_args, TRUE), 10);
			return FALSE;
		}

		$structure->id = $this->db->lastInsertID();

		return $structure->id;

	}


	/**
	 * When provided with a structural unit ID and a label, this function will iteratively search
	 * through that structural unit's ancestry until it finds a structural unit with that label.
	 * This is meant for use while identifying definitions, within the store() method, specifically
	 * to set the scope of applicability of a definition.
	 */
	public function find_structure_parent()
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
				$this->logger->error('SQL ERROR: ' . $sql . ' ' . print_r($sql_args, TRUE), 10);
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
	public function extract_definitions($text, $structure_labels)
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
	public function store_definitions()
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


	public function query($sql)
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
	public function extract_references()
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
	public function store_references()
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
	public function extract_history()
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
			$this->logger->error('SQL ERROR: ' . $sql . ' ' . print_r($sql_args, TRUE), 10);
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
