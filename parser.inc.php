<?php

class Parser
{
	
	# Accept the raw content of a section of code and normalize it.
	public function parse()
	{

		# If a section of code hasn't been passed to this, then it's of no use.
		if (!isset($this->section))
		{
			return false;
		}
		
		# Include HTML Purifier, which we use to clean up the code and character sets.
		require_once(INCLUDE_PATH.'/htmlpurifier/HTMLPurifier.auto.php');
		# Fire up HTML Purifier.
		$purifier = new HTMLPurifier();
		
		# Strip out the whitespace.
		$this->section = trim($this->section);
		
		# For whatever reason, the SGML file from LIS uses a vertical pipe in place of the section
		# symbol. Swap them out.
		$this->section = str_replace('|', '§', $this->section);
		
/*
?			$$PROFILE=COD
section		2.2-2031
ignore		Division of Public Safety Communications established; appointment of Virginia Public Safety Communic
?			00000000001
?			05602.2
?			20.1
concerns?	Virginia Information Technologies Agency
?			N
*/
		
		# Break this section into an array based on newlines.
		$section = explode(PHP_EOL, $this->section);
		
		# The 146th character is where, no matter what, the raw section number appears. Extract
		# that and everything after it.
		$tmp = substr($section[0], 145);
		$tmp = preg_replace('/\s\s+/', '-=-=-=-', $tmp);
		$tmp = explode('-=-=-=-', $tmp);
		
		# If we only have two items present in this array, then we're missing both the chapter title
		# and the chapter number, because neither exist. (It's not that they're in the SGML -- they
		# simply don't exist. In that case, we've got to fake it.
		if (count($tmp) == 2)
		{
			# Reassign named_act to the proper variable.
			$tmp[3] = $tmp[1];
			
			# Give it a chapter number of "1". That's not accurate, but it's *something*.
			$tmp[1] = 1;
			
			# Give the chapter a title of "Untitled."
			$tmp[2] = 'Untitled';
		}
		
		// *Really*? There's no better way to do this?
		$tmp[0] = trim($tmp[0]);
		$tmp[1] = trim($tmp[1]);
		$tmp[2] = trim($tmp[2]);
		$tmp[3] = trim($tmp[3]);
		
		# I don't know what this bit is, but we store it for when we figure that out.
		$code->unknown1 = $tmp[0];
		
		# This bit contains the chapter number.
		$code->chapter_number = $tmp[1];
	
		# Store the chapter name and whether this act has a legislative name.
		$code->chapter_name = $tmp[2];
		$code->named_act = $tmp[3];
		
		unset($tmp);
		
		# Grab everything after this line until we come to "<history>" -- that is, everything within
		# <section> or <table> tags.
		for ($i=1; $i<count($section); $i++)
		{
			if (stristr($section[$i], '<history>') === false)
			{
				$tmp[] = $section[$i];
			}
			else
			{
				# Store this line number so that we can use it later.
				$history_line = $i;
				break;
			}
		}
		
		# Turn that text into an array.
		$tmp = '<code>'.implode(' ', $tmp).'</code>';
		
		# Take any HTML entities and store them as their actual Unicode characters. For instance, we
		# don't want &deg;, we want °.
		$tmp = html_entity_decode($tmp, ENT_QUOTES, 'UTF-8');
		
		# Any ampersands that are left over are stand-alone ampersands, which we'll want to convert
		# into their entity equivalent.
		$tmp = str_replace('&', '&amp;', $tmp);
		
		# We have to deal with <section> and <table> tags. To keep the XML parser happy and keep
		# those appearing in the proper order, we modify all of the <table> tags to make them
		# <section> tags, and we give both a "type" attribute so that we can tell them apart.
		# This is available within $xml as $xml->section->attributes()->type.
		$tmp = str_replace('<table>', '<section type="table">', $tmp);
		$tmp = str_replace('</table>', '</section>', $tmp);
		$tmp = str_replace('<section>', '<section type="section">', $tmp);
		
		# Use HTML Purifier to clean this up. Specifically, we're invoking a function that's meant
		# to clean up SGML, by cleaning up the character set without worrying about the validity of
		# HTML tags. That leaves our SGML unharmed, and our character set tidy, too.
		$tmp = HTMLPurifier_Encoder::cleanUTF8($tmp, $force_php = false);
		
		# And now render that array as a XML tree.
		$xml = new SimpleXMLElement($tmp);
		unset($tmp);

		# Define all five possible section prefixes via via PCRE strings.
		$prefix_candidates = array	('/[A-Z]{1,2}\. /',
									'/[0-9]{1,2}\. /',
									'/[a-z]{1,2}\. /',
									'/\([0-9]{1,2}\) /',
									'/\([a-z]{1,2}\) /');
		
		# Establish a blank prefix structure. We'll build this up and continually modify it to keep
		# track of our current complete section number as we iterate through the code.
		$prefixes = array();
		
		# Establish a blank variable to accrue the full text.
		$code->text = '';
		
		# Deal with each subsection, one at a time.
		for ($i=1; $i<count($xml->section); $i++)
		{
			# Strip out carriage returns and collapse whitespace if this is a regular section. The
			# carriage returns and spaces are meaningful on tables, so we leave them alone.
			if ($xml->section->$i->attributes()->type == 'section')
			{
				$xml->section->$i = str_replace("\n", ' ', $xml->section->$i);
				$xml->section->$i = preg_replace('/\s\s+/', ' ', $xml->section->$i);
			}
			$xml->section->$i = trim($xml->section->$i);
			
			# Append this section's text to the complete text.
			if ($xml->section->$i->attributes()->type == 'section')
			{
				$code->text .= '<p>'.$xml->section->$i.'</p>';
			}
			elseif ($xml->section->$i->attributes()->type == 'table')
			{
				$code->text .= '<pre class="table">'.$xml->section->$i.'</pre>';
			}
			
			# Detect the presence of a subsection prefix -- that is, a letter, number, or series
			# of letters that defines an individual subsection of a law in a hierarchical fashion.
			# The subsection letter can be in one of five formats, listed here from most to least
			# important:
			# 		A. -> 1. -> a. -> (1) -> (a)
			# ...or, rather, this is *often* the order in which they appear. But not always! So we
			# can't rely on this order.
			# When the capital letters run out, they increment like hex: "AB." "AC." etc.
			# When the lowercase letters run out, they double: "aa." "bb." etc.
			
			# Set aside the first five characters in this section of text. That's the maximum number
			# of characters that a prefix can occupy.
			$section_fragment = substr($xml->section->$i, 0, 5);
			
			# Iterate through our possible candidates until we find one that matches (if, indeed,
			# one does at all).
			foreach ($prefix_candidates as $prefix)
			{
			        
				# If this prefix isn't found in this section fragment, then proceed to the next
				# prefix.
				preg_match($prefix, $section_fragment, $matches);
				if (count($matches) == 0)
				{
					continue;
				}
				
				# Great, we've successfully made a match -- we now know that this is the beginning
				# of a new numbered section. First, let's save a platonic ideal of this match.
				$match = trim($matches[0]);
				
				# Now we need to figure out what the entire section number is, only the very end
				# of which is our actual prefix. To start with, we need to modify our subsection
				# structure array to include our current prefix.
				
				# If this is our first time through, then this is easy -- our entire structure
				# consists of the current prefix.
				if (count($prefixes) == 0)
				{
					$prefixes[] = $match;
				}
				
				# But if we already have a prefix stored in our array of prefixes for this section,
				# then we need to iterate through and see if there's a match.
				else
				{
					
					# We must figure out where in the structure our current prefix lives. Iterate
					# through the prefix structure and look for anything that matches the regex that
					# matched our prefix.
					foreach ($prefixes as $key => &$prefix_component)
					{
						# We include a space after $prefix_component because this regex is looking
						# for a space after the prefix, something that would be there when finding
						# this match in the context of a section, but of course we've already
						# trimmed that out of $prefix_component.
						preg_match($prefix, $prefix_component.' ', $matches);
						if (count($matches) == 0)
						{
							continue;
						}
						
						# We've found a match! Update our array to reflect the current section
						# number, by modifying the relevant prefix component.
						$prefix_component = $match;
						
						# Also, set a flag so that we know that we made a match.
						$match_made = true;
						
						# If there are more elements in the array after this one, we need to zero
						# them out. That is, if we're in A4(c), and our last section was A4(b)6,
						# then we need to lop off that "6." So kill everything in the array after
						# this.
						if (count($prefixes) > $key)
						{
							$prefixes = array_slice($prefixes, 0, ($key+1));
						}
					}
					
					# If the $match_made flag hasn't been set, then we know that this is a new
					# prefix component, and we can append it to the prefix array.
					if (!isset($match_made))
					{
						$prefixes[] = $match;
					}
					else
					{
						unset($match_made);
					}
				}		
				
				# Iterate through the prefix structure and store each prefix section in our code
				# object. While we're at it, eliminate any periods.
				for ($j=0; $j<count($prefixes); $j++)
				{
					$code->section->$i->prefix_hierarchy->$j = str_replace('.', '', $prefixes[$j]);
				}
				
				# And store the prefix list as a single string.
				$code->section->$i->prefix = implode('', $prefixes);

			}
			
			# Hack off the prefix at the beginning of the text and save what remains to $code.
			if (isset($code->section->$i->prefix))
			{
				$tmp2 = explode(' ', $xml->section->$i);
				unset($tmp2[0]);
				$code->section->$i->text = implode(' ', $tmp2);
			}
			
			# If we haven't detected a prefix, that's fine -- just save this section as-is.
			elseif (!isset($match))
			{
				$code->section->$i->text = $xml->section->$i;
			}
			
			# Include the type in $code, too.
			$code->section->$i->type = $xml->section->$i->attributes()->type;
			
			# We want to eliminate our matched prefix now, so that we don't mistakenly believe that
			# we've successfully made a match on our next loop through.
			unset($match);
		}
		
		# We don't want to reuse this accidentally.
		unset($tmp);
		
		# The contents of the first section tag are broken into section and name. Pull out the
		# section number.
		preg_match(SECTION_PCRE, $xml->section->{0}, $matches);
		
		# If we have a matched section number, put it to work.
		if (is_array($matches) && (count($matches) > 0) )
		{

			# Save the matched string as the section number.
			$code->section_number = trim($matches[0]);
			if (substr($code->section_number, -1) == '.')
			{
				$code->section_number = substr($code->section_number, 0, -1);
			}
			
			# And save the stuff that isn't the matched string as the name. Since this sometimes
			# includes a newline, and sometimes has double spaces, do a couple of quick substitutions.
			$code->name = trim(str_replace('§ '.$code->section_number.'.', '', $xml->section->{0}));
			$code->name = str_replace("\n", ' ', $code->name);
			$code->name = str_replace('  ', ' ', $code->name);
			
		}
		
		# If we don't have a matched section number, then we're out of luck. We've got to give up.
		else
		{
			return false;
		}
		
		# If the words "repealed effective" appear in the name, or if the string starts with "§
		# through ", or if it's really short and starts with the phrase "repealed by acts," then we
		# mark this section as repealed. Those being the hallmarks of repealed sections. Note that
		# the string length of "128" is used as the threshold for "really short," because that is
		# the greatest length of a section in the Virginia Code that contains the string "Repealed
		# by Acts."
		if (
			(stristr($code->name, 'repealed effective') !== false)
			||
			(strstr($code->name, '§ through ') !== false)
			||
			(
				(substr($code->text, 0, 16) == 'Repealed by Acts')
				&&
				(strlen($code->text) <= 128)
			)
		)
		{
			$code->repealed = 'y';
		}
		
		# Step through each line in the history.
		for ($i=$history_line; $i<count($section); $i++)
		{
			# Save every line that isn't a container tag. This is probably just one line.
			if (stristr($section[$i], 'history>') === false)
			{
				$tmp[] = preg_replace('/\s\s+/', ' ', $section[$i]);
			}
		}
		
		# If we've got any history data at this point.
		if (is_array($tmp))
		{
			
			# Save the finalized history data.
			$tmp = implode(' ', $tmp);
			
			$tmp = trim($tmp);
			
			# Strip out the parentheses that bracket the history if, in fact, they do.
			if ( (substr($tmp, -1) == ')') && (substr($tmp, 0, 1) == '(') )
			{
				$tmp = substr($tmp, 0, -1);
				$tmp = substr($tmp, 1);
			}
			
			# Use HTML Purifier to clean up this history data.
			$tmp = $purifier->purify($tmp);
			
			# Save the finished history data.
			$code->history = $tmp;
		}
			
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
		
		# This first section creates the record for the law, but doesn't do anything with the
		# content of it just yet.
		
		# We're going to need access to the database connection throughout this function.
		global $db;
		
		# Try to create a new chapter. If the chapter already exists, create_chapter() will handle
		# that silently. Either way a chapter ID gets returned.
		$chapter = new Parser;
		$chapter->number = $this->code->chapter_number;
		$chapter->name = $this->code->chapter_name;
		$tmp = explode('-', $this->code->section_number);
		$chapter->title_number = $tmp[0];
		$chapter_id = $chapter->create_chapter();
		if ($chapter_id !== false)
		{
			$query['chapter_id'] = $chapter_id;
			unset($chapter_id);
		}
		unset($chapter->number);
		unset($chapter->name);
		
		# Build up an array of field names and values, using the names of the database columns as
		# the key names.
		$query['catch_line'] = $this->code->name;
		$query['section'] = $this->code->section_number;
		$query['chapter_number'] = $this->code->chapter_number;
		$query['text'] = $this->code->text;
		if (!empty($this->code->unknown1))
		{
			$query['unknown1'] = $this->code->unknown1;
		}
		if (!empty($this->code->named_act))
		{
			$query['named_act'] = $this->code->named_act;
		}
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
		if (PEAR::isError($result))
		{
			echo '<p>'.$sql.'</p>';
			die($result->getMessage());
		}
		
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
			if ($success === false)
			{
				echo '<p>References for section ID '.$law_id.' were found, but could not be
					stored.</p>';
			}
		}
		
