<?php

/**
 * The Structure class, for retrieving data about structural units (e.g., titles, chapters, etc.)
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
 
class Structure
{

	/**
	 * Takes a URL, returns an object all about that structural component. This isn't for laws,
	 * but for the containing units (titles, chapters, parts, etc.). It can be fed a URL or, if not,
	 * it'll just use the requested URL.
	 */
	function url_to_structure()
	{
	
		// We're going to need access to the database connection throughout this class.
		global $db;
		
		// If we haven't been provided with a URL, let's just assume that it's the current one.
		if (!isset($this->url))
		{
			// We can safely prepend "http://" because we're really only interested in the path
			// component of the URL -- the protocol will be ignored.
			$this->url = 'http://'.$_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
		}
		
		// Make sure that this URL is kosher.
		$this->url = filter_var($this->url, FILTER_SANITIZE_URL);
		if ($this->url === FALSE)
		{
			return FALSE;
		}
		
		// We don't actually want the whole URL, but just the path.
		$tmp = parse_url($this->url);
		$this->path = $tmp['path'];
		
		// Turn the URL into an array.
		$components = explode('/', $this->path);

		// Leading and trailing slashes in the path result in blank array elements. Remove them.
		// A path component may reasonably be "0" (in the case of a structural unit numbered "0,"
		// as exists in Virginia), so allow those.
		foreach ($components as $key => $component)
		{
			if ( empty($component) && (strlen($component) == 0) )
			{
				unset($components[$key]);
			}
		}
		
		// Reverse the components.
		$components = array_reverse($components);

		// Retrieve this structural unit's ancestry.
		$sql = 'SELECT s1_id
				FROM structure_unified
				WHERE ';
		$i=1;
		foreach ($components as $component)
		{
			$sql .= 's'.$i.'_identifier = ' . $db->quote($component) ;
			if ($i < count($components))
			{
				$sql .= ' AND ';
			}
			$i++;
		}
		
		$result = $db->query($sql);

		// If the query fails, or if no results are found, return false -- we can't make a match.
		if ( ($result === FALSE) || ($result->rowCount() == 0) )
		{
			return FALSE;
		}
		
		// Get the result as an object.
		$structure_row = $result->fetch(PDO::FETCH_OBJ);
		
		// Save the variable within the class scope.
		$this->structure_id = $structure_row->s1_id;
		
		// Pass the request off to the get_current() method.
		$this->get_current();
		
		return TRUE;
		
	}
	
	
	/**
	 * Get all of the metadata for the specified structural element (title, chapter, etc.).
	 */
	function get_current()
	{
	
		// We're going to need access to the database connection throughout this class.
		global $db;
		
		// If we don't have an ID of the structure element that we're looking for, then there's
		// really nothing for us to do here.
		if (!isset($this->structure_id))
		{
			return false;
		}
		
		// Retrieve this structural unit's ancestry.
		$sql = 'SELECT *
				FROM structure_unified
				WHERE 
				s1_id = '.$this->structure_id;
		
		$result = $db->query($sql);

		// If the query fails, or if no results are found, return false -- we can't make a match.
		if ( ($result === FALSE) || ($result->rowCount() == 0) )
		{
			return FALSE;
		}
		
		// Get the result as an object.
		$structure_row = $result->fetch(PDO::FETCH_OBJ);
		
		// Pivot this into a multidimensional object. That is, it's presently stored in multiple
		// columns in a single row, but we want it in multiple rows, one per hierarchical level.
		$structure = new stdClass();
		foreach($structure_row as $key => $value)
		{
			
			$value = stripslashes($value);
			
			// Determine the table prefix name, so that we can use the number contained within it as
			// the object element name.
			$tmp = explode('_', $key);
			$tmp = $tmp[0];
			$prefix = str_replace('s', '', $tmp);
			unset($tmp);
			
			// Strip out the table prefix from the key name.
			$key = preg_replace('/s[0-9]_/', '', $key);
			
			// If we have a null value for an ID, then we've reached the end of the populated
			// columns in this row.
			if ( ($key == 'id') && empty($value) )
			{
				break;
			}
			
			$structure->{$prefix-1}->$key = $value;
		}
		
		// Reverse the order of the elements of this object and place it in the scope of $this.
		$j=0;
		for ($i=count((array) $structure)-1; $i>=0; $i--)
		{
			$this->structure->{$j} = $structure->{$i};
			// Include the level of this structural element. (e.g., in Virginia, title is 1, chapter
			// is 2, part is 3.)
			$this->structure->{$j}->level = $j+1;
			if (isset($prior_id))
			{
				$this->structure->{$j}->parent_id = $prior_id;
			}
			$j++;
			$prior_id = $structure->{$i}->id;
		}
		unset($structure);
		
		// Iterate through the levels and build up the URLs recursively.
		$i=0;
		$url = 'http://' . $_SERVER['SERVER_NAME'].'/';
		$url_suffix = '';
		foreach ($this->structure as &$level)
		{
			$url_suffix .= urlencode($level->identifier) . '/';
			$level->url = $url . $url_suffix;
			$i++;
		}
		
		// We set these variables for the convenience of other functions in this class.
		$tmp = end($this->structure);
		$this->id = $tmp->id;
		$this->label = $tmp->label;
		$this->name = $tmp->name;
		$this->identifier = $tmp->identifier;
		if (isset($tmp->parent_id))
		{
			$this->parent_id = $tmp->parent_id;
		}
		unset($tmp);
		
		/*
		 * Get a list of all sibling structural units.
		 */
		 
		/*
		 * If this is anything other than a top-level structural unit. Because of how data is
		 * stored in structure_unified (the most specific structural units are in s1), the parent
		 * is always found in s2.
		 */
		if (!empty($this->parent_id))
		{
			$sql = 'SELECT s1_id AS id, s1_name AS name, s1_identifier AS identifier,
					CONCAT("/", ';
			for ($i=count((array) $this->structure); $i > 0; $i--)
			{
				$sql .= 's'.$i.'_identifier, "/"';
				if ($i != 1)
				{
					$sql .= ', ';
				}
			}
			$sql .= ') AS url
					FROM structure_unified
					LEFT JOIN structure
						ON structure_unified.s1_id = structure.id
					WHERE s2_id = ' . $db->quote($this->parent_id) . '
					ORDER BY structure.order_by, structure_unified.s1_identifier';
		}
		
		/*
		 * Else this is a top-level structural unit.
		 */
		else
		{
			$sql = 'SELECT id, name, identifier, CONCAT(identifier, "/") AS url
					FROM structure
					WHERE parent_id IS NULL';

			// Order these by the order_by column, which may or may not be populated.
			$sql .= ' ORDER BY structure.order_by ASC, ';

			// In case the order_by column is not populated, we go on to sort by the structure identifer,
			// by either Roman numerals or Arabic (traditional) numerals.
			if (isset($this->sort) && $this->sort == 'roman')
			{
				$sql .= 'fromRoman(structure.identifier) ASC';
			}
			else
			{
				$sql .= 'structure.identifier+0, ABS(SUBSTRING_INDEX(structure.identifier, ".", 1)) ASC,
					ABS(SUBSTRING_INDEX(structure.identifier, ".", -1)) ASC';
			}
		}
		
		$result = $db->query($sql);

		// If the query fails, or if no results are found, return false -- we can't make a match.
		if ( ($result === FALSE) || ($result->rowCount() == 0) )
		{
			// Get the result as an object.
			$this->siblings = $result->fetchAll(PDO::FETCH_OBJ);
		}
		
		return TRUE;
		
	}
	
	
	/**
	 * List all of the children of the current structural element. If $this->id is populated, then
	 * that is that used as the parent ID. If it is not populated, then the function will return all
	 * top level (parentless) structural elements.
	 */
	function list_children()
	{

		// We're going to need access to the database connection throughout this class.
		global $db;
		
		// Assemble the SQL query. The subselect is to avoid getting structural units that contain
		// only repealed (that is, unlisted) laws.
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
			$sql .= '='.$db->quote($this->id);
			
			// If this legal code continues to print repealed laws, then make sure that we're not
			// displaying any structural units that consist entirely of repealed laws.
			if (INCLUDES_REPEALED === TRUE)
			{
				$sql .= ' AND
					(SELECT COUNT(*)
					FROM laws
					LEFT OUTER JOIN laws_meta
						ON laws.id = laws_meta.law_id AND laws_meta.meta_key = "repealed"
					WHERE laws.structure_id=structure.id
					AND ((laws_meta.meta_value = "n") OR laws_meta.meta_value IS NULL)  ) > 0';
			}

		}
		
		// Order these by the order_by column, which may or may not be populated.
		$sql .= ' ORDER BY structure.order_by ASC, ';
		
		// In case the order_by column is not populated, we go on to sort by the structure
		// identifer, by either Roman numerals or Arabic (traditional) numerals.
		if (isset($this->sort) && $this->sort == 'roman')
		{
			$sql .= 'fromRoman(structure.identifier) ASC';
		}
		else
		{
			$sql .= 'structure.identifier+0, ABS(SUBSTRING_INDEX(structure.identifier, ".", 1)) ASC,
				ABS(SUBSTRING_INDEX(structure.identifier, ".", -1)) ASC';
		}
		
		// Execute the query.
		$result = $db->query($sql);
		
		// If the query fails, or if no results are found, return false -- we can't make a match.
		if ( ($result === FALSE) || ($result->rowCount() == 0) )
		{
			return FALSE;
		}
		
		// Return the result as an object, built up as we loop through the results.
		$i=0;
		while ($child = $result->fetch(PDO::FETCH_OBJ))
		{
			// Remap the structural column names to simplified column names.
			$child->id = $child->s1_id;
			$child->label = $child->s1_label;
			$child->name = $child->s1_name;
			$child->identifier = $child->s1_identifier;
			
			// Figure out the URL for this structural unit by iterating through the "identifier"
			// columns in this row.
			$child->url = 'http://'.$_SERVER['SERVER_NAME'].'/';
			$tmp = array();
			foreach ($child as $key => $value)
			{
				if (preg_match('/s[0-9]_identifier/', $key) == 1)
				{
					// Higher-level structural elements (e.g., titles) will have blank columns in
					// structure_unified, so we want to omit any blank values. Because a valid
					// structural unit identifier is "0" (Virginia does this), we check the string
					// length, rather than using empty().
					if (strlen($value) > 0)
					{
						$tmp[] = urlencode($value);
					}
				}
				
				/*
				 * We no longer have any need for these "s#_" fields. Eliminate them. (This is
				 * helpful to save memory, but it also allows this object to be delivered directly
				 * via the API, without modification.)
				 */
				if (preg_match('/s[0-9]_([a-z]+)/', $key) == 1)
				{
					unset($child->$key);
				}
			}
			$tmp = array_reverse($tmp);
			$child->url .= implode('/', $tmp).'/';
			$child->api_url = 'http://'.$_SERVER['SERVER_NAME'].'/api/structure/'.implode('/', $tmp).'/';
			$children->$i = $child;
			$i++;
		}
		
		return $children;
		
	}
	
	
	/**
	 * Get a structure ID's ancestry. For example, when given the ID of a chapter, it will return
	 * the chapter's ID, identifier, and name, along with its containing title's ID, number, and
	 * name.
	 */	
	function id_ancestry()
	{

		// We're going to need access to the database connection throughout this class.
		global $db;
		
		// If a structural ID hasn't been passed to this function, then there's nothing to do.
		if (!isset($this->id))
		{
			return FALSE;
		}
		
		// We use SELECT * because it's ultimately more efficient. That's because structure_unified
		// has a number of columns that varies between states. We could determine how many columns
		// based on the number of values in the STRUCTURE constant, or by first querying the
		// structure of the table, and that might be a worthy modification at some point. But, for
		// now, this will do.
		$sql = 'SELECT *
				FROM structure_unified
				WHERE s1_id='.$this->id;

		// Execute the query.
		$result = $db->query($sql);
		
		// If the query fails, or if no results are found, return false -- we can't make a match.
		if ( ($result === FALSE) || ($result->rowCount() == 0) )
		{
			return FALSE;
		}
		
		$structure = $result->fetch(PDO::FETCH_OBJ);

		// Create a new, blank object.
		$ancestry = new stdClass();
		
		// Iterate through $structure, cell by cell.
		foreach ($structure as $column => $cell)
		{
			// Some of the fields in our structure_unified table are going to be empty -- that's
			// just how it works. We're not interested in these fields, so we omit them. We verify
			// the string's length because 0 evaluates as empty in PHP, and we want to allow the use
			// of 0 as a valid structural unit identifier.
			if (empty($cell) && (strlen($cell) == 0))
			{
				continue;
			}
			
			// The first three characters of the column name are the prefix.
			$prefix = substr($column, 0, 2);
			
			// Strip out everything but the number.
			$prefix = preg_replace('/[^0-9]/', '', $prefix);
			
			// Assign this datum to an element within $tmp based on its prefix.
			$label = substr($column, 3);
			$ancestry->$prefix->$label = $cell;
		}
		
		// To assign URLs, we iterate through the object in reverse, and build up the URLs from
		// their structure identifiers.
		$url = 'http://'.$_SERVER['SERVER_NAME'].'/';
		foreach (array_reverse((array) $ancestry) as $key => $level)
		{
			$url .= urlencode($level->identifier).'/';
			$ancestry->$key->url = $url;
		}
		
		unset($structure);
		unset($label);
		unset($cell);
		unset($row);
		return $ancestry;
		
	}
	
	
	/**
	 * Convert a structure ID to its identifier.
	 */
	function id_to_identifier()
	{

		// We're going to need access to the database connection throughout this class.
		global $db;
		
		// If a structural ID hasn't been passed to this function, then there's nothing to do.
		if (!isset($this->id))
		{
			return FALSE;
		}
		
		// Assemble the SQL query.
		$sql = 'SELECT identifier
				FROM structure
				WHERE id='.$db->quote($this->id);
		
		// Execute the query.
		$result = $db->query($sql);
		
		// If the query fails, or if no results are found, return false -- we can't make a match.
		if ( ($result === FALSE) || ($result->rowCount() == 0) )
		{
			return FALSE;
		}
		
		$structure = $result->fetch(PDO::FETCH_OBJ);
		
		return $structure->identifier;
	}
	
	
	/**
	 * Get a listing of all laws for a given structural element.
	 */
	function list_laws()
	{

		// We're going to need access to the database connection throughout this class.
		global $db;
		
		// If a structural ID hasn't been passed to this function, then there's nothing to do.
		if (!isset($this->id))
		{
			return FALSE;
		}
		
		// Assemble the SQL query. Only get sections that haven't been repealed. We order by the
		// order_by field primarily, but we also order by section as a backup, in case something
		// should fail with the order_by field. The section column is not wholly reliable for
		// sorting (hence the order_by field), but it's a great deal better than an unsorted list.

		if (INCLUDES_REPEALED !== TRUE)
		{
		
			$sql = 'SELECT id, structure_id, section AS section_number, catch_line
					FROM laws
					WHERE structure_id=' . $db->quote($this->id) . '
					ORDER BY order_by, section';
		}
		
		else
		{
		
			$sql = 'SELECT laws.id, laws.structure_id, laws.section AS section_number, laws.catch_line
					FROM laws
					LEFT OUTER JOIN laws_meta
					ON laws_meta.law_id = laws.id AND laws_meta.meta_key = "repealed"
					WHERE structure_id=' . $db->quote($this->id) . '
					AND (laws_meta.meta_value = "n" OR laws_meta.meta_value IS NULL)
					ORDER BY order_by, section';
		}

		
		// Execute the query.
		$result = $db->query($sql);
		
		// If the query fails, or if no results are found, return false -- we can't make a match.
		if ( ($result === FALSE) || ($result->rowCount() == 0) )
		{
			return FALSE;
		}
		
		// Create a new, empty class.
		$laws = new stdClass();
		
		// Return the result as an object, built up as we loop through the results.
		$i=0;
		while ($section = $result->fetch(PDO::FETCH_OBJ))
		{
			// Figure out the URL and include that.
			$section->url = 'http://'.$_SERVER['SERVER_NAME'].'/'.$section->section_number.'/';
			
			// Ditto for the API URL.
			$section->api_url = 'http://'.$_SERVER['SERVER_NAME'].'/api/law/'.$section->section_number.'/';
			
			// Sometimes there are laws that lack titles. We've got to put something in that field.
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
