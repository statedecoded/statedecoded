<?php

# Get the contents of a given URL. A wrapper for cURL.
function fetch_url($url)
{
	if (!isset($url))
	{
		return false;
	}
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
	curl_setopt($ch, CURLOPT_TIMEOUT_MS, 1200);
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
	$html = curl_exec($ch);
	curl_close($ch);
	return $html;
}

# This is used as the preg_replace_callback function that inserts section links into text.
function replace_sections($matches)
{

	# PCRE provides an array-based match listing. We only want the first one.
	$match = $matches[0];
	
	# If the section symbol prefixes this match, hack it off.
	if (substr($match, 0, strlen(SECTION_SYMBOL)) == SECTION_SYMBOL)
	{
		$match = substr($match, (strlen(SECTION_SYMBOL.' ')));
	}

	# Create an instance of the Law class.
	$law = new Law;
	
	# Just find out if this law exists.
	$law->section_number = $match;
	$section = $law->exists();
	
	# If this isn't a valid section number, then just return the match verbatim -- there's no link
	# to be provided.
	if ($section === false)
	{
		return $matches[0];
	}
	
	return '<a class="section" href="/'.$match.'/">'.$matches[0].'</a>';
}

class Autolinker
{
	# Make these arrays available so that we can manipulate them, if need be. There's no need to
	# feed these to Autolinker directly, because under real-world circumstances, these can always be
	# plucked from the globals.
	function __construct()
	{
		global $terms;
		$this->terms = $terms;
		$this->term_blacklist = array();
	}
	
	# This is used as the preg_replace_callback function that inserts dictionary links into text.
	function replace_terms($term)
	{
		
		if (!isset($term))
		{
			return false;
		}
		
		# If the provided term is an array of terms, just use the first one. This might seem odd,
		# but note that this function is written to be used within preg_replace_callback(), the PCRE
		# provides an array-based word listing, and we only want the first one.
		if (is_array($term))
		{
			$term = $term[0];
		}
		
		# If we have already marked this term as blacklisted -- that is, as a word that is a subset
		# of a longer term -- then just return the term without marking it as a dictionary term.
		if (in_array(strtolower($term), $this->term_blacklist))
		{
			return $term;
		}
	
		# Determine whether this term is made up of multiple words, so that we can eliminate any
		# terms from our arrays of terms that are any of the individual words that make up this
		# term. That is, if this term is "person or people," and "person" is another term in our
		# array, then we want to drop "person," to avoid display overlapping terms.
		$num_spaces = substr_count($term, ' ');
		if ($num_spaces > 0)
		{

			# Use that separator to break the term up into an array of words.
			$term_components = explode(' ', $term);
			
			# Step through each the the words that make up this phrase, and add each of them to
			# the blacklist, so that we can skip this word next time it appears in this law.
			foreach ($term_components as $word)
			{
				$this->term_blacklist[] = strtolower($word);
			}
			
			# Now step through each two-word sub-phrase that make up this 3+-word phrase (assuming
			# that there are any) and add each of them to the blacklist.
			if ($num_spaces > 1)
			{
				for ($i=0; $i<$num_spaces; $i++)
				{
					$this->term_blacklist[] = strtolower($term_components[$i].' '.$term_components[$i+1]);
				}
			}
		}

		return '<span class="dictionary">'.$term.'</span>';
	}
}

# Send an error message formatted as JSON. This requires the text of an error message.
function json_error($text)
{
	if (!isset($text))
	{
		return false;
	}
	$error = array('error',
		array(
			'message' => 'An Error Occurred',
			'details' => $text
		)
	);
	$error = json_encode($error);
	
	# Return a 400 "Bad Request" error. This indicates that the request was invalid. Whether this is
	# the best HTTP header is unclear.
	header("HTTP/1.0 400 OK");
	# Send an HTTP header defining the content as JSON.
	header('Content-type: application/json');
	echo $error;
}

function send_404()
{
	include('/var/www/vacode.org/htdocs/404.php');
	exit();
}

# This is relied on by usort() in law.php.
function sort_by_length($a, $b)
{
	return strlen($b) - strlen($a);
}

class Law
{
		
	# Retrieve ID (by section #)
	function get_id()
	{
		# If a section number hasn't been passed to this function, then there's nothing to do.
		if (!isset($this->section_number))
		{
			return false;
		}
		
		# Erase any instance of a section symbol.
		$this->section_number = str_replace(SECTION_SYMBOL, '', $this->section_number);
		
		# Trim it down.
		$this->section_number = trim($this->section_number);
		
		# Query the database for the ID for this section number, retrieving the current version
		# of the law.
		$sql = 'SELECT id
				FROM laws
				WHERE section="'.$db->escape($this->section_number).'"
				AND edition_id='.EDITION_ID;
		
		# Execute the query.
		$result =& $db->query($sql);
		
		# If the query fails, or if no results are found, return false -- we can't make a match.
		if ( PEAR::isError($result) || ($result->numRows() < 1) )
		{
			return false;
		}
		
		$law = $result->fetchRow();
		return $law['id'];
	}
	
