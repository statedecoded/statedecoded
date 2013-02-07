<?php

/**
 * 
 */
class Law
{
	
	/**
	 * Retrieve all of the material relevant to a given law.
	 */
	function get_law()
	{

		// We're going to need access to the database connection throughout this class.
		global $db;
		
		// If neither a section number nor a law ID has been passed to this function, then there's
		// nothing to do.
		if (!isset($this->section_number) && !isset($this->law_id))
		{
			return false;
		}
		
		// Define the level of detail that we want from this method. By default, we return
		// everything that we have for this law. But if any specific 
		if ( !isset($this->config) || ($this->config->get_all == TRUE) )
		{
			$this->config->get_text = TRUE;
			$this->config->get_structure = TRUE;
			$this->config->get_amendment_attempts = TRUE;
			$this->config->get_court_decisions = TRUE;
			$this->config->get_metadata = TRUE;
			$this->config->get_references = TRUE;
			$this->config->get_related_laws = TRUE;
			$this->config->render_html = TRUE;
		}
		
		// Assemble the query that we'll use to get this law.
		$sql = 'SELECT id AS section_id, structure_id, section AS section_number, catch_line,
				history, text AS full_text, repealed
				FROM laws';
		
		// If we're requesting a specific law by ID.
		if (isset($this->law_id))
		{
			// If it's just a single law ID, then just request the one.
			if (!is_array($this->law_id))
			{
				$sql .= ' WHERE id='.$db->escape($this->law_id);
			}
			
			// But if it's an array of law IDs, request all of them.
			elseif (is_array($this->law_id))
			{
				$sql .= ' WHERE (';

				// Step through the list.
				foreach ($this->law_id as $id)
				{
					$sql .= ' id='.$db->escape($id);
					if (end($this->law_id) != $id)
					{
						$sql .= ' OR';
					}
				}
				
				$sql .= ')';
			}
		}
		
		// Else if we're requesting a law by section number, then make sure that we're getting the
		// law from the newest edition of the laws.
		else
		{
			$sql .= ' WHERE section="'.$db->escape($this->section_number).'"
					AND edition_id='.EDITION_ID;
		}
		
		// Execute the query.
		$result =& $db->query($sql);
		
		// If the query fails, or if no results are found, return false -- we can't make a match.
		if ( PEAR::isError($result) || ($result->numRows() < 1) )
		{
			return false;
		}
		
		// Return the result as an object.
		$tmp = $result->fetchRow(MDB2_FETCHMODE_OBJECT);
		
		// Bring this law into the object scope.
		foreach ($tmp as $key => $value)
		{
			$this->$key = $value;
		}
		
		// Change from the y/n MySQL storage type for "repealed" to true/false.
		if ($this->repealed == 'n')
		{
			$this->repealed = FALSE;
		}
		else
		{
			$this->repealed = TRUE;
		}
		
		// Clean up the typography in the full text.
		$this->full_text = wptexturize($this->full_text);
		
		// Now get the text for this law.
		if ($this->config->get_text === TRUE)
		{
			$sql = 'SELECT text, type,
						(SELECT
							GROUP_CONCAT(identifier
							ORDER BY sequence ASC
							SEPARATOR "|")
						FROM text_sections
						WHERE text_id=text.id
						GROUP BY text_id) AS prefixes
					FROM text
					WHERE law_id='.$db->escape($this->section_id).'
					ORDER BY text.sequence ASC';
			
			// Execute the query.
			$result =& $db->query($sql);
			
			// If the query fails, or if no results are found, return false -- we can't make a match.
			if ( PEAR::isError($result) || ($result->numRows() < 1) )
			{
				return false;
			}
			
			// Iterate through all of the sections of text to save to our object.
			$i=0;
			while ($tmp = $result->fetchRow(MDB2_FETCHMODE_OBJECT))
			{
				$tmp->prefixes = explode('|', $tmp->prefixes);
				$tmp->prefix = end($tmp->prefixes);
				$tmp->entire_prefix = implode('', $tmp->prefixes);
				$tmp->prefix_anchor = str_replace(' ', '_', $tmp->entire_prefix);
				$tmp->level = count($tmp->prefixes);
		
				// Pretty it up, converting all straight quotes into directional quotes, double
				// dashes into em dashes, etc.
				$tmp->text = wptexturize($tmp->text);
				
				// Append this section.
				$this->text->$i = $tmp;
				$i++;
			}
		}
		
		// Determine this law's structural position.
		if ($this->config->get_structure == TRUE)
		{
			// Create a new instance of the Structure class.
			$struct = new Structure;
	
			// Our structure ID provides a starting point to identify this law's ancestry.
			$struct->id = $this->structure_id;
			
			// Save the law's ancestry.
			$this->ancestry = $struct->id_ancestry();
			
			// Short of a parser error, there’s no reason why a law should not have an ancestry. In
			// case of this unlikely possibility, just erase the false element.
			if ($this->ancestry === false)
			{
				unset($this->ancestry);
			}
			
			// Get the listing of all other sections in the structural unit that contains this
			// section.
			$this->structure_contents = $struct->list_laws();

			// Figure out what the next and prior sections are (we may have 0-1 of either). Iterate
			// through all of the contents of the chapter.
			for ($i=0; $i<count((array) $this->structure_contents); $i++)
			{
				// When we get to our current section, that's when we get to work.
				if ($this->structure_contents->$i->id == $this->section_id)
				{
					$j = $i-1;
					$k = $i+1;
					if (isset($this->structure_contents->$j))
					{
						$this->previous_section = $this->structure_contents->$j;
					}
					
					if (isset($this->structure_contents->$k))
					{
						$this->next_section = $this->structure_contents->$k;
					}
					break;
				}
			}
		}
		
		// Gather all metadata stored about this law.
		if ($this->config->get_metadata == TRUE)
		{
			$this->metadata = Law::get_metadata();
		}
		
		// If this state has its own State class, then we can potentially use some of its methods.
		if (class_exists('State'))
		{
		
			// Create a new instance of the State() class.
			$state = new State();
			$state->section_id = $this->section_id;
			$state->section_number = $this->section_number;
			
			// Get the amendation attempts for this law and include those (if there are any). But
			// only if we haven't specifically indicated that we don't want it. The idea behind
			// skipping this is that it's calling from Richmond Sunlight, which is reasonable for
			// internal purposes, but it's not sensible for our own API to make a call to another
			// site's API.
			if ($this->config->get_amendment_attempts == TRUE)
			{
				if (method_exists($state, 'get_amendment_attempts'))
				{
					$this->amendment_attempts = $state->get_amendment_attempts();
				}
			}

			// Get the court decisions for this law and include those (if there are any).
			if ($this->config->get_court_decisions == TRUE)
			{
				if (method_exists($state, 'get_court_decisions'))
				{
					$this->court_decisions = $state->get_court_decisions();
				}
			}
		
			// Get the URL for this law on its official state web page.
			if (method_exists($state, 'official_url'))
			{
				$this->official_url = $state->official_url();
			}
			
			// Translate the history of this law into plain English.
			if (method_exists($state, 'translate_history'))
			{
				if (isset($this->metadata->history))
				{
					$state->history = $this->metadata->history;
					$this->history_text = $state->translate_history();
				}
			}
			
		} // end class_exists('State')
		
		// Get the references to this law among other laws and include those (if there are any).
		if ($this->config->get_references == TRUE)
		{
			$this->references = Law::get_references();
		}
		
		if ($this->config->get_related_laws == TRUE)
		{
			$this->related = Law::get_related();
		}

		// Extract every year named in the history.
		preg_match_all('/(18|19|20)([0-9]{2})/', $this->history, $years);
		if (count($years[0]) > 0)
		{
			$i=0;
			foreach ($years[0] as $year)
			{
				$this->amendment_years->$i = $year;
				$i++;
			}
		}
		
		// Pretty up the text for the catch line.
		$this->catch_line = wptexturize($this->catch_line);
		
		// Provide the URL for this section.
		$this->url = 'http://'.$_SERVER['SERVER_NAME'].'/'.$this->section_number.'/';
		
		// Assemble the citations. Amendment years may not always be present (such as with repealed
		// sections, which may lack histories), so only include those if they exist.
		$this->citation->official = 'Va. Code §&nbsp;'.$this->section_number;
		$this->citation->universal = 'VA Code §&nbsp;'.$this->section_number;
		if (is_array($this->amendment_years))
		{
			$this->citation->official .= ' ('.end($this->amendment_years).')';
			$this->citation->universal .= ' ('.end($this->amendment_years).' through Reg Sess)';
		}

		
		if ($this->config->render_html == TRUE)
		{
			$this->html = Law::render();
		}
		
		$law = $this;
		unset($law->config);
		
		// Return the result.
		return $law;
	}
	
	
	/**
	 * Return a listing of every section of the code that refers to a given section.
	 */
	function get_references()
	{
				
		// We're going to need access to the database connection throughout this class.
		global $db;
		
		// If a section number doesn't exist in the scope of this class, then there's nothing to do.
		if (!isset($this->section_id))
		{
			return false;
		}
		
		// Get a listing of IDs, section numbers, and catch lines.
		$sql = 'SELECT DISTINCT laws.id, laws.section AS section_number, laws.catch_line
				FROM laws
				INNER JOIN laws_references
					ON laws.id = laws_references.law_id
				WHERE laws_references.target_law_id =  '.$db->escape($this->section_id).'
				ORDER BY laws.order_by, laws.section ASC';
		
		// Execute the query.
		$result =& $db->query($sql);
		
		// If the query fails, or if no results are found, return false -- no sections refer to
		// this one.
		if ( PEAR::isError($result) || ($result->numRows() < 1) )
		{
			return false;
		}
		
		// Return the result as an enumerated object.
		$references = new stdClass();
		$i = 0;
		while ($reference = $result->fetchRow(MDB2_FETCHMODE_OBJECT))
		{
			$reference->catch_line = stripslashes($reference->catch_line);
			$reference->url = 'http://'.$_SERVER['SERVER_NAME'].'/'.$reference->section_number.'/';
			
			$references->$i = $reference;
			$i++;
		}
		
		return $references;
	}
	
