<?php

/**
 * The Law class, for retrieving data about individual laws.
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
 
class Law
{
	
	/**
	 * Retrieve all of the material relevant to a given law.
	 */
	function get_law()
	{

		/*
		 * We're going to need access to the database connection throughout this class.
		 */
		global $db;
		
		/*
		 * If neither a section number nor a law ID has been passed to this function, then there's
		 * nothing to do.
		 */
		if (!isset($this->section_number) && !isset($this->law_id))
		{
			return FALSE;
		}
		
		/*
		 * If we haven't specified which fields that we want, then assume that we want all of them.
		 */
		if (!isset($this->config->get_all))
		{
			$this->config->get_all = TRUE;
		}
		
		/*
		 * Define the level of detail that we want from this method. By default, we return
		 * everything that we have for this law.
		 */
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
		
		/*
		 * Assemble the query that we'll use to get this law.
		 */
		$sql = 'SELECT id AS section_id, structure_id, section AS section_number, catch_line,
				history, text AS full_text
				FROM laws';
		
		/*
		 * If we're requesting a specific law by ID.
		 */
		if (isset($this->law_id))
		{
			/*
			 * If it's just a single law ID, then just request the one.
			 */
			if (!is_array($this->law_id))
			{
				$sql .= ' WHERE id=' . $db->quote($this->law_id);
			}
			
			/*
			 * But if it's an array of law IDs, request all of them.
			 */
			elseif (is_array($this->law_id))
			{
				$sql .= ' WHERE (';

				/*
				 * Step through the list.
				 */
				foreach ($this->law_id as $id)
				{
					$sql .= ' id=' . $db->quote($id);
					if (end($this->law_id) != $id)
					{
						$sql .= ' OR';
					}
				}
				
				$sql .= ')';
			}
		}
		
		/*
		 * Else if we're requesting a law by section number, then make sure that we're getting the
		 * law from the newest edition of the laws.
		 */
		else
		{
			$sql .= ' WHERE section=' . $db->quote($this->section_number) . '
					AND edition_id=' . EDITION_ID;
		}
		
		$result = $db->query($sql);
		
		if ( ($result === FALSE) || ($result->rowCount() == 0) )
		{
			return FALSE;
		}
		
		/*
		 * Return the result as an object.
		 */
		$tmp = $result->fetch(PDO::FETCH_OBJ);
		
		/*
		 * Bring this law into the object scope.
		 */
		foreach ($tmp as $key => $value)
		{
			$this->$key = $value;
		}
		
		/*
		 * Clean up the typography in the full text.
		 */
		$this->full_text = wptexturize($this->full_text);
		
		/*
		 * Now get the text for this law, subsection by subsection.
		 */
		if ($this->config->get_text === TRUE)
		{
			
			/*
			 * When invoking this method in a loop, $this->text can pile up on itself. If the text
			 * property is already set, clear it out.
			 */
			if (isset($this->text))
			{
				unset($this->text);
			}
			
			$sql = 'SELECT id, text, type,
						(SELECT
							GROUP_CONCAT(identifier
							ORDER BY sequence ASC
							SEPARATOR "|")
						FROM text_sections
						WHERE text_id=text.id
						GROUP BY text_id) AS prefixes
					FROM text
					WHERE law_id='.$db->quote($this->section_id).'
					ORDER BY text.sequence ASC';
			
			$result = $db->query($sql);
			
			/*
			 * If the query fails, or if no results are found, return false -- we can't make a
			 * match.
			 */
			if ( ($result === FALSE) || ($result->rowCount() == 0) )
			{
				return FALSE;
			}
			
			/*
			 * Iterate through all of the sections of text to save to our object.
			 */
			$i=0;
			while ($tmp = $result->fetch(PDO::FETCH_OBJ))
			{
			
				$tmp->prefixes = explode('|', $tmp->prefixes);
				$tmp->prefix = end($tmp->prefixes);
				$tmp->entire_prefix = implode('', $tmp->prefixes);
				$tmp->prefix_anchor = str_replace(' ', '_', $tmp->entire_prefix);
				$tmp->level = count($tmp->prefixes);
		
				/*
				 * Pretty it up, converting all straight quotes into directional quotes, double
				 * dashes into em dashes, etc.
				 */
				if ($tmp->type != 'table')
				{
					$tmp->text = wptexturize($tmp->text);
				}
				
				/*
				 * Append this section.
				 */
				$this->text->$i = $tmp;
				$i++;
				
			}
		}
		
		/*
		 * Determine this law's structural position.
		 */
		if ($this->config->get_structure == TRUE)
		{
		
			/*
			 * Create a new instance of the Structure class.
			 */
			$struct = new Structure;
	
			/*
			 * Our structure ID provides a starting point to identify this law's ancestry.
			 */
			$struct->id = $this->structure_id;
			
			/*
			 * Save the law's ancestry.
			 */
			$this->ancestry = $struct->id_ancestry();
			
			/*
			 * Short of a parser error, there’s no reason why a law should not have an ancestry. In
			 * case of this unlikely possibility, just erase the false element.
			 */
			if ($this->ancestry === FALSE)
			{
				unset($this->ancestry);
			}
			
			/*
			 * Get the listing of all other sections in the structural unit that contains this
			 * section.
			 */
			$this->structure_contents = $struct->list_laws();

			/*
			 * Figure out what the next and prior sections are (we may have 0-1 of either). Iterate
			 * through all of the contents of the chapter. (It's possible that there are no next or
			 * prior sections, such as in a single-item structural unit.)
			 */
			if ($this->structure_contents !== FALSE)
			{
				$tmp = count((array) $this->structure_contents);
				for ($i=0; $i<$tmp; $i++)
				{
					/*
					 * When we get to our current section, that's when we get to work.
					 */
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
		}
		
		/*
		 * Gather all metadata stored about this law.
		 */
		if ($this->config->get_metadata == TRUE)
		{
			$this->metadata = Law::get_metadata();
		}

		/*
		 * Extract every year named in the history.
		 */
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
		
		/*
		 * Create a new instance of the State() class.
		 */
		$state = new State();
		$state->section_id = $this->section_id;
		$state->section_number = $this->section_number;
		
		/*
		 * Get the amendment attempts for this law and include those (if there are any). But
		 * only if we have specifically requested this data. That's because, on most installations,
		 * this will be making a call to a third-party service (e.g., Open States), and such a call
		 * is expensive.
		 */
		if ($this->config->get_amendment_attempts == TRUE)
		{
			if (method_exists($state, 'get_amendment_attempts'))
			{
				$this->amendment_attempts = $state->get_amendment_attempts();
			}
		}

		/*
		 * Get the amendment attempts for this law and include those (if there are any). But
		 * only if we have specifically requested this data. That's because, on most installations,
		 * this will be making a call to a third-party service and such a call is expensive.
		 */
		if ($this->config->get_court_decisions == TRUE)
		{
			if (method_exists($state, 'get_court_decisions'))
			{
				$this->court_decisions = $state->get_court_decisions();
			}
		}
	
		/*
		 * Get the URL for this law on its official state web page.
		 */
		if (method_exists($state, 'official_url'))
		{
			$this->official_url = $state->official_url();
		}
		
		/*
		 * Translate the history of this law into plain English.
		 */
		if (method_exists($state, 'translate_history'))
		{
			if (isset($this->metadata->history))
			{
				$state->history = $this->metadata->history;
				$this->history_text = $state->translate_history();
			}
		}
		
		/*
		 * Generate citations for this law.
		 */
		if (method_exists($state, 'citations'))
		{
			$state->section_number = $this->section_number;
			$state->amendment_years = $this->amendment_years;
			$state->citations();
			$this->citation = $state->citation;
		}
		
		/*
		 * Get the references to this law among other laws and include those (if there are any).
		 */
		if ($this->config->get_references == TRUE)
		{
			$this->references = Law::get_references();
		}
		
		/*
		 * Pretty up the text for the catch line.
		 */
		$this->catch_line = wptexturize($this->catch_line);
		
		/*
		 * Provide the URL for this section.
		 */
		$this->url = 'http://'.$_SERVER['SERVER_NAME'].'/'.$this->section_number.'/';
		
		/*
		 * Create metadata in the Dublin Core format.
		 */
		$this->dublin_core = new stdClass();
		$this->dublin_core->Title = $this->catch_line;
		$this->dublin_core->Type = 'Text';
		$this->dublin_core->Format = 'text/html';
		$this->dublin_core->Identifier = SECTION_SYMBOL . ' ' . $this->section_number;
		$this->dublin_core->Relation = LAWS_NAME;
	
		/*
		 * If the request specifies that rendered HTML should be returned, then generate that.
		 */
		if ( isset($this->config->render_html) && ($this->config->render_html === TRUE) )
		{
			$this->html = Law::render();
		}
		
		/*
		 * Provide a plain text version of this law.
		 */
		$this->plain_text = Law::render_plain_text();
	
		/*
		 * Provide a plain text document header.
		 */
		$this->plain_text =  str_repeat(' ', (round(((81 - strlen(LAWS_NAME)) / 2))))
			. strtoupper(LAWS_NAME) . "\n\n"
			. wordwrap(strtoupper($this->catch_line) . ' (' . SECTION_SYMBOL . ' '
			. $this->section_number . ')', 80, "\n", TRUE)
			. "\n\n" . $this->plain_text;
		if (!empty($this->history))
		{
			$this->plain_text .=  "\n" . wordwrap('HISTORY: ' . $this->history, 80, "\n", TRUE);
		}
		
		$law = $this;
		unset($law->config);
		
		return $law;
		
	}
	
	
	/**
	 * Return a listing of every section of the code that refers to a given section.
	 */
	function get_references()
	{
				
		/*
		 * We're going to need access to the database connection throughout this class.
		 */
		global $db;
		
		/*
		 * If a section number doesn't exist in the scope of this class, then there's nothing to do.
		 */
		if (!isset($this->section_id))
		{
			return FALSE;
		}
		
		/*
		 * Get a listing of IDs, section numbers, and catch lines.
		 */
		$sql = 'SELECT DISTINCT laws.id, laws.section AS section_number, laws.catch_line
				FROM laws
				INNER JOIN laws_references
					ON laws.id = laws_references.law_id
				WHERE laws_references.target_law_id =  '.$db->quote($this->section_id).'
				ORDER BY laws.order_by, laws.section ASC';
		
		/*
		 * Execute the query.
		 */
		$result = $db->query($sql);
		
		/*
		 * If the query fails, or if no results are found, return false -- no sections refer to
		 * this one.
		 */
		if ( ($result === FALSE) || ($result->rowCount() == 0) )
		{
			return FALSE;
		}
		
		/*
		 * Return the result as an enumerated object.
		 */
		$references = new stdClass();
		$i = 0;
		while ($reference = $result->fetch(PDO::FETCH_OBJ))
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
	
		/*
		 * We're going to need access to the database connection throughout this class.
		 */
		global $db;
		
		/*
		 * If a section number doesn't exist in the scope of this class, then there's nothing to do.
		 */
		if (!isset($this->section_number))
		{
			return FALSE;
		}
		
		/*
		 * Record the view.
		 */
		$sql = 'INSERT DELAYED INTO laws_views
				SET section="'.$this->section_number.'"';
		if (!empty($_SERVER['REMOTE_ADDR']))
		{
			$sql .= ', ip_address=INET_ATON("'.$_SERVER['REMOTE_ADDR'].'")';
		}
		
		/*
		 * Execute the query.
		 */
		$result = $db->exec($sql);
		
		/*
		 * If the query fails, return false.
		 */
		if ($result === FALSE)
		{
			return FALSE;
		}
		
		return TRUE;
	}
	

	/**
	 * Get all metadata for a single law.
	 */
	function get_metadata()
	{
	
		/*
		 * We're going to need access to the database connection throughout this class.
		 */
		global $db;
		
		/*
		 * If a section number doesn't exist in the scope of this class, then there's nothing to do.
		 */
		if (!isset($this->section_id))
		{
			return FALSE;
		}
		
		/*
		 * Get a listing of all metadata that belongs to this law.
		 */
		$sql = 'SELECT id, meta_key, meta_value
				FROM laws_meta
				WHERE law_id=' . $db->quote($this->section_id);
		$result = $db->query($sql);
		
		/*
		 * If the query fails, or if no results are found, return false -- no sections refer to this
		 * one.
		 */
		if ( ($result === FALSE) || ($result->rowCount() == 0) )
		{
			return FALSE;
		}
		
		/*
		 * Return the result as an object.
		 */
		$metadata = $result->fetchAll(PDO::FETCH_OBJ);
		
		/*
		 * Create a new object, to which we will port a rotated version of this object.
		 */
		$rotated = new stdClass();
		
		/*
		 * Iterate through the object in order to reorganize it, assigning the meta_key field to the
		 * key and the meta_value field to the value.
		 */
		foreach($metadata as $field)
		{
			
			$row->meta_value = stripslashes($row->meta_value);
			
			/*
			 * If unserializing this value works, then we've got serialized data here.
			 */
			if (@unserialize($row->meta_value) !== FALSE)
			{
				$row->meta_value = unserialize($row->meta_value);
			}
			
			/*
			 * Convert y/n values into TRUE/FALSE values.
			 */
			if ($row->meta_value == 'y')
			{
				$row->meta_value = TRUE;
			}
			elseif ($row->meta_value == 'n')
			{
				$row->meta_value = FALSE;
			}
			
			$rotated->{stripslashes($row->meta_key)} = $row->meta_value;
			
		}
		return $rotated;
	}
	

	/**
	 * When provided with a section number, it indicates whether that section exists. This is
 	 * designed for use when parsing the text of each section, which turns any section numbers into
 	 * links. But it has to verify that they're really section numbers, and not strings that
	 * resemble section numbers, which necessitates a fast, lightweight function.
	 */
	function exists()
	{
		
		/*
		 * We're going to need access to the database connection throughout this class.
		 */
		global $db;
		
		/*
		 * If neither a section number nor a law ID has been passed to this function, then there's
		 * nothing to do.
		 */
		if (!isset($this->section_number))
		{
			return FALSE;
		}

		/*
		 * Trim it down.
		 */
		$this->section_number = trim($this->section_number);

		/*
		 * Query the database for the ID for this section number, retrieving the current version
		 * of the law.
		 */
		$sql = 'SELECT *
				FROM laws
				WHERE section=' . $db->quote($this->section_number) . '
				AND edition_id=' . EDITION_ID;
		$result = $db->query($sql);
		
		if ( ($result === FALSE) || ($result->rowCount() < 1) )
		{
			return FALSE;
		}
		
		return TRUE;

	}
	
	/**
	 * Takes the instant law object and turns it into HTML, with embedded links, anchors, etc.
	 */
	function render()
	{
	
		/*
		 * Get the dictionary terms for this chapter.
		 */
		$dictionary = new Dictionary();
		$dictionary->structure_id = $this->structure_id;
		$dictionary->section_id = $this->section_id;
		$terms = (array) $dictionary->term_list();

		/*
		 * If we've gotten a list of dictionary terms.
		 */
		if ( ($terms !== FALSE) && is_array($terms) )
		{
			/*
			 * Arrange our terms from longest to shortest. This is to ensure that the most specific
			 * terms are defined (e.g. "person of interest") rather than the broadest terms (e.g.
			 * "person").
			 */
			usort($terms, 'sort_by_length');
			
			/*
			 * Store a list of the dictionary terms as an array, which is required for
			 * preg_replace_callback, the function that we use to insert the definitions.
			 */
			$term_pcres = array();
			foreach ($terms as $term)
			{
				
				/*
				 * Step through each character in this word.
				 */
				for ($i=0; $i<strlen($term); $i++)
				{
					/*
					 * If there are any uppercase characters, then make this PCRE string case
					 * sensitive.
					 */
					if ( (ord($term{$i}) >= 65) && (ord($term{$i}) <= 90) )
					{
						$term_pcres[] = '/\b'.$term.'(s?)\b(?![^<]*>)/';
						$caps = TRUE;
						break;
					}
				}
				
				/*
				 * If we have determined that this term does not contain capitalized letters, then
				 * create a case-insensitive PCRE string.
				 */
				if (!isset($caps))
				{
					$term_pcres[] = '/\b'.$term.'(s?)\b(?![^<]*>)/i';
				}
				
				/*
				 * Unset our flag -- we don't want to have it set the next time through.
				 */
				if (isset($caps))
				{
					unset($caps);
				}
			}
		}
		
		/*
		 * Instantiate our autolinker, which embeds links. If we've defined a state-custom
		 * autolinker, use that one. Otherwise, use the built-in one. Be sure not to attempt to
		 * autoload a file fitting our class-name schema, since this class, if it exists, would be
		 * found within class.[State].inc.php.
		 */
		if (class_exists('State_Autolinker', FALSE) === TRUE)
		{
			$autolinker = new State_Autolinker;
		}
		$autolinker = new Autolinker;

		/*
		 * Iterate through every section to make some basic transformations.
		 */
		foreach ($this->text as $section)
		{
			
			/*
			 * Prevent lines from wrapping in the middle of a section identifier.
			 */
			$section->text = str_replace('§ ', '§&nbsp;', $section->text);
			
			/*
			 * Turn every code reference in every paragraph into a link.
			 */
			$section->text = preg_replace_callback(SECTION_PCRE, array($autolinker, 'replace_sections'), $section->text);
			
			/*
			 * Turn every pair of newlines into carriage returns
			 */
			$section->text = wpautop($section->text);
			
			/*
			 * Use our dictionary to embed dictionary terms in the form of span titles.
			 */
			if (isset($term_pcres))
			{
				$section->text = preg_replace_callback($term_pcres, array($autolinker, 'replace_terms'), $section->text);
			}
		}
		
		$html = '';
		
		/*
		 * Iterate through each section of text to display it.
		 */
		$i=0;
		$num_paragraphs = count((array) $this->text);
		foreach ($this->text as $paragraph)
		{
		
			/*
			 * Identify the prior and next sections, by storing their prefixes.
			 */
			if ($i > 0)
			{
				$paragraph->prior_prefix = $this->text->{$i-1}->entire_prefix;
			}
			if ( ($i+1) < $num_paragraphs )
			{
				$paragraph->next_prefix = $this->text->{$i+1}->entire_prefix;
			}
		
			/*
			 * If this paragraph's prefix hierarchy is different than that of the prior prefix, then
			 * indicate that this is a new section.
			 */
			if ( !isset($paragraph->prior_prefix) || ($paragraph->entire_prefix != $paragraph->prior_prefix) )
			{
				$html .= '
					<section';
				if (!empty($paragraph->prefix_anchor))
				{
					$html .= ' id="' . $paragraph->prefix_anchor . '"';
				}
				
				/*
				 * If this is a subsection, indent it.
				 */
				if ($paragraph->level > 1)
				{
					$html .= ' class="indent-' . ($paragraph->level-1) . '"';
				}
				$html .= '>';
			}
			
			/*
			 * Start a paragraph of the appropriate type.
			 */
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
			
			/*
			 * If we've got a section prefix, and it's not the same as the last one, then display
			 * it.
			 */
			if 	( !empty($paragraph->prefix)
				&&
				( !isset($paragraph->prior_prefix) || ($paragraph->entire_prefix != $paragraph->prior_prefix) ) )
			{
				
				$html .= $paragraph->prefix;
				
				/*
				 * We could use a regular expression to determine if we need to append a period, but
				 * that would be slower.
				 */
				if ( (substr($paragraph->prefix, -1) != ')') && (substr($paragraph->prefix, -1) != '.') )
				{
					$html .= '.';
				}
				$html .= ' ';
			}
			
			/*
			 * Display this section of text. Purely structural sections lack text of their own (only
			 * their child structures contain text), which is why this is conditional.
			 */
			if (!empty($paragraph->text))
			{
				$html .= $paragraph->text;
			}
			
			/*
			 * If we've got a section prefix, append a paragraph link to the end of this section.
			 */
			if (!empty($paragraph->prefix))
			{
				/*
				 * Assemble the permalink
				 */
				$permalink = $_SERVER['REQUEST_URI'] . '#'.$paragraph->prefix_anchor;

				$html .= ' <a id="test-' . $paragraph->id . '" class="section-permalink"
					href="' . $permalink . '">¶</a>';
			}
			if ($paragraph->type == 'section')
			{
				$html .= '</p>';
			}
			elseif ($paragraph->type == 'table')
			{
				$html .= '</pre>';
			}
			
			/*
			 * If our next prefix is different than the current prefix, than terminate this section.
			 */
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
	 */
	function render_plain_text()
	{
	
		if (!isset($this->text))
		{
			return FALSE;
		}

		/*
		 * Iterate through every section to make some basic transformations.
		 */
		foreach ($this->text as $section)
		{
			
			/*
			 * Prevent lines from wrapping in the middle of a section identifier by replacing the
			 * &nbsp; entity with the Unicode NO-BREAK-SPACE (U+00A0) character.
			 */
			$section->text = str_replace('§&nbsp;', '§ ', $section->text);
			
			/*
			 * Eliminate any HTML.
			 */
			$section->text = strip_tags($section->text);
			
		}
		
		/*
		 * Instantiate the variable in which we'll store the plain text.
		 */
		$text = '';
		
		/*
		 * Iterate through each section of text to display it.
		 */
		$i=0;
		$num_paragraphs = count((array) $this->text);
		foreach ($this->text as $paragraph)
		{
			
			/*
			 * Initialize a variable that we'll use to store the text for this subsection.
			 */
			$subsection = '';
			
			/*
			 * If we've got a section prefix, and it's not the same as the last one, then display
			 * it.
			 */
			if 	( !empty($paragraph->prefix)
				&&
				( !isset($paragraph->prior_prefix) || ($paragraph->entire_prefix != $paragraph->prior_prefix) ) )
			{
				
				$subsection .= $paragraph->prefix;
				
				/*
				 * We could use a regular expression to determine if we need to append a period, but
				 * that would be slower.
				 */
				if ( (substr($paragraph->prefix, -1) != ')') && (substr($paragraph->prefix, -1) != '.') )
				{
					$subsection .= '.';
				}
				$subsection .= ' ';
			}
			
			/*
			 * Add the text itself to the subsection.
			 */
			$subsection .= $paragraph->text;
			
			/*
			 * Wrap this text at 80 characters minus two spaces for every nested subsection,
			 * breaking up words that exceed the line length.
			 */
			$subsection = wordwrap($subsection, (80 - (($paragraph->level - 1) * 2)), "\n", TRUE);
			
			/*
			 * Indent applicable subsections by adding blank space to the beginning of each line.
			 */
			if ($paragraph->level > 0)
			{
				$lines = explode("\n", $subsection);
				foreach ($lines as &$line)
				{
					$line = str_repeat(' ', ( ($paragraph->level - 1) * 3 )) . $line;
				}
				$subsection = implode("\n", $lines);
			}
			
			/*
			 * Finish up with a pair of newlines.
			 */
			$subsection .= "\n\n";
			
			/*
			 * And, finally, add this subsection to the text of the section.
			 */
			$text .= $subsection;
			
			$i++;
		}
		
		/*
		 * Hack off any trailing (or, somehow, leading) whitespace, and finish with a single
		 * newline.
		 */
		$text = trim($text) . "\n";
		
		return $text;
		
	} // end render_plain_text()

} // end Law