	# Retrieve all of the material relevant to a given law.
	function get_law()
	{

		# We're going to need access to the database connection throughout this class.
		global $db;
		
		# If neither a section number nor a law ID has been passed to this function, then there's
		# nothing to do.
		if (!isset($this->section_number) && !isset($this->law_id))
		{
			return false;
		}
		
		# Define the level of detail that we want from this method. By default, we return
		# everything that we have for this law. But if any specific 
		if ( !isset($this->config) || ($this->config->get_all == TRUE) )
		{
			$this->config->get_text = TRUE;
			$this->config->get_structure = TRUE;
			$this->config->get_amendment_attempts = TRUE;
			$this->config->get_court_decisions = TRUE;
			$this->config->get_metadata = TRUE;
			$this->config->get_references = TRUE;
			$this->config->get_related_laws = TRUE;
		}
		
		# Assemble the query that we'll use to get this law.
		$sql = 'SELECT id, structure_id, section AS section_number, catch_line, history,
				text AS full_text, repealed
				FROM laws';
		
		# If we're requesting a specific law by ID.
		if (isset($this->law_id))
		{
			# If it's just a single law ID, then just request the one.
			if (!is_array($this->law_id))
			{
				$sql .= ' WHERE id='.$db->escape($this->law_id);
			}
			
			# But if it's an array of law IDs, request all of them.
			elseif (is_array($this->law_id))
			{
				$sql .= ' WHERE (';

				# Step through the list.
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
		
		# Else if we're requesting a law by section number, then make sure that we're getting the
		# law from the newest edition of the laws.
		else
		{
			$sql .= ' WHERE section="'.$db->escape($this->section_number).'"
					AND edition_id='.EDITION_ID;
		}
		
		# Execute the query.
		$result =& $db->query($sql);
		
		# If the query fails, or if no results are found, return false -- we can't make a match.
		if ( PEAR::isError($result) || ($result->numRows() < 1) )
		{
			return false;
		}
		
		# Return the result as an object.
		$law = $result->fetchRow(MDB2_FETCHMODE_OBJECT);
		
		# Make the section ID available within the scope of this class, so that we can use it in
		# other functions.
		$this->section_id = $law->id;
		
		# Now get the text for this law.
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
					WHERE law_id='.$db->escape($law->id).'
					ORDER BY text.sequence ASC';
			
			# Execute the query.
			$result =& $db->query($sql);
			
			# If the query fails, or if no results are found, return false -- we can't make a match.
			if ( PEAR::isError($result) || ($result->numRows() < 1) )
			{
				return false;
			}
			
			# Iterate through all of the sections of text to save to our object.
			$i=0;
			while ($tmp = $result->fetchRow(MDB2_FETCHMODE_OBJECT))
			{
				$tmp->prefixes = explode('|', $tmp->prefixes);
				$tmp->prefix = end($tmp->prefixes);
				$tmp->entire_prefix = implode('', $tmp->prefixes);
				$tmp->prefix_anchor = str_replace(' ', '_', $tmp->entire_prefix);
				$tmp->level = count($tmp->prefixes);
		
				# Pretty it up, converting all straight quotes into directional quotes, double dashes
				# into em dashes, etc.
				$tmp->text = wptexturize($tmp->text);
				
				# Append this section.
				$law->text->$i = $tmp;
				$i++;
			}
		}
		
		# Determine this law's structural position.
		if ($this->config->get_structure == TRUE)
		{
			# Create a new instance of the Structure class.
			$struct = new Structure;
	
			# Our structure ID provides a starting point to identify this law's ancestry.
			$struct->id = $law->structure_id;
			
			# Save the law's ancestry.
			$law->ancestry = $struct->id_ancestry();
			
			# Short of a parser error, there’s no reason why a law should not have an ancestry. In
			# case of this unlikely possibility, just erase the false element.
			if ($law->ancestry === false)
			{
				unset($law->ancestry);
			}
			
			# Get the listing of all other sections in the structural unit that contains this section.
			$law->structure_contents = $struct->list_laws();
			
			# Figure out what the next and prior sections are (we may have 0-1 of either). Iterate
			# through all of the contents of the chapter.
			for ($i=0; $i<count((array) $law->structure_contents); $i++)
			{
				# When we get to our current section, that's when we get to work.
				if ($law->structure_contents->$i->id == $law->id)
				{
					$j = $i-1;
					$k = $i+1;
					if (isset($law->structure_contents->$j))
					{
						$law->previous_section = $law->structure_contents->$j;
					}
					
					if (isset($law->structure_contents->$k))
					{
						$law->next_section = $law->structure_contents->$k;
					}
					break;
				}
			}
		}
		
		# Gather all metadata stored about this law.
		if ($this->config->get_metadata == TRUE)
		{
			$law->metadata = Law::get_metadata();
		}
		
		# If this state has its own State class, then we can potentially use some of its methods.
		if (class_exists('State'))
		{
		
			# Create a new instance of the State() class.
			$state = new State();
			$state->section_id = $law->id;
			$state->section_number = $law->section_number;
			
			# Get the amendation attempts for this law and include those (if there are any). But only
			# if we haven't specifically indicated that we don't want it. The idea behind skipping this
			# is that it's calling from Richmond Sunlight, which is reasonable for internal purposes,
			# but it's not sensible for our own API to make a call to another site's API.
			if ($this->config->get_amendment_attempts == TRUE)
			{
				if (method_exists($state, 'get_amendment_attempts'))
				{
					$law->amendment_attempts = $state->get_amendment_attempts();
				}
			}

			# Get the court decisions for this law and include those (if there are any).
			if ($this->config->get_court_decisions == TRUE)
			{
				if (method_exists($state, 'get_court_decisions'))
				{
					$law->court_decisions = $state->get_court_decisions();
				}
			}
		
			# Get the URL for this law on its official state web page.
			if (method_exists($state, 'official_url'))
			{
				$law->official_url = $state->official_url();
			}
			
			# Translate the history of this law into plain English.
			if (method_exists($state, 'translate_history'))
			{
				if (isset($law->metadata->history))
				{
					$state->history = $law->metadata->history;
					$law->history_text = $state->translate_history();
				}
			}
			
		} // end class_exists('State')
		
		# Get the references to this law among other laws and include those (if there are any).
		if ($this->config->get_references == TRUE)
		{
			$law->references = Law::get_references();
		}
		
		if ($this->config->get_related_laws == TRUE)
		{
			$law->related = Law::get_related();
		}

		# Extract every year named in the history.
		preg_match_all('/(18|19|20)([0-9]{2})/', $law->history, $years);
		if (count($years[0]) > 0)
		{
			$i=0;
			foreach ($years[0] as $year)
			{
				$law->amendation_years->$i = $year;
				$i++;
			}
		}
		
		# Pretty up the text for the catch line.
		$law->catch_line = wptexturize($law->catch_line);
		
		# Provide the URL for this section.
		$law->url = 'http://'.$_SERVER['SERVER_NAME'].'/'.$law->section_number.'/';
		
		# Assemble the citations.
		$law->citation->official = 'Va. Code §&nbsp;'.$law->section_number.' ('.end($law->amendation_years).')';
		$law->citation->universal = 'VA Code §&nbsp;'.$law->section_number.' ('.end($law->amendation_years)
			.' through Reg Sess)';
		
		# Return the result.
		return $law;
	}
	
	
	# Return a listing of every section of the code that refers to a given section.
	function get_references()
	{
				
		# We're going to need access to the database connection throughout this class.
		global $db;
		
		# If a section number doesn't exist in the scope of this class, then there's nothing to do.
		if (!isset($this->section_id))
		{
			return false;
		}
		
		# Get a listing of IDs, section numbers, and catch lines.
		$sql = 'SELECT DISTINCT laws.id, laws.section, laws.catch_line
				FROM laws
				INNER JOIN laws_references
					ON laws.id = laws_references.law_id
				WHERE laws_references.target_law_id =  '.$db->escape($this->section_id).'
				ORDER BY laws.order_by, laws.section ASC';
		
		# Execute the query.
		$result =& $db->query($sql);
		
		# If the query fails, or if no results are found, return false -- no sections refer to
		# this one.
		if ( PEAR::isError($result) || ($result->numRows() < 1) )
		{
			return false;
		}
		
		# Return the result as an object.
		$references = $result->fetchAll(MDB2_FETCHMODE_OBJECT);
		
		# Iterate through the decisions so that we can strip out slashes.
		foreach($references as &$reference)
		{
			$reference->catch_line = stripslashes($reference->catch_line);
			$reference->url = 'http://'.$_SERVER['SERVER_NAME'].'/'.$reference->section.'/';
			$reference->section = SECTION_SYMBOL.'&nbsp;'.$reference->section;
		}
		
		return $references;
	}
	
	# Record a view of a single law.
	function record_view()
	{
	
		# We're going to need access to the database connection throughout this class.
		global $db;
		
		# If a section number doesn't exist in the scope of this class, then there's nothing to do.
		if (!isset($this->section_id))
		{
			return false;
		}
		
		# Record the view.
		$sql = 'INSERT DELAYED INTO laws_views
				SET law_id='.$this->section_id;
		if (!empty($_SERVER['REMOTE_ADDR']))
		{
			$sql .= ', ip_address=INET_ATON("'.$_SERVER['REMOTE_ADDR'].'")';
		}
		
		# Execute the query.
		$result =& $db->exec($sql);
		
		# If the query fails, return false.
		if (PEAR::isError($result))
		{
			return false;
		}
		
		return true;
	}
	
	# Get all metadata for a single law.
	function get_metadata()
	{
	
		# We're going to need access to the database connection throughout this class.
		global $db;
		
		# If a section number doesn't exist in the scope of this class, then there's nothing to do.
		if (!isset($this->section_id))
		{
			return false;
		}
		
		# Get a listing of all metadata that belongs to this law.
		$sql = 'SELECT id, meta_key, meta_value
				FROM laws_meta
				WHERE law_id='.$db->escape($this->section_id);
		
		# Execute the query.
		$result =& $db->query($sql);
		
		# If the query fails, or if no results are found, return false -- no sections refer to
		# this one.
		if ( PEAR::isError($result) || ($result->numRows() < 1) )
		{
			return false;
		}
		
		# Return the result as an object.
		$metadata = $result->fetchAll(MDB2_FETCHMODE_OBJECT);
		
		# Create a new object, to which we will port a rotated version of this object.
		$rotated = new stdClass();
		
		# Iterate through the object in order to reorganize it, assigning the meta_key field to the
		# key and the meta_value field to the value.
		foreach($metadata as $field)
		{
			$new->{stripslashes($field->meta_key)} = unserialize(stripslashes($field->meta_value));
		}
		
		return $new;
	# Get a collection of the 5 sections most similar to the present law.
	function get_related()
	{
		
		Solarium_Autoloader::register();

		# Create a client instance.
		$client = new Solarium_Client();
		
		# Get a morelikethis query instance.
		$query = $client->createMoreLikeThis();
		
		# Add a query and morelikethis settings. Note that this search MUST be performed against
		# the "law_location" field. There are two other candidate fields at this writing
		# (law_section and law_code), but neither of those yield correct matches. A search for
		# "law_code:18.2-30" turns up "8.01-130" as the first result, a number that is very similar
		# to 18.2-30, but the result is wildly different -- useless -- related results.
		#
		# Note that we have to escape colons in this query.
		$query->setQuery('law_location:'.str_replace(':', '\:', $this->section_number));
		$query->setMltFields('law_text,tags,law_title');
		$query->setMatchInclude(true);
		$query->setStart(0)->setRows(5);
		
		# Execute the query and return the result.
		$results = $client->select($query);
		
		# Create a new, blank object to store our related sections.
		$related = new StdClass();
		
		# Iterate through the returned documents
		$i=0;
		foreach ($results as $document)
		{
			$related->{$i}->id = $document->id;
			$related->{$i}->catch_line = $document->law_title;
			$related->{$i}->section_number = $document->law_section;
			$related->{$i}->text = $document->law_text;
			$related->{$i}->url = '/'.$document->law_section.'/';
			$i++;
		}
		
		return $related;
	}
	
	}
}


class Structure
{

