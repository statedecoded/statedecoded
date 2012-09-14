<?php

class Parser
{
	
	# Gather the raw text of each law for the parse() function to figure out what to do with. This
	# might iterate through files with one law per file (as with this example), it might be screen-
	# scraping text off a legislature's website, or it might do any number of other things to gather
	# that text. This function needs to loop over and over again, returning the contents of a single
	# law with each loop, until finally the entire Code has been iterated over.
	public function iterate()
	{
		
		# Get a listing of every file in the directory.
		$files = scandir($this->directory);
		
		# Iterate through each file.
		for ($j = $this->file; $j < count($files); $j++)
		{
			
			$this->file = $j;
			
			$filename = $files[$j];
			
			# Open the file and store its contents as an array.
			$law = file($filename);
			
			return $law;
			
		} // end iterating through files
	} // end iterate() function
	
	
	# Accept the raw content of a section of code and extract each unit of data into the proper
	# object-based format.
	public function parse()
	{
	
		# The identifier for this law (e.g., "10.12-46-21").
		$this->code->section_number = '';

		# The name of this law (e.g. "Receiving stolen goods").
		$this->code->name = '';
		
		# Store the full text of this law.
		$this->code->text = '';
		
		# Each set of laws is arranged from beginning to ending, with every law occupying a
		# particular position in that order. To know in what order to display those laws, every
		# law must be assigned some sort of "order by" characteristic that will result in a natural
		# order when selected from the database. Assign such a number here.
		$this->code->order_by = '';
		
		# This bit contains the structure (e.g., chapter, title, part, etc.) number.
		$this->code->structure_number = '';
	
		# Store the structure name.
		$this->code->structure_name = '';
				
		# Iterate through each section of this law (e.g., section A, then section B, etc.)
		$i=0;
		foreach ($section_of_the_code as $section)
		{
			
			# As we iterate deeper into the code (e.g., subsection 1, then sub-subsection a), build
			# up an array (e.g., array(A, 1, a)) so that we know the precise identifier for this
			# subsection of text.
			$prefixes[] = $section->prefix;
			
			# Iterate through the prefix structure of the text of the law and store each prefix section
			# in our code object.
			for ($i=0; $i<count($prefixes); $i++)
			{
				$this->code->section->$i->prefix_hierarchy->$j = $prefixes[$j];
			}
			
			# And store the prefix list as a single string.
			$this->code->section->$i->prefix = implode('', $prefixes);
			
			# Store the text of this individual section
			$this->code->section->$i->text = $section->text;
				
			# Include the type in $code, too. Type might be "text," "table," "illustration," or any
			# other class of section that would necessitate different display in the storage or
			# rendering process.
			$this->code->section->$i->type = $section->type;
			
			$i++;
		}			
		
		# If the words "repealed effective" appear in the name then we mark this section as repealed.
		if (strpos($this->code->name, 'repealed effective') !== false)
		{
			$this->code->repealed = 'y';
		}
		
		# Save history data for this law (i.e., when it was passed, when it was amended, etc.)
		$this->code->history = '';
			
		# Make the data available outside of the scope of this function.
		$this->code = $code;
		
		unset($code);
	}
	