	/**
	 * Record a view of a single law.
	 */
	function record_view()
	{
	
		// We're going to need access to the database connection throughout this class.
		global $db;
		
		// If a section number doesn't exist in the scope of this class, then there's nothing to do.
		if (!isset($this->section_number))
		{
			return false;
		}
		
		// Record the view.
		$sql = 'INSERT DELAYED INTO laws_views
				SET section="'.$this->section_number.'"';
		if (!empty($_SERVER['REMOTE_ADDR']))
		{
			$sql .= ', ip_address=INET_ATON("'.$_SERVER['REMOTE_ADDR'].'")';
		}
		
		// Execute the query.
		$result =& $db->exec($sql);
		
		// If the query fails, return false.
		if (PEAR::isError($result))
		{
			return false;
		}
		
		return true;
	}

	/**
	 * Get all metadata for a single law.
	 */
	function get_metadata()
	{
	
		// We're going to need access to the database connection throughout this class.
		global $db;
		
		// If a section number doesn't exist in the scope of this class, then there's nothing to do.
		if (!isset($this->section_id))
		{
			return false;
		}
		
		// Get a listing of all metadata that belongs to this law.
		$sql = 'SELECT id, meta_key, meta_value
				FROM laws_meta
				WHERE law_id='.$db->escape($this->section_id);
		
		// Execute the query.
		$result =& $db->query($sql);
		
		// If the query fails, or if no results are found, return false -- no sections refer to
		// this one.
		if ( PEAR::isError($result) || ($result->numRows() < 1) )
		{
			return false;
		}
		
		// Return the result as an object.
		$metadata = $result->fetchAll(MDB2_FETCHMODE_OBJECT);
		
		// Create a new object, to which we will port a rotated version of this object.
		$rotated = new stdClass();
		
		// Iterate through the object in order to reorganize it, assigning the meta_key field to the
		// key and the meta_value field to the value.
		foreach($metadata as $field)
		{
			$rotated->{stripslashes($field->meta_key)} = unserialize(stripslashes($field->meta_value));
		}
		
		return $rotated;
	}
	