	# Takes a URL, returns an object all about that structural component. This isn't for laws,
	# but for the containing units (titles, chapters, parts, etc.). It can be fed a URL or, if not,
	# it'll just use the requested URL.
	function url_to_structure()
	{
		# We're going to need access to the database connection throughout this class.
		global $db;
		
		# If we haven't been provided with a URL, let's just assume that it's the current one.
		if (!isset($this->url))
		{
			# We can safely prepend "http://" because we're really only interested in the path
			# component of the URL -- the protocol will be ignored.
			$this->url = 'http://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
		}
		
		# Make sure that this URL is kosher.
		$this->url = filter_var($this->url, FILTER_SANITIZE_URL);
		if ($this->url === false)
		{
			return false;
		}
		
		# We don't actually want the whole URL, but just the path.
		$tmp = parse_url($this->url);
		$this->path = $tmp['path'];
		
		# Turn the URL into an array.
		$components = explode('/', $this->path);

		# Leading and trailing slashes in the path result in blank array elements. Remove them.
		foreach ($components as $key => $component)
		{
			if (empty($component))
			{
				unset($components[$key]);
			}
		}
		
		# Turn the sidewide structure listing (just a string, separated by commas, of the basic
		# units of this legal code, from broadest—e.g., title—to the narrowest—e.g. article) into
		# an array.
		$structure = explode(',', STRUCTURE);
		
		# If our structure is longer than the URL components (as it very often will be), hack off
		# the structure array elements that we don't need.
		if (count($structure) > count($components))
		{
			$structure = array_slice($structure, 0, count($components));
		}
		
		# Merge the structure and component arrays.
		$tmp = array_combine($structure, $components);
		$tmp = array_reverse($tmp);
		unset($structure);
		unset($components);
		$structure = $tmp;
		unset($tmp);
		
		# Assemble the query by which we'll retrieve this URL's pedigree from the structure table.
		$sql = 'SELECT ';
		
		# First, come up with the listing of tables and fields that we'll query.
		$select = '{table}.id as {table}_id, {table}.number AS {table}_number,
					{table}.name AS {table}_name, {table}.label AS {table}_label,
					{table}.parent_id AS {table}_parent_id,';

		for ($i=1; $i<=count($structure); $i++)
		{
			$sql .= str_replace('{table}', 's'.$i, $select);
		}
		
		# Remove the trailing comma.
		$sql = rtrim($sql, ',');
		
		# Second, come up with the list of joins that we're using (joining the structure table on
		# itself) to get what might be multiple levels of data.
		reset($structure);
		$i=1;
		foreach ($structure as $type => $number)
		{
			# If this is our first iteration through, start our FROM statement.
			reset($structure);
			if (key($structure) == $type)
			{
				$sql .= ' FROM structure AS s'.$i;
			}
			
			# If this isn't our first iteration, through, create joins.
			else
			{
				# Because we've already reset the array's pointer, we need to restore it to its
				# proper position, and then back it up one, to determine the parent of this current
				# section.
				while ($type != key($structure))
				{
					next($structure);
				}
				prev($structure);
				$sql .= ' LEFT JOIN structure AS s'.$i.'
					ON ';
				$sql .= 's'.($i-1).'.parent_id=s'.$i.'.id';
			}
			$i++;
		}
		
		# Third, and finally, create the WHERE portion of our SQL query.
		$sql .= ' WHERE ';
		$i=1;
		foreach ($structure as $type => $number)
		{
			$where[] = 's'.$i.'.number="'.$db->escape($number).'" AND s'.$i.'.label="'.$db->escape($type).'"';
			$i++;
		}
		
		$sql .= implode(' AND ', $where);
		
		# We don't need either of these anymore.
		unset($where);
		unset($structure);

		# Execute the query.
		$result =& $db->query($sql);
		
		# If the query fails, or if no results are found, return false -- we can't make a match.
		if ( PEAR::isError($result) || ($result->numRows() < 1) )
		{
			return false;
		}
		
		# Get the result as an object.
		$structure_row = $result->fetchRow(MDB2_FETCHMODE_OBJECT);
		
		# Pivot this into a multidimensional object. That is, it's presently stored in multiple
		# columns in a single row, but we want it in multiple rows, one per hierarhical level.
		$structure = new stdClass();
		foreach($structure_row as $key => $value)
		{
			$value = stripslashes($value);
			
			# Determine the table prefix name, so that we can use the number contained within it as
			# the object element name.
			$tmp = explode('_', $key);
			$tmp = $tmp[0];
			$prefix = str_replace('s', '', $tmp);
			unset($tmp);
			
			# Strip out the table prefix from the key name.
			$key = preg_replace('/s[0-9]_/', '', $key);
			
			$structure->{$prefix-1}->$key = $value;
		}
		
		
		# Reverse the order of the elements of this object and place it in the scope of $this.
		$j=0;
		for ($i=count((array) $structure)-1; $i>=0; $i--)
		{
			$this->structure->{$j} = $structure->{$i};
			$j++;
		}
		unset($structure);
		
		# Iterate through the levels and build up the URLs recursively.
		$url_prefix = 'http://'.$_SERVER['SERVER_NAME'].'/';
		$url_base = '';
		foreach ($this->structure as &$level)
		{
			$url_suffix .= $level->number.'/';
			$level->url = $url_prefix.$url_suffix;
		}
		
		# We set these two variables for the convenience of other functions in this class.
		$tmp = end($this->structure);
		$this->id = $tmp->id;
		$this->label = $tmp->label;
		$this->name = $tmp->name;
		$this->number = $tmp->number;
		$this->parent_id = $tmp->parent_id;
		unset($tmp);
		
		return true;
	}
	