	# Take an object containing the normalized code data and store it.
	public function store()
	{
		if (!isset($this->code))
		{
			die('No data provided.');
		}
		
		# Try to create a new chapter. If the chapter already exists, create_structure() will handle
		# that silently. Either way a chapter ID gets returned.
		$structure = new Parser;
		$structure->number = $this->code->structure_number;
		$structure->name = $this->code->structure_name;
		$structure_id = $chapter->create_structure();
		
		# Build up an array of field names and values, using the names of the database columns as
		# the key names.
		$query['catch_line'] = $this->code->name;
		$query['section'] = $this->code->section_number;
		$query['text'] = $this->code->text;
		if (isset($this->code->history))
		{
			$query['history'] = $this->code->history;
		}
		if (isset($this->code->repealed))
		{
			$query['repealed'] = $this->code->repealed;
		}
		
		# Create the beginning of the insertion statement.
		$sql = 'INSERT INTO laws
				SET date_created=now(), edition_id='.EDITION_ID;
				
		# Iterate through the array and turn it into SQL.
		foreach ($query as $name => $value)
		{
			$sql .= ', '.$name.'="'.$db->escape($value).'"';
		}
		
		# Execute the query.
		$result =& $db->exec($sql);
		
		# Preserve the insert ID from this law, since we'll need it below.
		$law_id = $db->lastInsertID();
		
		# This second section inserts the textual portions of the law.
		
		# Pull out any mentions of other sections of the code that are found within its text and
		# save a record of those, for crossreferencing purposes.
		$references = new Parser;
		$references->text = $this->code->text;
		$sections = $references->extract_references();
		if ( ($sections !== false) && (count($sections) > 0) )
		{
			$references->section_id = $law_id;
			$references->sections = $sections;
			$success = $references->store_references();
		}
		
		# Step through each section.
		$i=1;
		foreach ($this->code->section as $section)
		{
			
			# If no section type has been specified, make it your basic section.
			if (!isset($section->type) || empty($section->type))
			{
				$section->type = 'section';
			}
			
			# Insert this section of the...uh...section into the text table.
			$sql = 'INSERT INTO text
					SET law_id='.$law_id.',
					sequence='.$i.',
					text="'.$db->escape($section->text).'",
					type="'.$db->escape($section->type).'",
					date_created=now()';

			# Execute the query.
			$result =& $db->exec($sql);
		
			# Preserve the insert ID from this section of text, since we'll need it below.
			$text_id = $db->lastInsertID();
			
			# Start a new counter.
			$j = 1;
			
			# Step through every portion of the prefix (i.e. A4b is three portions) and insert each.
			foreach ($section->prefix_hierarchy as $prefix)
			{
				$sql = 'INSERT INTO text_sections
						SET text_id='.$text_id.',
						identifier="'.$prefix.'",
						sequence='.$j.',
						date_created=now()';
				
				# Execute the query.
				$result =& $db->exec($sql);
				
				$j++;
			}
			
			$i++;
		}
		
		# Trawl through the text for definitions, if the section contains "Definitions" in the title
		# or if the current chapter is the chapter that we defined in the site config as containing
		# the global definitions.
		if (
			(stristr($this->code->name, 'Definitions.') !== false)
			||
			(stristr($this->code->name, 'Definition.') !== false)
			||
			(stristr($this->code->name, 'Meaning of certain terms.') !== false)
			||
			($chapter->title_number.'-'.$this->code->chapter_number == GLOBAL_DEFINITIONS) )
		{
			
			$dictionary = new Parser;
			
			# Pass this section of text to $dictionary.
			$dictionary->text = $this->code->text;
			
			# Get a normalized listing of definitions.
			$definitions = $dictionary->extract_definitions();
			
			# Override the calculated scope for global definitions.
			if ($chapter->title_number.'-'.$this->code->chapter_number == GLOBAL_DEFINITIONS)
			{
				$definitions->scope = 'global';
			}
			
			# If any definitions were found in this text, store them.
			if ($definitions !== false)
			{
				$dictionary->terms = $definitions->terms;
				$dictionary->law_id = $law_id;
				$dictionary->scope = $definitions->scope;
				$dictionary->store_definitions();
			}
		}
	}
	
	# When provided with a structure number and label, verifies whether that structural unit. Returns
	# the structure ID if it exists; otherwise, returns false. Requires the structural unit's
	# natural number (e.g., "3" -- the actual number provided within the Code) and the label for
	# that structural unit (e.g., "chapter," "part," "title," etc.) Note that there the combination
	# of a type and number will rarely be unique, with the exception of top-level structural units
	# (e.g., titles), so this function should almost always be provided with $parent_id that
	# specifies the ID of this structural unit's parent.
	function structure_exists()
	{
		
		# We're going to need access to the database connection within this function.
		global $db;
	
		if (!isset($this->number) || !isset($this->title_id))
		{
			return false;
		}
		
		# If a label hasn't been provided, just assume that it's a chapter.
		if (!isset($this->label))
		{
			$this->label = 'chapter';
		}
		
		# Assemble the query.
		$sql = 'SELECT id
				FROM structure
				WHERE label="'.$this->label.'" AND number="'.$this->number.'"
				AND parent_id='.$this->title_id;

		# Execute the query.
		$result =& $db->query($sql);

		# If the query fails, or if no results are found, return false -- we can't make a match.
		if ( PEAR::isError($result) || ($result->numRows() < 1) )
		{
			return false;
		}
		
		$chapter = $result->fetchRow(MDB2_FETCHMODE_OBJECT);
		return $chapter->id;
	}
	
