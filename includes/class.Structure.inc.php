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
			$this->url = 'http://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
		}
		
		// Make sure that this URL is kosher.
		$this->url = filter_var($this->url, FILTER_SANITIZE_URL);
		if ($this->url === false)
		{
			return false;
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
		
		// Turn the sidewide structure listing (just a string, separated by commas, of the basic
		// units of this legal code, from broadest—e.g., title—to the narrowest—e.g. article) into
		// an array.
		$structure = explode(',', STRUCTURE);
	
		// If there are more components of the URL than `count($structure)', only consider the
		// first `count($structure)' components.
		if (count($components) > count($structure))
		{
			$components = array_slice($components, 0, count($structure));
		}
		// If our structure is longer than the URL components (as it very often will be), hack off
		// the end structure array elements that we don't need.
		elseif (count($structure) > count($components))
		{
			$structure = array_slice($structure, 0, count($components));
		}	
		
		// Merge the structure and URL component arrays.
		$tmp = array_combine($structure, $components);
		$tmp = array_reverse($tmp);
		unset($structure);
		unset($components);
		$structure = $tmp;
		unset($tmp);
		
		// Assemble the query by which we'll retrieve this URL's pedigree from the structure table.
		$sql = 'SELECT ';
		
		// First, come up with the listing of tables and fields that we'll query.
		$select = '{table}.id as {table}_id, {table}.number AS {table}_number,
					{table}.name AS {table}_name, {table}.label AS {table}_label,
					{table}.parent_id AS {table}_parent_id,';

		for ($i=1; $i<=count($structure); $i++)
		{
			$sql .= str_replace('{table}', 's'.$i, $select);
		}
		
		// Remove the trailing comma.
		$sql = rtrim($sql, ',');
		
		// Second, come up with the list of joins that we're using (joining the structure table on
		// itself) to get what might be multiple levels of data.
		reset($structure);
		$i=1;
		foreach ($structure as $type => $number)
		{
			// If this is our first iteration through, start our FROM statement.
			reset($structure);
			if (key($structure) == $type)
			{
				$sql .= ' FROM structure AS s'.$i;
			}
			
			// If this isn't our first iteration, through, create joins.
			else
			{
				// Because we've already reset the array's pointer, we need to restore it to its
				// proper position, and then back it up one, to determine the parent of this current
				// section.
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
		
		// Third, and finally, create the WHERE portion of our SQL query.
		$sql .= ' WHERE ';
		$i=1;
		$where = array();
		foreach ($structure as $type => $number)
		{
			$where[] = 's'.$i.'.number="'.$db->escape($number).'" AND s'.$i.'.label="'.$db->escape($type).'"';
			$i++;
		}
		
		if (count($where) > 1)
		{
			$sql .= implode(' AND ', $where);
		}
		else
		{
			$sql .= current($where);
		}

		
		// We don't need either of these anymore.
		unset($where);
		unset($structure);

		// Execute the query.
		$result =& $db->query($sql);

		// If the query fails, or if no results are found, return false -- we can't make a match.
		if ( PEAR::isError($result) || ($result->numRows() < 1) )
		{
			return false;
		}
		
		// Get the result as an object.
		$structure_row = $result->fetchRow(MDB2_FETCHMODE_OBJECT);
		
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
			$j++;
		}
		unset($structure);
		
		// Iterate through the levels and build up the URLs recursively.
		$i=0;
		$url = 'http://'.$_SERVER['SERVER_NAME'].'/';
		$url_suffix = '';
		foreach ($this->structure as &$level)
		{
			$url_suffix .= urlencode($level->number).'/';
			$level->url = $url . $url_suffix;
			$i++;
		}
		
		// Set some variables for the convenience of other functions in this class.
		$tmp = end($this->structure);
		$this->id = $tmp->id;
		$this->label = $tmp->label;
		$this->name = $tmp->name;
		$this->number = $tmp->number;
		$this->parent_id = $tmp->parent_id;
		unset($tmp);
		
		return true;
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
		
		$sql = 'SELECT id, name, number, label, parent_id
				FROM structure
				WHERE';
			
		// If we've got a title ID, use that.
		if (isset($this->id))
		{
			$sql .= ' id="'.$db->escape($this->id).'"';
		}
		
		// Execute the query.
		$result =& $db->query($sql);
		
		// If the query fails, or if no results are found, return false -- we can't make a match.
		if ( PEAR::isError($result) || ($result->numRows() < 1) )
		{
			return false;
		}
		
		// Get the result as an object.
		$this->structure = $result->fetchRow(MDB2_FETCHMODE_OBJECT);
		
		foreach($this->structure as &$tmp)
		{
			$tmp = stripslashes($tmp);
		}
		
		return $structure;
		
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
			$sql .= '='.$db->escape($this->id);
			
			// If this legal code continues to print repealed laws, then make sure that we're not
			// displaying any structural units that consist entirely of repealed laws.
			if (INCLUDES_REPEALED === true)
			{
				$sql .= ' AND
						(SELECT COUNT(*)
						FROM laws
						WHERE structure_id=structure.id
						AND laws.repealed="n") > 0';
			}

		}
		
		// Order these by the order_by column, which may or may not be populated.
		$sql .= ' ORDER BY structure.order_by ASC, ';
		
		// In case the order_by column is not populated, we go on to sort by the structure identifer,
		// by either Roman numerals or Arabic (traditional) numerals.
		if (isset($this->sort) && $this->sort == 'roman')
		{
			$sql .= 'fromRoman(structure.number) ASC';
		}
		else
		{
			$sql .= 'structure.number+0, ABS(SUBSTRING_INDEX(structure.number, ".", 1)) ASC, ABS(SUBSTRING_INDEX(structure.number, ".", -1)) ASC';
		}

		// Execute the query.
		$result =& $db->query($sql);
		
		// If the query fails, or if no results are found, return false -- we can't make a match.
		if ( PEAR::isError($result) || ($result->numRows() < 1) )
		{
			return false;
		}
		
		// Return the result as an object, built up as we loop through the results.
		$i=0;
		while ($child = $result->fetchRow(MDB2_FETCHMODE_OBJECT))
		{
			// Remap the structural column names to simplified column names.
			$child->id = $child->s1_id;
			$child->label = $child->s1_label;
			$child->name = $child->s1_name;
			$child->number = $child->s1_number;
			
			// Figure out the URL for this structural unit by iterating through the "number" columns
			// in this row.
			$child->url = 'http://'.$_SERVER['SERVER_NAME'].'/';
			$tmp = array();
			foreach ($child as $key => $value)
			{
				if (preg_match('/s[0-9]_number/', $key) == 1)
				{
					// Higher-level structural elements (e.g., titles) will have blank columns in
					// structure_unified, so we want to omit any blank values. Because a valid
					// structural unit number is "0" (Virginia does this), we check the string
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
	 * the chapter's ID, number, and name, along with its containing title's ID, number, and name.
	 */	
	function id_ancestry()
	{

		// We're going to need access to the database connection throughout this class.
		global $db;
		
		// If a structural ID hasn't been passed to this function, then there's nothing to do.
		if (!isset($this->id))
		{
			return false;
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
		$result =& $db->query($sql);
		
		// If the query fails, or if no results are found, return false -- we can't make a match.
		if ( PEAR::isError($result) || ($result->numRows() < 1) )
		{
			return false;
		}
		
		$structure = $result->fetchRow(MDB2_FETCHMODE_OBJECT);

		// Create a new, blank object.
		$ancestry = new stdClass();
		
		// Iterate through $structure, cell by cell.
		foreach ($structure as $column => $cell)
		{
			// Some of the fields in our structure_unified table are going to be empty -- that's
			// just how it works. We're not interested in these fields, so we omit them. We verify
			// the string's length because 0 evaluates as empty in PHP, and we want to allow the use
			// of 0 as a valid structural unit number.
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
		
		// To assign URLs, we iterate through the object in reverse, and build up the URLs from their
		// structure numbers.
		$url = 'http://'.$_SERVER['SERVER_NAME'].'/';
		foreach (array_reverse((array) $ancestry) as $key => $level)
		{
			$url .= urlencode($level->number).'/';
			$ancestry->$key->url = $url;
		}
		
		unset($structure);
		unset($label);
		unset($cell);
		unset($row);
		return $ancestry;
		
	}
	
	
	/**
	 * Convert a structure ID to its number.
	 */
	function id_to_number()
	{

		// We're going to need access to the database connection throughout this class.
		global $db;
		
		// If a structural ID hasn't been passed to this function, then there's nothing to do.
		if (!isset($this->id))
		{
			return false;
		}
		
		// Assemble the SQL query.
		$sql = 'SELECT number
				FROM structure
				WHERE id='.$db->escape($this->id);
		
		// Execute the query.
		$result =& $db->query($sql);
		
		// If the query fails, or if no results are found, return false -- we can't make a match.
		if ( PEAR::isError($result) || ($result->numRows() < 1) )
		{
			return false;
		}
		
		$structure = $result->fetchRow(MDB2_FETCHMODE_OBJECT);
		
		return $structure->number;
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
			return false;
		}
		
		// Assemble the SQL query. Only get sections that haven't been repealed. We order by the
		// order_by field primarily, but we also order by section as a backup, in case something
		// should fail with the order_by field. The section column is not wholly reliable for
		// sorting (hence the order_by field), but it's a great deal better than an unsorted list.
		$sql = 'SELECT id, structure_id, section AS section_number, catch_line
				FROM laws
				WHERE structure_id='.$db->escape($this->id).' AND repealed="n"
				ORDER BY order_by, section';
		
		// Execute the query.
		$result =& $db->query($sql);
		
		// If the query fails, or if no results are found, return false -- we can't make a match.
		if ( PEAR::isError($result) || ($result->numRows() < 1) )
		{
			return false;
		}
		
		// Create a new, empty class.
		$laws = new stdClass();
		
		// Return the result as an object, built up as we loop through the results.
		$i=0;
		while ($section = $result->fetchRow(MDB2_FETCHMODE_OBJECT))
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