	# Get all of the metadata for the specified structural element (title, chapter, etc.).
	function get_current()
	{
		# We're going to need access to the database connection throughout this class.
		global $db;
		
		# If we don't have an ID of the structure element that we're looking for, then there's
		# really nothing for us to do here.
		if (!isset($this->structure_id))
		{
			//throw new Exception('No structure ID provided.');
			return false;
		}
		
		$sql = 'SELECT id, name, number, label, parent_id
				FROM structure
				WHERE';
			
		# If we've got a title ID, use that.
		if (isset($this->id))
		{
			$sql .= ' id="'.$db->escape($this->id).'"';
		}
		
		# Execute the query.
		$result =& $db->query($sql);
		
		# If the query fails, or if no results are found, return false -- we can't make a match.
		if ( PEAR::isError($result) || ($result->numRows() < 1) )
		{
			return false;
		}
		
		# Get the result as an object.
		$this->structure = $result->fetchRow(MDB2_FETCHMODE_OBJECT);
		
		foreach($this->structure as &$tmp)
		{
			$tmp = stripslashes($tmp);
		}
		
		return $structure;
		
	}
	
	# List all of the children of the current structural element. If $this->id is populated, then
	# that is that used as the parent ID. If it is not populated, then the function will return all
	# top level (parentless) structural elements.
	function list_children()
	{

		# We're going to need access to the database connection throughout this class.
		global $db;
		
		# Assemble the SQL query. The subselect is to avoid getting structural units that contain
		# only repealed (that is, unlisted) laws.
		$sql = 'SELECT structure_unified.*
				FROM structure
				LEFT JOIN structure_unified
					ON structure.id = structure_unified.s1_id
				WHERE structure.parent_id';
		if (!isset($this->id))
		{
			$sql .= ' IS NULL';
		}
		else
		{
			$sql .= '='.$db->escape($this->id);
			
			# If this legal code continues to print repealed laws, then make sure that we're not
			# displaying any structural units that consist entirely of repealed laws.
			if (INCLUDES_REPEALED === true)
			{
				$sql .= ' AND
						(SELECT COUNT(*)
						FROM laws
						WHERE structure_id=structure.id
						AND laws.repealed="n") > 0';
			}

		}		$sql .= ' ORDER BY ';
		
		# We may sort by either Roman numerals or Arabic (traditional) numerals. 
		if ($this->sort == 'roman')
		{
			$sql .= 'fromRoman(structure.number) ASC';
		}
		else
		{
			$sql .= 'ABS(SUBSTRING_INDEX(structure.number, ".", 1)) ASC, ABS(SUBSTRING_INDEX(structure.number, ".", -1)) ASC';
		}
		
		# Execute the query.
		$result =& $db->query($sql);
		
		# If the query fails, or if no results are found, return false -- we can't make a match.
		if ( PEAR::isError($result) || ($result->numRows() < 1) )
		{
			return false;
		}
		
		# Return the result as an object, built up as we loop through the results.
		$i=0;
		while ($child = $result->fetchRow(MDB2_FETCHMODE_OBJECT))
		{
			# Remap the structural column names to simplified column names.
			$child->id = $child->s1_id;
			$child->label = $child->s1_label;
			$child->name = $child->s1_name;
			$child->number = $child->s1_number;
			
			# Figure out the URL for this structural unit by iterating through the "number" columns
			# in this row.
			$child->url = 'http://'.$_SERVER['SERVER_NAME'].'/';
			$tmp = array();
			foreach ($child as $key => $value)
			{
				if (preg_match('/s[0-9]_number/', $key) == 1)
				{
					# Higher-level structural elements (e.g., titles) will have blank columns in
					# structure_unified, so we want to omit any blank values.
					if (!empty($value))
					{
						$tmp[] = $value;
					}
				}
			}
			$tmp = array_reverse($tmp);
			$child->url .= implode('/', $tmp).'/';
			$children->$i = $child;
			$i++;
		}
		
		return $children;
		
	}
	