	# When provided with a structural unit number and type, it creates a record for that structural
	# unit. Save for top-level structural units (e.g., titles), it should always be provided with
	# a $parent_id, which is the ID of the parent structural unit. Most structural units will have
	# a name, but not all.
	function create_structure()
	{
		
		# We're going to need access to the database connection within this function.
		global $db;
		
		# Sometimes the code contains references to no-longer-existent chapters and even whole
		# titles of the code. These are void of necessary information. We want to ignore these
		# silently. Though you'd think we should require a chapter name, we actually shouldn't,
		# because sometimes chapters don't have names. In the Virginia Code, for instance, titles
		# 8.5A, 8.6A, 8.10, and 8.11 all have just one chapter ("part"), and none of them have a
		# name.
		if (empty($this->number) || empty($this->title_number))
		{
			return false;
		}
		
		{
			# Get the ID of the title that contains this chapter.
			$sql = 'SELECT id
					FROM structure
					WHERE number="'.$db->escape($this->parent_number).'"
					AND label="'.$db->escape($this->parent_label).'"';
			# Execute the query.
			$result =& $db->query($sql);
			
			$title = $result->fetchRow(MDB2_FETCHMODE_OBJECT);
			$this->title_id = $title->id;
		}
		
		# Insert this chapter record into the database. We use ON DUPLICATE KEY so that this can
		# be run without first invoking chapter_exists().
		$sql = 'INSERT INTO structure
				SET number="'.$db->escape($this->number).'",';
		if (isset($this->name) && !empty($this->name))
		{
			$sql .= 'name="'.$db->escape($this->name).'",';
		}
		$sql .= 'label="chapter", date_created=now(), parent_id='.$this->parent_id.'
				ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)';

		# Execute the query.
		$result =& $db->exec($sql);
	