	/**
	 * Get a collection of the 5 sections most similar to the present law.
	 */
	function get_related()
	{
		
		Solarium_Autoloader::register();

		// Create a client instance.
		$client = new Solarium_Client();
		
		// Get a morelikethis query instance.
		$query = $client->createMoreLikeThis();
		
		// Add a query and morelikethis settings. Note that this search MUST be performed against
		// the "law_location" field. There are two other candidate fields at this writing
		// (law_section and law_code), but neither of those yield correct matches. A search for
		// "law_code:18.2-30" turns up "8.01-130" as the first result, a number that is very similar
		// to 18.2-30, but the result is wildly different -- useless -- related results.
		//
		// Note that we have to escape colons in this query.
		$query->setQuery('law_location:'.str_replace(':', '\:', $this->section_number));
		$query->setMltFields('law_text,tags,law_title');
		$query->setMatchInclude(true);
		$query->setStart(0)->setRows(5);
		
		// Execute the query and return the result.
		$results = $client->select($query);
		
		// Create a new, blank object to store our related sections.
		$related = new StdClass();
		
		// Iterate through the returned documents
		$i=0;
		foreach ($results as $document)
		{
			$related->{$i}->id = $document->id;
			$related->{$i}->catch_line = $document->law_title;
			$related->{$i}->section_number = $document->law_section;
			$related->{$i}->text = $document->law_text;
			$related->{$i}->url = 'http://'.$_SERVER['SERVER_NAME'].'/'.$document->law_section.'/';
			$i++;
		}
		
		return $related;
	}