	# Get a structure ID's ancestry. For example, when given the ID of a chapter, it will return
	# the chapter's ID, number, and name, along with its containing title's ID, number, and name.
	function id_ancestry()
	{

		# We're going to need access to the database connection throughout this class.
		global $db;
		
		# If a structural ID hasn't been passed to this function, then there's nothing to do.
		if (!isset($this->id))
		{
			return false;
		}
		
		# We use SELECT * because it's ultimately more efficient. That's because structure_unified
		# has a number of columns that varies between states. We could determine how many columns
		# based on the number of values in the STRUCTURE constant, or by first querying the
		# structure of the table, and that might be a worthy modification at some point. But, for
		# now, this will do.
		$sql = 'SELECT *
				FROM structure_unified
				WHERE s1_id='.$this->id;

		# Execute the query.
		$result =& $db->query($sql);
		
		# If the query fails, or if no results are found, return false -- we can't make a match.
		if ( PEAR::isError($result) || ($result->numRows() < 1) )
		{
			return false;
		}
		
		$structure = $result->fetchRow(MDB2_FETCHMODE_OBJECT);

		# Create a new, blank object.
		$ancestry = new stdClass();
		
		# Iterate through $structure, cell by cell.
		foreach ($structure as $column => $cell)
		{
			# Some of the fields in our structure_unified table are going to be empty -- that's just
			# how it works. We're not interested in these fields, so we omit them.
			if (empty($cell))
			{
				continue;
			}
			
			# The first three characters of the column name are the prefix.
			$prefix = substr($column, 0, 2);
			
			# Strip out everything but the number.
			$prefix = preg_replace('/[^0-9]/', '', $prefix);
			
			# Assign this datum to an element within $tmp based on its prefix.
			$label = substr($column, 3);
			$ancestry->$prefix->$label = $cell;
		}
		
		# To assign URLs, we iterate through the object in reverse, and build up the URLs from their
		# structure numbers.
		$url = '/';
		foreach (array_reverse((array) $ancestry) as $key => $level)
		{
			$url .= $level->number.'/';
			$ancestry->$key->url = $url;
		}
		
		unset($structure);
		unset($label);
		unset($cell);
		unset($row);
		return $ancestry;
		
	}
	
	# Convert a structure ID to its number.
	function id_to_number()
	{

		# We're going to need access to the database connection throughout this class.
		global $db;
		
		# If a structural ID hasn't been passed to this function, then there's nothing to do.
		if (!isset($this->id))
		{
			return false;
		}
		
		# Assemble the SQL query. Only get sections that haven't been repealed.
		$sql = 'SELECT number
				FROM structure
				WHERE id='.$db->escape($this->id);
		
		# Execute the query.
		$result =& $db->query($sql);
		
		# If the query fails, or if no results are found, return false -- we can't make a match.
		if ( PEAR::isError($result) || ($result->numRows() < 1) )
		{
			return false;
		}
		
		$structure = $result->fetchRow(MDB2_FETCHMODE_OBJECT);
		
		return $structure->number;
	}

	# Get a listing of all laws for a given structural element.
	function list_laws()
	{

		# We're going to need access to the database connection throughout this class.
		global $db;
		
		# If a structural ID hasn't been passed to this function, then there's nothing to do.
		if (!isset($this->id))
		{
			return false;
		}
		
		# Assemble the SQL query. Only get sections that haven't been repealed. We order by the
		# order_by field primarily, but we also order by section as a backup, in case something
		# should fail with the order_by field. The section column is not wholly reliable for sorting
		# (hence the order_by field), but it's a great deal better than an unsorted list.
		$sql = 'SELECT id, structure_id, section AS number, catch_line
				FROM laws
				WHERE structure_id='.$db->escape($this->id).' AND repealed="n"
				ORDER BY order_by, section';
		
		# Execute the query.
		$result =& $db->query($sql);
		
		# If the query fails, or if no results are found, return false -- we can't make a match.
		if ( PEAR::isError($result) || ($result->numRows() < 1) )
		{
			return false;
		}
		
		# Create a new, empty class.
		$laws = new stdClass();
		
		# Return the result as an object, built up as we loop through the results.
		$i=0;
		while ($section = $result->fetchRow(MDB2_FETCHMODE_OBJECT))
		{
			# Figure out the URL and include that.
			$section->url = 'http://'.$_SERVER['SERVER_NAME'].'/'.$section->number.'/';
			
			# Sometimes there are laws that lack titles. We've got to put something in that field.
			if (empty($section->catch_line))
			{
				$section->catch_line = '[Untitled]';
			}
			
			$laws->$i = $section;
			$i++;
		}
		return $laws;
	}
}


class Page
{
	
	# If we haven't defined which template that we want, then just use the standard page template.
	public $template = 'page';
	
	# A shortcut for all steps necessary to turn variables into an output page.
	function parse()
	{
		Page::render();
		Page::display();
	}
	
	# Combine the populated variables with the template.
	function render()
	{
		# Save the contents of the template file to a variable.
		$this->html = file_get_contents(TEMPLATE_PATH.'/'.$this->template.'.inc.php');
		
		# Create the browser title.
		if (!isset($field->browser_title))
		{
			if (isset($field->page_title))
			{
				$field->browser_title .= $field->page_title;
				$field->browser_title .= '—'.SITE_TITLE;
			}
			else
			{
				$field->browser_title .= SITE_TITLE;
			}
		}
		else
		{
			$field->browser_title .= '—'.SITE_TITLE;
		}
		
		# Replace all of our in-page tokens with our defined variables.
		foreach ($this->field as $field=>$contents)
		{
			$this->html = str_replace('{{'.$field.'}}', $contents, $this->html);
		}
		
		# Erase any unpopulated tokens that remain in our template.
		$this->html = preg_replace('/{{[0-9a-z_]+}}/', '', $this->html);
		
		# Erase selected containers, if they're empty.
		$this->html = preg_replace('/<section id="sidebar">(\s*)<\/section>/', '', $this->html);
		$this->html = preg_replace('/<nav id="intercode">(\s*)<\/nav>/', '', $this->html);
		$this->html = preg_replace('/<nav id="breadcrumbs">(\s*)<\/nav>/', '', $this->html);
	}
	
	# Send the page to the browser.
	function display()
	{
		if (!isset($this->html))
		{
			return false;
		}
		
		echo $this->html;
		return true;
	}
}


class Dictionary
{
	