		# Step through each section.
		$i=1;
		foreach ($this->code->section as $section)
		{
			# Insert this section of the...uh...section into the text table.
			$sql = 'INSERT INTO text
					SET law_id='.$law_id.',
					sequence='.$i.',
					text="'.$db->escape($section->text).'",
					type="'.$db->escape($section->type).'",
					date_created=now()';

			# Execute the query.
			$result =& $db->exec($sql);
			if (PEAR::isError($result))
			{
				echo '<p>'.$sql.'</p>';
				die($result->getMessage());
			}
		
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
				if (PEAR::isError($result))
				{
					echo '<p>'.$sql.'</p>';
					die($result->getMessage());
				}
				
				$j++;
			}			
			
			
			$i++;
		}
		
		# Trawl through the text for definitions, if the section contains "Definitions" in the title
		# or if the current chapter is the chapter that we defined in the site config as containing
		# the global definitions. We could just confirm that title is exactly "Definitions.", but
		# sometimes it's preceded with other text, e.g. "(Effective July 1, 2012) ".
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
	
	# Step through every line of every file that contains the contents of the code.
	public function iterate()
	{
		
		if (!isset($this->directory))
		{
			$this->directory = getcwd();
		}
		
		# We need to maintain a file counter that will survive instances of this function to keep
		# track of which file we're working on. If it's not already set, set it now.
		if (!isset($this->file))
		{
			$this->file=0;
		}
		
		# Change to the directory.
		chdir($this->directory);
		
		# Get a listing of every file in the directory.
		$files = scandir($this->directory);
		
		# Drop from the array any file name that doesn't start with "code-".
		foreach ($files as $number => $file)
		{
			if (substr($file, 0, 5) !== 'code-')
			{
				unset($files[$number]);
			}
		}
		$files = array_values($files);
		
		# Iterate through every file.
		for ($j = $this->file; $j < count($files); $j++)
		{
			
			$this->file = $j;
			
			$filename = $files[$j];
			
			# Open the file and store its contents as an array.
			$file = file($filename);
			
			if ($file === false)
			{
				die($filename.' could not be opened.');
			}
			
			# Start a counter to track our position in the file. Start with the prior state of this
			# function, if there was one.
			if (isset($this->i))
			{
				$i = $this->i;
			}
			else
			{
				$i = 0;
				$this->i = 0;
			}
			
			# Count how many lines that there are in the file
			$line_count = count($file);
			
			# Iterate through the contents of this file.
			for ($i=$this->i; $i<$line_count; $i++)
			{
				
				$line = $file[$i];
				
				# If the line contains "$$PROFILE" then we're starting a new section of the code.
				if (stristr($line, '$$PROFILE') !== false)
				{
					
					# Send the amassed chunk of code to our parser to deal with it.
					if (isset($section))
					{
						
						# Save this variable to reuse next time, so that we can start again where
						# we left off.
						$this->i = $i;
						
						# Return this section and end the function.
						return $section;
					}
					
					# Start a new instance of the $section variable, beginning with this line.
					$section = $line;
				}
				
				# If this is just a regular line, then append it to the existing section variable.
				elseif (isset($section))
				{
					$section .= $line;
				}
				
				# If we've reached the end of the file.
				if ( ($i+1) == $line_count)
				{
					
					# Reset our line counter.
					$this->i = 0;
					$i = 0;
					
					# Stop iterating through this file, break out to the file foreach loop,
					# and continue with the next file.
					break;
				}
				
			} // end iterating through the contents of a file
		} // end iterating through files
	} // end iterate() function
	
	# When provided with a chapter number, verifies whether that chapter exists. Returns the chapter
	# ID if it exists; otherwise, returns false.
	public function chapter_exists()
	{
		if (!isset($this->code->chapter_number))
		{
			return false;
		}
		
		# Assemble the query.
		$sql = 'SELECT id
				FROM chapters
				WHERE number="'.$this->code->chapter_number.'"';
		
		# Execute the query.
		$result =& $db->query($sql);
		
		# If the query fails, or if no results are found, return false -- we can't make a match.
		if ( PEAR::isError($result) || ($result->numRows() < 1) )
		{
			return false;
		}
		
		$chapter = $result->fetchRow();
		return $chapter['id'];
	}
	
	# When provided with a chapter number, verifies whether that chapter exists. Returns the chapter
	# ID, if successful; otherwise returns false.
	public function create_chapter()
	{
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
		
		# We're going to need access to the database connection within this function.
		global $db;
			
		# Insert this chapter record into the database. We use ON DUPLICATE KEY so that this can
		# be run without first invoking chapter_exists().
		$sql = 'INSERT INTO chapters
				SET number="'.$db->escape($this->number).'",';
		if (isset($this->name) && !empty($this->name))
		{
			$sql .= 'name="'.$db->escape($this->name).'",';
		}
		$sql .= 'date_created=now(), title_id=
					(SELECT id
					FROM titles
					WHERE number="'.$db->escape($this->title_number).'")
				ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)';
				
		# Execute the query.
		$result =& $db->exec($sql);
		if (PEAR::isError($result))
		{
			return false;
		}
	
		# Return 
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
				   )
				{
				
					# Extract every word in quotation marks in this paragraph as a term that's being
					# defined here. Most definitions will have just one term being defined, but some
					# will have two or more.
					// Isn't this too broad? How can we narrow the scope?
					// We're getting words between quotation marks, such as the word "or" in the
					// passage "'alpha' or 'bravo'". Also, this is too greedy. Or something. The
					// matching for lists of defined words is just weird.
					preg_match_all('/"([A-Za-z]{1})([A-Za-z,\'\s]*)([A-Za-z]{1})"/', $paragraph, $terms);
					
					# If we've made any matches.
					if ( ($terms !== false) && (count($terms) > 0) )
					{
						
						# We only need the first element in this multi-dimensional array, which has
						# the actual matched term. It includes the quotation marks in which the term
						# is enclosed, so we strip those out.
						$terms = str_replace('"', '', $terms[0]);
						
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
		$tmp = $definitions;
		$definitions['terms'] = $tmp;
		$definitions['scope'] = $scope;
			
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
		if (PEAR::isError($result))
		{
			echo $sql;
			return false;
		}
		
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
				(section_id, target_section_number, mentions, date_created)
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
		if (PEAR::isError($result))
		{
			echo '<p>Failed: '.$sql.'</p>';
			return false;
		}
		
		return true;
		
	} // end store_references()
	
} // end Parser class
?>