	/**
	 * When provided with a section number, it indicates whether that section exists. This is
 	 * designed for use when parsing the text of each section, which turns any section numbers into
 	 * links. But it has to verify that they're really section numbers, and not strings that
	 * resemble section numbers, which necessitates a fast, lightweight function.
	 */
	function exists()
	{
		
		// We're going to need access to the database connection throughout this class.
		global $db;
		
		// If neither a section number nor a law ID has been passed to this function, then there's
		// nothing to do.
		if (!isset($this->section_number))
		{
			return false;
		}

		// Trim it down.
		$this->section_number = trim($this->section_number);

		// Query the database for the ID for this section number, retrieving the current version
		// of the law.
		$sql = 'SELECT *
				FROM laws
				WHERE section="'.$db->escape($this->section_number).'"
				AND edition_id='.EDITION_ID;
		
		// Execute the query.
		$result =& $db->query($sql);
		
		// If the query fails, or if no results are found, return false -- we can't make a match.
		if ($result->numRows() < 1)
		{
			return false;
		}
		
		// Otherwise we've gotten a result, so return true.
		return true;

	}
	
	/**
	 * Takes the instant law object and turns it into HTML, with embedded links, anchors, etc.
	 */
	function render()
	{
		// Get the dictionary terms for this chapter.
		$dictionary = new Dictionary();
		$dictionary->structure_id = $this->structure_id;
		$dictionary->section_id = $this->id;
		if ($this->catch_line == 'Definitions.')
		{
			$dictionary->scope = 'global';
		}
		$terms = $dictionary->term_list();
		
		// If we've gotten a list of dictionary terms.
		if ( ($terms !== false) && is_object($terms) )
		{
			// Arrange our terms from longest to shortest. This is to ensure that the most specific
			// terms are defined (e.g. "person of interest") rather than the broadest terms (e.g.
			// "person").
			usort($terms, 'sort_by_length');
			
			// Store a list of the dictionary terms as an array, which is required for
			// preg_replace_callback, the function that we use to insert the definitions.
			$term_pcres = array();
			foreach ($terms as $term)
			{
				
				// Step through each character in this word.
				for ($i=0; $i<strlen($term); $i++)
				{
					// If there are any uppercase characters, then make this PCRE string case sensitive.
					if ( (ord($term{$i}) >= 65) && (ord($term{$i}) <= 90) )
					{
						$term_pcres[] = '/\b'.$term.'(s?)\b(?![^<]*>)/';
						$caps = true;
						break;
					}
				}
				
				// If we have determined that this term does not contain capitalized letters, then
				// create a case-insensitive PCRE string.
				if (!isset($caps))
				{
					$term_pcres[] = '/\b'.$term.'(s?)\b(?![^<]*>)/i';
				}
				
				// Unset our flag -- we don't want to have it set the next time through.
				if (isset($caps))
				{
					unset($caps);
				}
			}
		}
		
		// Instantiate our autolinker, which embeds links.
		$autolinker = new Autolinker;

		// Iterate through every section to make some basic transformations.
		foreach ($this->text as $section)
		{
			
			// Prevent lines from wrapping in the middle of a section identifier.
			$section->text = str_replace('§ ', '§&nbsp;', $section->text);
			
			// Turn every code reference in every paragraph into a link.
			$section->text = preg_replace_callback(SECTION_PCRE, array($autolinker, 'replace_sections'), $section->text);
			
			// Use our dictionary to embed dictionary terms in the form of span titles.
			if (isset($term_pcres))
			{
				$section->text = preg_replace_callback($term_pcres, array($autolinker, 'replace_terms'), $section->text);
			}
		}
		
		// Iterate through each section of text to display it.
		$i=0;
		$num_paragraphs = count((array) $this->text);
		foreach ($this->text as $paragraph)
		{
		
			// Identify the prior and next sections, by storing their prefixes.
			if ($i > 0)
			{
				$paragraph->prior_prefix = $this->text->{$i-1}->entire_prefix;
			}
			if ($i < $num_paragraphs)
			{
				$paragraph->next_prefix = $this->text->{$i+1}->entire_prefix;
			}
		
			// If this paragraph's prefix hierarchy is different than that of the prior prefix, then
			// indicate that this is a new section.
			if ( ($paragraph->entire_prefix != $paragraph->prior_prefix) || !isset($paragraph->prior_prefix) )
			{
				$html .= '
					<section';
				if (!empty($paragraph->prefix_anchor))
				{
					$html .= ' id="'.$paragraph->prefix_anchor.'"';
				}
				
				// If this is a subsection, indent it.
				if ($paragraph->level > 1)
				{
					$html .= ' class="indent-'.($paragraph->level-1);
					$html .= '"';
				}
				$html .= '>';
			}
			
			// Start a paragraph of the appropriate type.
			$html .= '<';
			if ($paragraph->type == 'section')
			{
				$html .= 'p';
			}
			elseif ($paragraph->type == 'table')
			{
				$html .= 'pre class="table"';
			}
			$html .= '>';
			
			// If we've got a section prefix, and it's not the same as the last one, then display
			// it.
			if ($paragraph->entire_prefix != $paragraph->prior_prefix)
			{
				
				$html .= $paragraph->prefix;
				
				// We could use a regular expression to determine if we need to append a period, but
				// that would be slower.
				if ( (substr($paragraph->prefix, -1) != ')') && (substr($paragraph->prefix, -1) != '.') )
				{
					$html .= '.';
				}
				$html .= ' ';
			}
			
			// Display this section of text.
			$html .= $paragraph->text;
			
			// If we've got a section prefix, append a paragraph link to the end of this section.
			if (!empty($paragraph->prefix))
			{
				$html .= ' <a class="section-permalink" href="#'.$paragraph->prefix_anchor.'">¶</a>';
			}
			if ($paragraph->type == 'section')
			{
				$html .= '</p>';
			}
			elseif ($paragraph->type == 'table')
			{
				$html .= '</pre>';
			}
			
			// If our next prefix is different than the current prefix, than terminate this section.
			if	(
					( !isset($paragraph->next_prefix) || ($paragraph->entire_prefix != $paragraph->next_prefix) )
					||
					( ($i+1) === $num_paragraphs)
				)
			{
				$html .= '</section>';
			}
			$i++;
		}
		
		return $html;
		
	} // end render()
	
	
	/**
	 * Takes the instant law object and turns it into a nicely formatted plain text version.
	 * NOTE: I'M NOT SURE THAT $paragraph->depth EXISTS! Verify that's a valid variable to make this
	 * work.
	 */
	function render_plain_text()
	{

		// Iterate through every section to make some basic transformations.
		foreach ($this->text as $section)
		{
			
			// Prevent lines from wrapping in the middle of a section identifier by inserting the
			// Unicode NO-BREAK-SPACE (U+00A0) character.
			$section->text = str_replace('§ ', '§ ', $section->text);
			
		}
		
		// Iterate through each section of text to display it.
		$i=0;
		$num_paragraphs = count((array) $this->text);
		foreach ($this->text as $paragraph)
		{
			
			// Initialize a variable that we'll use to store the text for this subsection.
			$subsection = '';
			
			// If we've got a section prefix, and it's not the same as the last one, then display
			// it.
			if ($paragraph->entire_prefix != $paragraph->prior_prefix)
			{
				
				# Append the prefix for this subsection.
				$subsection .= $paragraph->prefix;
				
				// We could use a regular expression to determine if we need to append a period, but
				// that would be slower.
				if ( (substr($paragraph->prefix, -1) != ')') && (substr($paragraph->prefix, -1) != '.') )
				{
					$subsection .= '.';
				}
				$subsection .= ' ';
			}
			
			// Add the text itself to the subsection.
			$subsection .= $paragraph->text;
			
			// Wrap this text at 80 characters minus two spaces for every nested subsection,
			// breaking up words that exceed the line length.
			$subsection = wordwrap($subsection, (80 - (($paragraph->depth - 1) * 2)), "\n", true);
			
			// Indent applicable subsections by adding blank space to the beginning of each line.
			if ($paragraph->depth > 0)
			{
				$lines = explode("\n", $subsection);
				foreach ($lines as $line)
				{
					$line = str_repeat(' ', ( ($paragraph->depth - 1) * 2 )).$line;
				}
				$subsection = implode("\n", $lines);
			}
			
			// Finish up with a pair of carriage returns.
			$subsection .= "\n\n";
			
			// And, finally, add this subsection to the text of the section.
			$text .= $subsection;
			
			$i++;
		}
		
		return $text;
		
	} // end render_plain_text()

} // end Law

?>