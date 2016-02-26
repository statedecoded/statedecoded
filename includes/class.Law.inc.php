<?php

/**
 * The Law class, for retrieving data about individual laws.
 *
 * PHP version 5
 *
 * @license		http://www.gnu.org/licenses/gpl.html GPL 3
 * @version		0.9
 * @link		http://www.statedecoded.com/
 * @since		0.1
 *
 */

class Law
{
	protected $db;

	public function __construct($args = array())
	{
		foreach($args as $key=>$value)
		{
			$this->$key = $value;
		}

		/*
		 * We're going to need access to the database connection throughout this class.
		 */
		if(!isset($this->db))
		{
			global $db;
			$this->db = $db;
		}
	}

	/**
	 * Retrieve all of the material relevant to a given law.
	 */
	public function get_law()
	{
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
		if (!isset($this->config) || !is_object($this->config) )
		{
			$this->config = new StdClass();
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
			$this->config->get_tags = TRUE;
			$this->config->render_html = TRUE;
		}

		/*
		 * Assemble the query that we'll use to get this law.
		 */
		$sql = 'SELECT id AS section_id, structure_id, edition_id,
				section AS section_number, catch_line,
				history, text AS full_text, order_by
				FROM laws';
		$sql_args = array();

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
				$sql .= ' WHERE id = :id';
				$sql_args[':id'] = $this->law_id;
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
				$law_count = count($this->law_id);
				for($i = 0; $i < $law_count; $i++)
				{
					$sql .= " id = :id$i";
					$sql_args[":id$i"] = $this->law_id[$i];

					if ($i < ($law_count - 1))
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
			$sql .= ' WHERE section = :section_number
					AND edition_id = :edition_id';
			$sql_args[':section_number'] = $this->section_number;

			if(isset($this->edition_id))
			{
				$sql_args[':edition_id'] = $this->edition_id;
			}
			else
			{
				$sql_args[':edition_id'] = EDITION_ID;
			}
		}

		$statement = $this->db->prepare($sql);
		$result = $statement->execute($sql_args);

		if ( ($result === FALSE) || ($statement->rowCount() == 0) )
		{
			return FALSE;
		}

		/*
		 * Return the result as an object.
		 */
		$tmp = $statement->fetch(PDO::FETCH_OBJ);

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
					WHERE law_id = :law_id
					ORDER BY text.sequence ASC';
			$sql_args = array(
				':law_id' => $this->section_id
			);

			$statement = $this->db->prepare($sql);
			$result = $statement->execute($sql_args);

			/*
			 * If the query fails, or if no results are found, return false -- we can't make a
			 * match.
			 */
			if ( ($result === FALSE) || ($statement->rowCount() == 0) )
			{
				return FALSE;
			}

			/*
			 * Iterate through all of the sections of text to save to our object.
			 */
			$i=0;
			while ($tmp = $statement->fetch(PDO::FETCH_OBJ))
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
				if (!isset($this->text))
				{
					$this->text = new StdClass();
				}
				$this->text->$i = $tmp;
				$i++;

			}
		}

		/*
		 * Determine this law's structural position.
		 */
		if ($this->config->get_structure = TRUE)
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
		 * Gather any tags applied to this law.
		 */
		if ($this->config->get_tags == TRUE)
		{
			$sql = 'SELECT text
					FROM tags
					WHERE law_id = ' . $this->db->quote($this->section_id);

			$result = $this->db->query($sql);

			if ( ($result !== TRUE) && ($result->rowCount() > 0) )
			{

				$this->tags = new stdClass();

				$i = 0;
				while ($tag = $result->fetch(PDO::FETCH_OBJ))
				{
					$this->tags->{$i} = $tag->text;
					$i++;
				}

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
				if ($state->get_amendment_attempts() !== FALSE)
				{
					$this->amendment_attempts = $state->bills;
				}
			}

		}

		/*
		 * Get the court decisions that affect this law and include those (if there are any). But
		 * only if we have specifically requested this data. That's because, on most installations,
		 * this will be making a call to a third-party service and such a call is expensive.
		 */
		if ($this->config->get_court_decisions == TRUE)
		{

			/*
			 * If we already have this data cached as metadata, and it's not blank. (We cache not
			 * just when there are court decisions for a given law, but also when there are no
			 * court decisions for a law. "No court decisions" is represented as an empty record.)
			 */
			if ( isset($this->metadata->court_decisions) && !empty($this->metadata->court_decisions) )
			{
				$this->court_decisions = $this->metadata->court_decisions;
			}

			/*
			 * If we do not have this data cached.
			 */
			else
			{
				if (method_exists($state, 'get_court_decisions'))
				{
					if ($state->get_court_decisions() !== FALSE)
					{
						$this->court_decisions = $state->decisions;
					}
				}
			}

			/*
			 * If we've cached the fact that there are no court decisions.
			 */
			if ($this->court_decisions->{0} == FALSE)
			{
				unset($this->court_decisions);
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
			$state->citations();
			$this->citation = $state->citation;
		}

		/*
		 * Get the references to this law among other laws and include those (if there are any).
		 */
		if ($this->config->get_references == TRUE)
		{
			$this->references = Law::get_references();
			$this->refers_to = Law::get_references(true);
		}

		/*
		 * Pretty up the text for the catch line.
		 */
		$this->catch_line = wptexturize($this->catch_line);

		/*
		 * Provide the URL for this section.
		 */
		$sql = 'SELECT url, token
				FROM permalinks
				WHERE relational_id = :id
				AND object_type = :object_type';
		$statement = $this->db->prepare($sql);

		$sql_args = array(
			':id' => $this->section_id,
			':object_type' => 'law'
		);

		$result = $statement->execute($sql_args);

		if ( ($result !== FALSE) && ($statement->rowCount() > 0) )
		{
			$permalink = $statement->fetch(PDO::FETCH_OBJ);
			$this->url = $permalink->url;
			$this->token = $permalink->token;
		}

		/*
		 * List the URLs for the textual formats in which this section is available.
		 */
		if(!isset($this->formats))
		{
			$this->formats = new StdClass();
		}

		$this->formats->txt = substr($this->url, 0, -1) . '.txt';
		$this->formats->json = substr($this->url, 0, -1) . '.json';
		$this->formats->xml = substr($this->url, 0, -1) . '.xml';

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
	 * If $to is true, returns laws the current law references.
	 * If $to is fales, returns laws that reference the current law.
	 */
	public function get_references($to = false)
	{

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
		$sql = 'SELECT DISTINCT laws.id, laws.section AS section_number,
			laws.catch_line FROM laws ';
		if($to)
		{
			$sql .= 'INNER JOIN laws_references
				ON laws.id = laws_references.target_law_id
				WHERE laws_references.law_id =  :law_id ';
		}
		else
		{
			$sql .= 'INNER JOIN laws_references
				ON laws.id = laws_references.law_id
				WHERE laws_references.target_law_id =  :law_id ';
		}
		$sql .= 'AND laws.edition_id = :edition_id
			ORDER BY laws.order_by, laws.section ASC';
		$sql_args = array(
			':law_id' => $this->section_id,
			':edition_id' => $this->edition_id
		);
		/*
		 * Execute the query.
		 */
		$statement = $this->db->prepare($sql);
		$result = $statement->execute($sql_args);

		/*
		 * If the query fails, or if no results are found, return false -- no sections refer to
		 * this one.
		 */
		if ( ($result === FALSE) || ($statement->rowCount() == 0) )
		{
			return FALSE;
		}

		$permalink_obj = new Permalink(array('db' => $this->db));

		$references = array();
		while ($reference = $statement->fetch(PDO::FETCH_OBJ))
		{
			$reference->catch_line = stripslashes($reference->catch_line);

			$permalink = $permalink_obj->get_preferred(
				$reference->id, 'law', $this->edition_id);
			$reference->url = $permalink->url;

			$references[] = $reference;
		}

		return $references;

	}

	/**
	 * Return the URL for a given law ID.
	 *
	 * This is meant to be invoked inline, which is why it takes a section id as a parameter and
	 * returns a URL, rather than getting and setting those as object properties.
	 *
	 * By default, this will get the preferred link.
	 *
	 */
	### TODO fix references to this.
	### TODO replace the body of this with a call to Permalink.
	public function get_url($law_id, $edition_id = null, $permalink = false)
	{

		/*
		 * If a section number hasn't been passed to this function, then there's nothing to do.
		 */
		if (empty($law_id))
		{
			return FALSE;
		}

		/*
		 * Set the default edition.
		 */
		if (empty($edition_id))
		{
			$edition_obj = new Edition(array('db' => $this->db));
			$edition = $edition_obj->current();
			$edition_id = $edition_object->id;
		}

		$sql = 'SELECT *
				FROM permalinks
				WHERE object_type="law"
				AND relational_id = :law_id';

		$sql_args = array(
			':law_id' => $law_id
		);

		if($permalink === true)
		{
			$sql .= ' AND permalink = 1';
		}
		else {
			$sql .= ' AND preferred = 1';
		}

		$statement = $this->db->prepare($sql);
		$result = $statement->execute($sql_args);

		if ( ($result === FALSE) || ($statement->rowCount() == 0) )
		{
			return FALSE;
		}

		$permalink = $statement->fetch(PDO::FETCH_OBJ);

		return $permalink;

	}


	/**
	 * Record a view of a single law.
	 */
	public function record_view()
	{

		/*
		 * If configured not to record views, then quietly exit.
		 */
		if ( defined('RECORD_VIEWS') && (RECORD_VIEWS === FALSE) )
		{
			return TRUE;
		}

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
				SET section = :section';
		$sql_args = array(
			':section' => $this->section_number
		);
		if (!empty($_SERVER['REMOTE_ADDR']))
		{
			$sql .= ', ip_address=INET_ATON(:ip)';
			$sql_args[':ip'] = $_SERVER['REMOTE_ADDR'];
		}

		/*
		 * Execute the query.
		 */
		$statement = $this->db->prepare($sql);
		$result = $statement->execute($sql_args);

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
	public function get_metadata()
	{

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
				WHERE law_id = :law_id';
		$sql_args = array(
			':law_id' => $this->section_id
		);
		$statement = $this->db->prepare($sql);
		$result = $statement->execute($sql_args);

		/*
		 * If the query fails, or if no results are found, return false -- no sections refer to this
		 * one.
		 */
		if ( ($result === FALSE) || ($statement->rowCount() == 0) )
		{
			return FALSE;
		}

		/*
		 * Return the result as an object.
		 */
		$metadata = $statement->fetchAll(PDO::FETCH_OBJ);

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

			/*
			 * If unserializing this value works, then we've got serialized data here.
			 */
			if (@unserialize($row->meta_value) !== FALSE)
			{
				$field->meta_value = unserialize($field->meta_value);
			}

			/*
			 * If JSON decoding this value works, then we've got JSON data here.
			 */
			if (@json_decode($row->meta_value) !== FALSE)
			{
				$field->meta_value = json_decode($field->meta_value);
			}

			/*
			 * Convert y/n values into TRUE/FALSE values.
			 */
			if ($field->meta_value == 'y')
			{
				$field->meta_value = TRUE;
			}
			elseif ($field->meta_value == 'n')
			{
				$field->meta_value = FALSE;
			}

			$rotated->{$field->meta_key} = $field->meta_value;

		}

		return $rotated;

	}

	/*
	 * Store a single piece of metadata for a single law
	 *
	 * Must receive $this->section_id and $this->metadata. The latter is an object that that
	 * contains a series of $key => $value pairs (at least one) that are to be stored for the law
	 * in question.
	 *
	 * This method exists within Law, as opposed to within the importer, because metadata can be
	 * stored at any time. For example, a list of court rulings affecting a given law wouldn't be
	 * imported only when the parser is run, because a court could issue a new ruling again at any
	 * time. Instead, that data is imported periodically, incrementally, via store_metadata.
	 *
	 * @param	string	$this->section_id	The ID of the law.
	 * @param	object	$this->metadata		Key/value pairs to be stored.
	 * @return TRUE or FALSE
	 */
	public function store_metadata()
	{

		if ( !isset($this->section_id) || !is_object($this->metadata) )
		{
			return FALSE;
		}

		$sql = 'INSERT INTO laws_meta
				SET law_id = :law_id,
				meta_key = :meta_key,
				meta_value = :meta_value,
				date_created = now()';
		$statement = $this->db->prepare($sql);

		foreach ($this->metadata as $field)
		{
			$sql_args = array(
				':law_id' => $this->section_id,
				':meta_key' => $field->key,
				':meta_value' => $field->value
			);
			$result = $statement->execute($sql_args);

			if ($result === FALSE)
			{
				return FALSE;
			}

		}

		return TRUE;

	}


	/**
	 * When provided with a section number, it indicates whether that section exists. This is
 	 * designed for use when parsing the text of each section, which turns any section numbers into
 	 * links. But it has to verify that they're really section numbers, and not strings that
	 * resemble section numbers, which necessitates a fast, lightweight function.
	 */
	public function exists()
	{

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
				WHERE section = :section
				AND edition_id = :edition_id';
		$sql_args = array(
			':section' => $this->section_number,
		);

		if(isset($this->edition_id))
		{
			$sql_args[':edition_id'] = $this->edition_id;
		}
		else
		{
			$sql_args[':edition_id'] = EDITION_ID;
		}

		$statement = $this->db->prepare($sql);
		$result = $statement->execute($sql_args);

		if ( ($result === FALSE) || ($statement->rowCount() < 1) )
		{
			return FALSE;
		}

		return TRUE;

	}

	/**
	 * Takes the instant law object and turns it into HTML, with embedded links, anchors, etc.
	 */
	public function render()
	{

		/*
		 * Get the dictionary terms for this chapter.
		 */
		$dictionary = new Dictionary();
		$dictionary->structure_id = $this->structure_id;
		$dictionary->section_id = $this->section_id;
		if (USE_GENERIC_TERMS !== TRUE)
		{
			$dictionary->generic_terms = FALSE;
		}
		$tmp = $dictionary->term_list();
		if ($tmp !== FALSE)
		{
			$terms = (array) $tmp;
			unset($tmp);
		}

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
		$autolinker = new Autolinker(
			array(
				'edition_id' => $this->edition_id,
				'db' => $this->db
			)
		);

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
			 * Highlight any search terms.
			 */
			if($_GET['q'])
			{
				$query = str_replace('"', '', $_GET['q']);
				$query = explode(' ', $query);
				foreach($query as $term)
				{
					$section->text = str_replace($term, '<span class="search-term">' . $term . '</span>', $section->text);
				}
			}

			/*
			 * Turn every code reference in every paragraph into a link.
			 */
			$section->text = preg_replace_callback(SECTION_REGEX, array($autolinker, 'replace_sections'), $section->text);

			/*
			 * Turn every pair of newlines into carriage returns.
			 */
			$section->text = preg_replace('/\R\R/', '<br /><br />', $section->text);

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
			if ($paragraph->type == 'section')
			{
				$html .= '<p>';
			}
			elseif ($paragraph->type == 'table')
			{
				$html .= '<div class="tabular"><pre class="table">';
			}

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
			if (!empty($paragraph->prefix) && !defined('EXPORT_IN_PROGRESS'))
			{
				/*
				 * Assemble the permalink
				 */

				$permalink = $_SERVER['REQUEST_URI'] . '#'
					. $paragraph->prefix_anchor;

				$html .= ' <a id="paragraph-' . $paragraph->id . '" class="section-permalink" '
					.'href="' . $permalink . '"><i class="icon-link"></i></a>';
			}
			if ($paragraph->type == 'section')
			{
				$html .= '</p>';
			}
			elseif ($paragraph->type == 'table')
			{
				$html .= '</pre></div>';
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
	public function render_plain_text()
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

	/**
	 * Get ids of all of the laws for a given edition.
	 *
	 * param int  $edition_id The edition to query.
	 * param bool $result_handle_only Get the results or just return the handle.
	 *                                This is useful when we don't have much memory.
	 */
	public function get_all_laws($edition_id, $result_handle_only = false)
	{
		$query_args = array(
			':edition_id' => $edition_id
		);
		$query = 'SELECT laws.id
			FROM laws
			WHERE edition_id = :edition_id';

		$statement = $this->db->prepare($query);
		$result = $statement->execute($query_args);

		if($result_handle_only)
		{
			return $statement;
		}
		else
		{
			return $statement->fetchAll();
		}
	}

	/**
	 * A stripped down version of the get_law() function.  Used by the Autolinker.
	 */
	public function get_matching_sections($section, $edition_id, $fields = array())
	{
		static $select_statement;
		if(!isset($select_statement))
		{
			$sql = 'SELECT id, catch_line FROM laws WHERE section = :section AND
				edition_id = :edition_id ORDER BY order_by';
			$select_statement = $this->db->prepare($sql);
		}

		$sql_args = array(
			':section' => $section,
			':edition_id' => $edition_id
		);

		$select_result = $select_statement->execute($sql_args);

		if ($select_result === FALSE || $select_statement->rowCount() == 0)
		{
			return FALSE;
		}
		else
		{
			$permalink_obj = new Permalink(array('db' => $this->db));

			$laws = $select_statement->fetchAll(PDO::FETCH_OBJ);
			foreach($laws as $key=>$law)
			{
				$permalink = $permalink_obj->get_preferred($law->id, 'law',
					$edition_id);
				$laws[$key]->url = $permalink->url;
			}
			return $laws;
		}
	}


} // end Law