	# Get the definition for a given term for a given section of code.
	function define_term()
	{

		# We're going to need access to the database connection throughout this class.
		global $db;
		
		# If no term has been defined, there is nothing to be done.
		if (!isset($this->term))
		{
			return false;
		}
		
		# We want to check if the term is in all caps. If it is, then we want to keep it in all
		# caps to query the database. Otherwise, we lowercase it. That is, "Board" should be looked
		# up as "board," but "NAIC" should be looked up as "NAIC."
		for ($i=0; $i<strlen($this->term); $i++)
		{
			# If there are any uppercase characters, then make this PCRE string case sensitive.
			if ( (ord($this->term{$i}) >= 97) && (ord($this->term{$i}) <= 122) )
			{
				$lowercase = true;
				break;
			}
		}
		
		if ($lowercase === true)
		{
			$this->term = strtolower($this->term);
		}
		
		# If the last character in this word is an "s," then it might be a plural, in which case we
		# need to search for this and without its plural version.
		if (substr($this->term, -1) == 's')
		{
			$plural = true;
		}

		$sql = 'SELECT definitions.term, definitions.definition, definitions.scope,
				laws.section AS section_number,
				(CASE
					WHEN scope = "section" then "0"
					WHEN scope = "chapter" then "1"
					WHEN scope = "title" then "2"
					WHEN scope = "global" then "3"
				END) AS scope_order
				FROM definitions
				LEFT JOIN laws
					ON definitions.law_id=laws.id
				LEFT JOIN structure AS chapters
					ON laws.structure_id=chapters.id
				LEFT JOIN structure AS titles
					ON chapters.parent_id=titles.id
				WHERE (definitions.term="'.mysql_real_escape_string($this->term).'"';
		if ($plural === true)
		{
			$sql .= ' OR definitions.term = "'.mysql_real_escape_string(substr($this->term, 0, -1)).'"';
		}
		$sql .= ')
				AND
				(
					(
						(definitions.scope = "section" AND laws.id =
							(SELECT id
							FROM laws
							WHERE section = "'.mysql_real_escape_string($this->section_number).'")
						)
						OR
						(definitions.scope = "title" AND chapters.parent_id =
							(SELECT titles.id
							FROM laws
							LEFT JOIN structure AS chapters
								ON laws.structure_id=chapters.id
							LEFT JOIN structure AS titles
								ON chapters.parent_id=titles.id
							WHERE section = "'.mysql_real_escape_string($this->section_number).'")
						)
						OR
						(definitions.scope = "chapter" AND laws.structure_id =
							(SELECT structure_id
							FROM laws
							WHERE section = "'.mysql_real_escape_string($this->section_number).'")
						)
					)
					OR (definitions.scope = "global")
				)
				ORDER BY scope_order
				COLLATE utf8_general_ci
				LIMIT 1';

		# Execute the query.
		$result =& $db->query($sql);
		
		# If the query fails, or if no results are found, return false -- we have no definitions for
		# this chapter.
		if ( PEAR::isError($result) || ($result->numRows() < 1) )
		{
			return false;
		}
		
		# Get the first result.
		$dictionary = $result->fetchRow(MDB2_FETCHMODE_OBJECT);
		
		$dictionary->url = 'http://'.$_SERVER['SERVER_NAME'].'/'.$dictionary->section_number.'/';
		$dictionary->formatted = wptexturize($dictionary->definition).' (<a href="'.$dictionary->url.'">'
			.$definition->section_number.'</a>)';
		
		# Return that result.
		return $dictionary;
		
	}
		
	
	# Get a list of defined terms for a given chapter of the code, returning just a listing of
	# terms. (The idea is that we can use an Ajax call to get each definition on demand.)
	function term_list()
	{

		# We're going to need access to the database connection throughout this class.
		global $db;
		
		# If a chapter ID hasn't been passed to this function, then return a listing of definitions
		# that apply to the entirety of the code.
		if (!isset($this->structure_id) && !isset($this->scope))
		{
			$this->scope = 'global';
		}
		
		# Get a listing of all globally scoped definitions.
		if ($this->scope == 'global')
			$sql = 'SELECT definitions.term
					FROM definitions
					LEFT JOIN laws
						ON definitions.law_id=laws.id
					 WHERE scope="global"';
		
		# Otherwise, we're getting a listing of all chapter- and title-scoped definitions. We always
		# make sure that global definitions are included, in addition to the definitions for the
		# current title and the current chapter.
		else
		{
			$sql = 'SELECT definitions.term
					FROM definitions
					LEFT JOIN laws
						ON definitions.law_id=laws.id
					LEFT JOIN structure
						ON laws.structure_id=structure.id
					WHERE
					(
						(definitions.law_id='.$db->escape($this->section_id).')
						AND
						(definitions.scope="section")
					)
					OR
					(
						(laws.structure_id='.$db->escape($this->structure_id).')
						AND
						(definitions.scope="chapter")
					)
					OR (
						(
							(SELECT parent_id
							FROM structure
							WHERE structure.id='.$db->escape($this->structure_id).'
							AND label="chapter")
							= structure.id
							AND
							(definitions.scope="title")
						)
					)
					OR (scope="global")';
		}

		# Execute the query.
		$result =& $db->query($sql);
		
		# If the query fails, or if no results are found, return false -- we have no definitions for
		# this chapter.
		if ( PEAR::isError($result) || ($result->numRows() < 1) )
		{
			return false;
		}
		
		# Return the result as an object, built up as we loop through the results.
		$i=0;
		while ($term = $result->fetchRow(MDB2_FETCHMODE_OBJECT))
		{
			$terms->$i = $term->term;
			$i++;
		}
		return $terms;
	}
		
	
	# Get a list of definitions for a given chapter of the code, returning the word, the definition,
	# the scope, the section where the definition appears, and the URL for that definition.
	function definition_list()
	{

		# We're going to need access to the database connection throughout this class.
		global $db;
		
		# If a chapter ID hasn't been passed to this function, then return a listing of definitions
		# that apply to the entirety of the code.
		if (!isset($this->structure_id) && !isset($this->scope))
		{
			$this->scope = 'global';
		}
		
		# Get a listing of all globally scoped definitions.
		if ($this->scope == 'global')
			$sql = 'SELECT laws.section, definitions.term, definitions.definition, definitions.scope
					FROM definitions
					LEFT JOIN laws
						ON definitions.law_id=laws.id
					 WHERE scope="global"';
		
		# Otherwise, we're getting a listing of all chapter- and title-scoped definitions. We always
		# make sure that global definitions are included, in addition to the definitions for the
		# current title and the current chapter.
		else
		{
			$sql = 'SELECT laws.section, definitions.term, definitions.definition, definitions.scope
					FROM definitions
					LEFT JOIN laws
						ON definitions.law_id=laws.id
					LEFT JOIN chapters
						ON laws.structure_id=chapters.id
					WHERE
					(
						(definitions.law_id='.$db->escape($this->section_id).')
						AND
						(definitions.scope="section")
					)
					OR
					(
						(laws.structure_id='.$db->escape($this->structure_id).')
						AND
						(definitions.scope="chapter")
					)
					OR (
						(
							(SELECT parent_id
							FROM structure
							WHERE structure.id='.$db->escape($this->structure_id).'
							AND label="chapter")
								= chapters.parent_id
							AND
							(definitions.scope="title")
						)
					)
					OR (scope="global")';
		}

		# We order this in an unusual way, but there's a logic to it. First, we sort by string
		# length, from longest the shortest. The idea here is to get around the problem of matching
		# briefer definitions within words or phrases for which we have longer definitions. For
		# instance, "Motor Coach" would come before "Motor," helping to make sure that the whole
		# phrase will be matched up. Second, we sort alphabetically by term because, hey, why not?
		$sql .= ' ORDER BY CHAR_LENGTH(definitions.term) DESC, term ASC';

		# Execute the query.
		$result =& $db->query($sql);
		
		# If the query fails, or if no results are found, return false -- we have no definitions for
		# this chapter.
		if ( PEAR::isError($result) || ($result->numRows() < 1) )
		{
			return false;
		}
		
		# Return the result as an object, built up as we loop through the results.
		while ($definition = $result->fetchRow(MDB2_FETCHMODE_OBJECT))
		{
			# Figure out the URL and include that.
			$tmp = explode('-', $definition->section);
			$definition->url = 'http://'.$_SERVER['SERVER_NAME'].'/'.$tmp[0].'-'.$tmp[1].'/';
			
			# Set aside the term so that we can use it as our object element name.
			$term = $definition->term;
			
			# Check to see if this term is already set and, if so, make sure that the most local
			# term is being used. That is, if we have a chapter definition, we prefer it to a global
			# definition, because it is narrowly tailored to the instant section.

			// Scope should probably be rendered numerically, so that we could simply use the one
			// with the smallest number.
			if (isset($definitions->$term))
			{
				if
				(
					# If we've already recorded a title, chapter, or section definition, and this is
					# a global definition.
					(
						($definition->scope == 'global')
						&&
						( ($definitions->scope == 'title') || ($definitions->scope == 'chapter')
							|| ($definitions->scope == 'section') )
					)
					||
					# Or if we've recorded a section or chapter definition, and this is a title
					# definition.
					(
						($definition->scope == 'title')
						&&
						(
							($definitions->scope == 'chapter')
							|| 
							($definition->scope == 'section')
						)
					)
					||
					# Or if we've recorded a section definition, and this is a chapter definition.
					(
						($definition->scope == 'chapter')
						&&
						($definitions->scope == 'section')
					)
				)
				{
					# Then don't bother adding it to the array.
					continue;
				}
			}
			
			# Since we've found that this is a relevant definition, add it to the list.
			$definitions->$term = $definition;
			unset($tmp);
		}
		return $definitions;
	}
}


class Tags
{