		# Return the new structural unit's ID.
		return $db->lastInsertID();
	}
	
	
	# When fed a section of the code that contains definitions, extracts the definitions from that
	# section and returns them as an object. Requires only a block of text.
	function extract_definitions()
	{
		
		if (!isset($this->text))
		{
			return false;
		}
		
		# Measure whether there are more straight quotes or directional quotes in this passage
		# of text, to determine which type are used in these definitions. We double the count of
		# directional quotes since we're only counting one of the two directions.
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
		
		# Break up this section into paragraphs.
		$paragraphs = explode('</p><p>', $this->text);
		
		# Create the empty array that we'll build up with the definitions found in this section.
		$definitions = array();
		
		# Step through each paragraph and determine which contain definitions.
		foreach ($paragraphs as &$paragraph)
		{
			
			# Any remaining paragraph tags are within an individual, multi-part definition, and can
			# be turned into spaces.
			$paragraph = str_replace('</p><p>', ' ', $paragraph);
			
			# Strip out any remaining HTML.
			$paragraph = strip_tags($paragraph);
			
			# Calculate the scope of these definitions using the first line.
			if (reset($paragraphs) == $paragraph)
			{
				if (
					(stripos($paragraph, 'as used in this chapter') !== false)
					|| 
					(stripos($paragraph, 'for the purpose of this chapter') !== false)
					|| 
					(stripos($paragraph, 'as used in this article') !== false)
					|| 
					(stripos($paragraph, 'as used in this act') !== false)
				   )
				{
					$scope = 'chapter';
				}
				
				elseif (stripos($paragraph, 'as used in this title') !== false)
				{
					$scope = 'title';
				}
				
				elseif (stripos($paragraph, 'as used in this section') !== false)
				{
					$scope = 'section';
				}
				
				# If we can't calculate scope, then we can safely assume it's specific to this
				# chapter.
				else
				{
					$scope = 'chapter';
				}
				
				# That's all we're going to get out of this paragraph, so move onto the next one.
				next;
			}
			
			# All defined terms are surrounded by quotation marks, so let's use that as a criteria
			# to round down our candidate paragraphs.
			if (strpos($paragraph, '"') !== false)
			{
				if (
					(strpos($paragraph, ' mean ') !== false)
					|| 
					(strpos($paragraph, ' means ') !== false)
					|| 
					(strpos($paragraph, ' shall include ') !== false)
					|| 
					(strpos($paragraph, ' includes ') !== false)
					|| 
					(strpos($paragraph, ' has the same meaning as ') !== false)
				   )
				{
				
					# Extract every word in quotation marks in this paragraph as a term that's being
					# defined here. Most definitions will have just one term being defined, but some
					# will have two or more.
					// Isn't this too broad? How can we narrow the scope?
					// We're getting words between quotation marks, such as the word "or" in the
					// passage "'alpha' or 'bravo'". Also, this is too greedy. Or something. The
					// matching for lists of defined words is just weird.
					preg_match_all('/("|“)([A-Za-z]{1})([A-Za-z,\'\s]*)([A-Za-z]{1})("|”)/', $paragraph, $terms);
					
					# If we've made any matches.
					if ( ($terms !== false) && (count($terms) > 0) )
					{
						
						# We only need the first element in this multi-dimensional array, which has
						# the actual matched term. It includes the quotation marks in which the term
						# is enclosed, so we strip those out.
						if ($quote_type == 'straight')
						{
							$terms = str_replace('"', '', $terms[0]);
						}
						elseif ($quote_type == 'directional')
						{
							$terms = str_replace('“', '', $terms[0]);
							$terms = str_replace('”', '', $terms);
						}
						
						# Eliminate whitespace.
						$terms = array_map('trim', $terms);
						
						# Lowercase most (but not necessarily all) terms. Any term that contains
						# any lowercase characters will be made entirely lowercase. But any term
						# that is in all caps is surely an acronym, and should be stored in its
						# original case so that we don't end up with overzealous matches. For
						# example, "CA" is a definition in section 3.2-4600, and we don't want to
						# match every time "ca" appears within a word.
						foreach ($terms as &$term)
						{
							# Drop noise words that occur in lists of words.
							if (($term == 'and') || ($term == 'or'))
							{
								unset($term);
							}
						
							# Step through each character in this word.
							for ($i=0; $i<strlen($term); $i++)
							{
								# If there are any lowercase characters, then make the whole thing
								# lowercase.
								if ( (ord($term{$i}) >= 97) && (ord($term{$i}) <= 122) )
								{
									$term = strtolower($term);
									break;
								}
							}
						}
						
						# Step through all of our matches and save them as discrete definitions.
						foreach ($terms as $term)
						{
							
							# It's possible for a definition to be preceded by a subsection number.
							# We want to pare down our definition down to the minimum, which means
							# excluding that. Solution: Start definitions at the first quotation
							# mark.
							$paragraph = substr($paragraph, strpos($paragraph, '"'));
							
							# Comma-separated lists of multiple words being defined need to have
							# the trailing commas removed.
							if (substr($term, -1) == ',')
							{
								$term = substr($term, 0, -1);
							}
							
							# If we don't yet have a record of this term.
							if (!isset($definitions[$term]))
							{
								# Append this definition to our list of definitions.
								$definitions[$term] = $paragraph;
							}
							
							# If we already have a record of this term. This is for when a word is
							# defined twice, once to indicate what it means, and one to list what it
							# doesn't mean. This is actually pretty common.
							else
							{
								# Make sure that they're not identical -- this can happen if the
								# defined term is repeated, in quotation marks, in the body of the
								# definition.
								if ( trim($definitions[$term]) != trim($paragraph) )
								{
									# Append this definition to our list of definitions.
									$definitions[$term] .= ' '.$paragraph;
								}
							}
						} // end iterating through matches
					} // end dealing with matches
				} // end this candidate paragraph (level 1)
			} // end this candidate paragraph (level 2)
			
			# We don't want to accidentally use this the next time we loop through.
			unset($terms);
		}
		
		if (count($definitions) == 0)
		{
			return false;
		}
		
		# Make the list of definitions a subset of a larger variable, so that we can store things
		# other than terms.
		$tmp = array();
		$tmp['terms'] = $definitions;
		$tmp['scope'] = $scope;
		$definitions = $tmp;
		unset($tmp);
			
		# Return our list of definitions, converted from an array to an object.
		return (object) $definitions;

	} // end extract_definitions()
	
	
	# When provided with an object containing a list of terms, their definitions, their scope,
	# and their section number, this will store them in the database.
	function store_definitions()
	{
		if ( !isset($this->terms) || !isset($this->law_id) || !isset($this->scope) )
		{
			return false;
		}
		
		# We're going to need access to the database connection throughout this function.
		global $db;
		
		# Start assembling our SQL string.
		$sql = 'INSERT INTO definitions (law_id, term, definition, scope, date_created)
				VALUES ';
		
		# Iterate through our definitions to build up our SQL.
		foreach ($this->terms as $term => $definition)
		{
		
			$sql .= '('.$this->law_id.', "'.$db->escape($db->escape($term)).'",
				"'.$db->escape($definition).'", "'.$db->escape($this->scope).'", now())';
			
			# Append a comma if this isn't our last term.
			if (array_pop(array_keys($this->terms)) != $term)
			{
				$sql .= ', ';
			}
			
		}
				
		# Execute the query.
		$result =& $db->exec($sql);
		
		return true;
		
	} // end store_definitions()
	
	
	# Find mentions of other sections within a section and return them as an array.
	function extract_references()
	{
		
		# If we don't have any text to analyze, then there's nothing more to do be done.
		if (!isset($this->text))
		{
			return false;
		}
		
		# Find every instance of "##.##" that fits the acceptable format for a state code citation.
		preg_match_all(SECTION_PCRE, $this->text, $matches);
		
		# We don't need all of the matches data -- just the first set. (The others are arrays of
		# subset matches.)
		$matches = $matches[0];
	
		# We assign the count to a variable because otherwise we're constantly diminishing the
		# count, meaning that we don't process the entire array.
		$total_matches = count($matches);
		for ($j=0; $j<$total_matches; $j++)
		{
			$matches[$j] = trim($matches[$j]);
			
			# Lop off trailing periods, colons, and hyphens.
			if ( (substr($matches[$j], -1) == '.') || (substr($matches[$j], -1) == ':')
				|| (substr($matches[$j], -1) == '-') )
			{
				$matches[$j] = substr($matches[$j], 0, -1);
			}
		}
		
		# Make unique, but with counts.
		$sections = array_count_values($matches);
		unset($matches);
		
		return $sections;
	} // end extract_references()
	
	
	# Take an array of references to other sections contained within a section of text and store
	# them in the database.
	function store_references()
	{
		# If we don't have any section numbers or a section number to tie them to, then we can't
		# do anything at all.
		if ( (!isset($this->sections)) || (!isset($this->section_id)) )
		{
			return false;
		}
		
		# We're going to need access to the database connection throughout this function.
		global $db;
		
		# Start creating our insertion query.
		$sql = 'INSERT INTO laws_references
				(law_id, target_section_number, mentions, date_created)
				VALUES ';
		$i=0;
		foreach ($this->sections as $section => $mentions)
		{
			$sql .= '('.$this->section_id.', "'.$section.'", '.$mentions.', now())';
			$i++;
			if ($i < count($this->sections))
			{
				$sql .= ', ';
			}
		}
		
		# If we already have this record, then just refresh it with a requisite update.
		$sql .= ' ON DUPLICATE KEY UPDATE mentions=mentions';
		
		# Execute the query.
		$result =& $db->exec($sql);
		
		return true;
		
	} // end store_references()
	
	

	
	# Turn the history sections into atomic data.
	function extract_history()
	{
		
		# If we have no history text, then we're done here.
		if (!isset($this->history))
		{
			return false;
		}
		
		# The list is separated by semicolons and spaces.
		$updates = explode('; ', $this->history);
		
		$i=0;
		foreach ($updates as &$update)
		{
			
			# Match lines of the format "2010, c. 402, § 1-15.1"
			$pcre = '/([0-9]{4}), c\. ([0-9]+)(.*)/';
			
			# First check for single matches.
			$result = preg_match($pcre, $update, $matches);
			if ( ($result !== false) && ($result !== 0) )
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
					$result = preg_match(SECTION_PCRE, $update, $matches[3]);
					if ( ($result !== false) && ($result !== 0) )
					{
						$final->{$i}->section = $matches[0];
					}
				}
			}

			# Then check for multiple matches.
			else
			{
				# Match lines of the format "2009, cc. 401,, 518, 726, § 2.1-350.2"
				$pcre = '/([0-9]{2,4}), cc\. ([0-9,\s]+)/';
				$result = preg_match_all($pcre, $update, $matches);
				if ( ($result !== false) && ($result !== 0) )
				{
					# Save the year.
					$final->{$i}->year = $matches[1][0];
					
					# Save the chapter listing. We eliminate any trailing slash and space to avoid
					# saving empty array elements.
					$chapters = rtrim(trim($matches[2][0]), ',');
					
					# We explode on a comma, rather than a comma and a space, because of occasional
					# typographical errors in histories.
					$chapters = explode(',', $chapters);
					
					# Step through each of these chapter references and trim down the leading spaces
					# (a result of creating the array based on commas rather than commas and
					# spaces) and eliminate any that are blank.
					for ($j=0; $j<count($chapters); $j++)
					{
						$chapters[$j] = trim($chapters[$j]);
						if (empty($chapters[$j]))
						{
							unset($chapters[$j]);
						}
					}
					$final->{$i}->chapter = $chapters;
					
					# Locate any section identifier.
					$result = preg_match(SECTION_PCRE, $update, $matches);
					if ( ($result !== false) && ($result !== 0) )
					{
						$final->{$i}->section = $matches[0];
					}
				}
			}
			$i++;
		}
		
		return $final;
	} // end extract_history()
	
} // end Parser class
?>