	# Accepts one or more tags to save them. Requires a section ID and an object that contains one
	# or more tags. Returns only true or false, based on the success of the SQL insertion.
	#	
	# The way that the Tag-Handler jQuery UI plugin works, the whole list of ALL tags is passed
	# every time a new tag is added. There is no indication as to which are new and which were
	# already there. Ditto for when a tag is deleted -- it just sends a listing of all tags, minus
	# the deleted one. As a result, on any update, this function clears out all tags, and then
	# inserts the whole list.
	function save()
	{

		# We're going to need access to the database connection throughout this class.
		global $db;
		
		# If a section ID and zero or more tags haven't been passed to this function, then there's
		# nothing we can do. (Yes, zero. A blank array is how we indicate that we want to clear out
		# all tags for a given section.)
		if (!isset($this->section_id) || !isset($this->tags))
		{
			return false;
		}
		
		# Start a transaction. This is in case our insertion of tags fails after our deletion of the
		# old tags -- we don't want to lose them.
		$transaction = $db->beginTransaction();
		
		# Clear out all of the tags for this section.
		$sql = 'DELETE FROM tags
				WHERE law_id='.$db->escape($this->section_id);
				
		# Execute the query.
		$result =& $db->query($sql);
		
		# If the query fails, then there's nothing more that we can do.
		if (PEAR::isError($result))
		{
			return false;
		}
		
		# If we have any tags to insert.
		if (count($this->tags) > 0)
		{
			# Start our insertion SQL. We use INSERT INTO...ON DUPLICATE KEY instead of REPLACE INTO
			# because every time that any one tag for a section is update, ALL tags for that section
			# are reinserted. We'd quickly wind up with ludicrously large values for tags if we used
			# REPLACE INTO.
			$sql = 'INSERT INTO tags
					(law_id, section_number, ip_address, date_created, text)
					VALUES ';
			
			# Iterate through our array of tags and create the content portion of our insertion SQL.
			$i=0;
			foreach ($this->tags as $tag)
			{
				$sql .= '('.$db->escape($this->section_id).',
					(SELECT section
					FROM laws
					WHERE id='.$db->escape($this->section_id).'), ';
				if (!empty($_SERVER['REMOTE_ADDR']))
				{
					$sql .= 'INET_ATON("'.$db->escape($_SERVER['REMOTE_ADDR']).'"),';
				}
				else
				{
					$sql .= 'NULL,';
				}
				$sql .= 'now(), "'.$db->escape($tag).'")';
	
				$i++;
				
				# If this isn't the last tag in the list of tags, append a comma.
				if ($i < count($this->tags))
				{
					$sql .= ',';
				}
			}

			# Execute the query.
			$result =& $db->query($sql);
			
			# If the query fails, roll back our deletions and then return false.
			if (PEAR::isError($result))
			{
				$tranaction = $db->rollback();
				return false;
			}
		}
		
		# Commit the transaction.
		$transaction = $db->commit();
		
		# We're done here.
		return true;
		
	}
	
	
	# When given a section ID, returns an object that contains a listing of all tags for that
	# section. If no tags are found, it returns false.
	function get()
	{

		# We're going to need access to the database connection throughout this class.
		global $db;
		
		# If we're searching by section.
		if (isset($this->section_id))
		{
			$sql = 'SELECT text, COUNT(*) AS number
					FROM tags
					WHERE law_id='.$db->escape($this->section_id).'
					GROUP BY text
					ORDER BY text ASC';
		}
		
		# If we're searching by structural unit.
		elseif (isset($this->structure_id))
		{
			if (!isset($this->structure_label))
			{
				return false;
			}
			
			# Determine how many levels of join we'll require. We're finding the current structure
			# identifier in the overall structure and counting how deep that is.
			$tmp = array_slice(array_reverse(explode(',', STRUCTURE)), 1);
			$key = array_search($this->structure_label, $tmp);
			$depth = count(array_slice($tmp, 0, $key+1));
			unset($tmp);
			unset($key);
			
			# Begin our SQL to retrieve tags for this structural point.
			$sql = 'SELECT tags.text, COUNT(*) AS number
					FROM tags
					LEFT JOIN laws
						ON tags.law_id = laws.id';
						
			# Step through our structural levels of recursion.
			for ($i=1; $i<=$depth; $i++)
			{
				# The first level of recursion requires a join back to the laws.parent_id.
				if ($i == 1)
				{
					$sql .= '
					LEFT JOIN structure AS s'.$i.'
						ON laws.structure_id = s'.$i.'.id';
				}
				
				# But further levels of recursion need to be joined to prior structural table
				# instances.
				else
				{
					$sql .= '
					LEFT JOIN structure AS s'.$i.'
						ON s'.($i-1).'.parent_id = s'.$i.'.id';
				}
			}
			
			# And then wrap up our query.
			$sql .= '
					WHERE s'.$depth.'.id='.$db->escape($this->structure_id).'
					GROUP BY tags.text
					ORDER BY text ASC';
		}
		
		# If this is for the whole code. To avoid getting a truly crazy number of tags, we limit the
		# query to tags that are used at least twice. (The number displayed is limited by cloud().)
		else
		{
			$sql = 'SELECT tags.text, COUNT(*) AS number
					FROM tags
					GROUP BY tags.text
					HAVING number > 1';
		}
		
		# Execute the query.
		$result =& $db->query($sql);
		
		# If the query fails, or if no results are found, return false -- we have no tags for this
		# section.
		if ( PEAR::isError($result) || ($result->numRows() < 1) )
		{
			return false;
		}
		
		# Return the result as an array, built up as we loop through the results. We store this
		# as an array, rather than an object, because that's what we need for the Ajax function.
		$tags = array();
		while ($tag = $result->fetchRow(MDB2_FETCHMODE_OBJECT))
		{
			$tag->text = stripslashes($tag->text);
			$tags[$tag->text] = $tag->number;
		}
		
		# If we're searching by section, then we actually need to modify the format. We don't need
		# a count (since it's 1 for everything), but instead just an array of tags.
		if (isset($this->section_id))
		{
			$tags = array_keys($tags);
		}
		
		# Set this aside to be (potentially) reused in other functions.
		$this->tags = $tags;
		
		return $tags;
	}
	
	
	# When given a tag ID, deletes it. Returns true/false upon success/failure.
	function delete()
	{

		# We're going to need access to the database connection throughout this class.
		global $db;
		
		# If a tag ID hasn't been passed to this function, then there's nothing we can do.
		if (!isset($this->tag_id))
		{
			return false;
		}
		
		$sql = 'DELETE FROM tags
				WHERE id='.$this->tag_id.'
				LIMIT 1';
		
		# Execute the query.
		$result =& $db->query($sql);
		
		# If the query fails, or if no results are found, return false -- we have no tags for this
		# section.
		if ( PEAR::isError($result))
		{
			return false;
		}
		
		return true;
	}
	
	# Returns a listing of the universe of existing tags. Optionally accepts a string against
	# which it matches to return a listing of all existing tags that begin with that string.
	# Otherwise, it lists all tags. It optionally accepts a threshold, which is the number of times
	# that a tag must be used before it will show up within this list. The default is 3.
	// Perhaps this should also accept a "chapter" option, to narrow the candidate list to those
	// tags that are used in the chapter that contains the section in question?
	function list_candidates()
	{

		# We're going to need access to the database connection throughout this class.
		global $db;
		
		# If a threshold hasn't been passed to this function, then use a default threshold of 3.
		# The threshold is the number of times that a tag must have been used before it will appear
		# in this listing.
		if (!isset($this->threshold))
		{
			$this->threshold = 1;
		}
		
		$sql = 'SELECT text, COUNT(*) AS number
				FROM tags
				GROUP BY text
				HAVING number >= '.$this->threshold.'
				ORDER BY text ASC';
		
		# Execute the query.
		$result =& $db->query($sql);
		
		# If the query fails, or if no results are found, return false -- we have no tags for this
		# section.
		if ( PEAR::isError($result) || ($result->numRows() < 1) )
		{
			return false;
		}
		
		# Return the result as an object, built up as we loop through the results.
		$i=0;
		while ($tag = $result->fetchRow(MDB2_FETCHMODE_OBJECT))
		{
			$tags->$i = stripslashes($tag->text);
			$i++;
		}
		return $tags;
	
	}
	
	# Return a marked-up listing of the provided tags, shaving them down to a prescibed number.
	function cloud()
	{
	
		if (!isset($this->tags))
		{
			return false;
		}
		
		if (!isset($this->number))
		{
			# The default number of tags is 30.
			$this->number = 30;
		}
		
		if (!isset($this->threshold))
		{
			# The default number of times that a tag must have been applied is 2.
			$this->threshold = 2;
		}
		
		# Iterate through the tags.
		foreach ($this->tags as $tag => $count)
		{
			# Drop any tag that has less than the threshold number of applications.
			if ($count < $this->threshold)
			{
				unset($this->tags['tag']);
				continue;
			}
		}
		
		# Order the array by count, highest to lowest.
		arsort($this->tags);
		
		# Limit the array to the specified number.
		$this->tags = array_slice($this->tags, 0, $this->number);
		
		# Sort the tags alphabetically.
		ksort($this->tags);
		
		# Establish a scale -- the average size in this list should be 1.25em, with the scale
		# moving up and down from there.
		$multiple = 1.25 / (array_sum($this->tags) / count($this->tags));

		# Iterate through the tags to create the HTML.
		$html = array();
		foreach($this->tags as $tag => $count)
		{
			# Calculate the size of the typeface that we're going to use for this word.
			$size = round( ($count*$multiple), 1);
			
			# We just can't have words getting too large. If the disparity between the average word
			# frequency and the maximum word frequency is too great, we can get type sizes over
			# 120 points. Strictly speaking, the proper way to address this disparity is through a
			# change in scale -- moving from an ordinal scale to a logarithmic scale, for instance.
			# We could get the square root of every tag frequency and make *that* the size. But most
			# sections simply don't exhibit the sort of disparity that makes such a scale necessary,
			# and using that scale only for sections that do have that disparity would be confusing,
			# providing the appearance of less variation than there really is. The solution employed
			# here -- simply capping the size -- also disorts the results, but it has the benefit of
			# distorting *only* the tags appearing with the greatest frequency, rather than *all* of
			# the tags, as would result from the selective application of a logarithmic scale.
			if ($size > 4)
			{
				$size = 4;
			}
			elseif ($size < .75)
			{
				$size = .75;
			}
			$tmp = '<a href="/search/?q='.urlencode($tag);
			if (isset($this->title_number))
			{
				$tmp .= '&amp;title='.$this->title_number;
			}
			$tmp .= '" style="font-size: '.$size.'em;">'.$tag.'</a>';
			$html[] = $tmp;
		}
		
		$html = implode(' ', $html);
		return $html;
	}
}

?>