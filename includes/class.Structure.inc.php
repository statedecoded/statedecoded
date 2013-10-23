<?php

/**
 * The Structure class, for retrieving data about structural units (e.g., titles, chapters, etc.)
 *
 * PHP version 5
 *
 * @author		Waldo Jaquith <waldo at jaquith.org>
 * @copyright	2010-2013 Waldo Jaquith
 * @license		http://www.gnu.org/licenses/gpl.html GPL 3
 * @version		0.7
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

		/*
		 * We're going to need access to the database connection throughout this class.
		 */
		global $db;

		/*
		 * If we haven't been provided with a URL, let's just assume that it's the current one.
		 */
		if (!isset($this->url))
		{
			/*
			 * We can safely prepend "http://" because we're really only interested in the path
			 * component of the URL -- the protocol will be ignored.
			 */
			$this->url = 'http://'.$_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
		}

		/*
		 * Make sure that this URL is kosher.
		 */
		$this->url = filter_var($this->url, FILTER_SANITIZE_URL);
		if ($this->url === FALSE)
		{
			return FALSE;
		}

		/*
		 * We don't actually want the whole URL, but just the path.
		 */
		$tmp = parse_url($this->url);
		$this->path = $tmp['path'];


		/*
		 * Get the url from the permalinks table
		 */
		$permalink_sql = 'SELECT relational_id FROM permalinks WHERE url = :url';
		$permalink_args = array('url' => $this->path);
		$permalink_statement = $db->prepare($permalink_sql);

		$result = $permalink_statement->execute($permalink_args);

		if ( ($result === FALSE) || ($permalink_statement->rowCount() == 0) )
		{
			return FALSE;
		}

		/*
		 * Get the result
		 */
		$structure_row = $permalink_statement->fetch(PDO::FETCH_ASSOC);

		/*
		 * Save the variable within the class scope.
		 */
		$this->structure_id = $structure_row['relational_id'];

		/*
		 * Pass the request off to the get_current() method.
		 */
		$this->get_current();

		return TRUE;

	}

	/**
	 * Easier way to get structure for API
	 */
	function token_to_structure($token)
	{

		/*
		 * We're going to need access to the database connection throughout this class.
		 */
		global $db;

		$permalink_sql = 'SELECT relational_id FROM permalinks WHERE token = :token';
		$permalink_args = array(':token' => $token);
		$permalink_statement = $db->prepare($permalink_sql);

		$result = $permalink_statement->execute($permalink_args);

		if ( ($result === FALSE) || ($permalink_statement->rowCount() == 0) )
		{
			return FALSE;
		}

		/*
		 * Get the result
		 */
		$structure_row = $permalink_statement->fetch(PDO::FETCH_ASSOC);

		/*
		 * Save the variable within the class scope.
		 */
		$this->structure_id = $structure_row['relational_id'];

		/*
		 * Pass the request off to the get_current() method.
		 */
		$this->get_current();

		return TRUE;

	}


	/**
	 * Get all of the metadata for the specified structural element (title, chapter, etc.).
	 */
	function get_current()
	{

		/*
		 * We're going to need access to the database connection throughout this class.
		 */
		global $db;

		/*
		 * If we don't have an ID of the structure element that we're looking for, then there's
		 * really nothing for us to do here.
		 */
		if (!isset($this->structure_id))
		{
			return false;
		}

		/*
		 * Retrieve this structural unit's ancestry.
		 */
		$sql = 'SELECT structure_unified.*
				FROM structure_unified
				WHERE
				s1_id = :id
				LIMIT 1';
		$sql_args = array(
			':id' => $this->structure_id
		);
		$statement = $db->prepare($sql);


		$result = $statement->execute($sql_args);

		if ( ($result === FALSE) || ($statement->rowCount() == 0) )
		{
			return FALSE;
		}

		/*
		 * Get the result as an object.
		 */
		$structure_row = $statement->fetch(PDO::FETCH_OBJ);

		/*
		 * Pivot this into a multidimensional object. That is, it's presently stored in multiple
		 * columns in a single row, but we want it in multiple rows, one per hierarchical level.
		 */
		$structure = new stdClass();
		$structure_ids = array();
		foreach($structure_row as $key => $value)
		{

			$value = stripslashes($value);

			/*
			 * Determine the table prefix name, so that we can use the number contained within it as
			 * the object element name.
			 */
			$tmp = explode('_', $key);
			$tmp = $tmp[0];
			$prefix = str_replace('s', '', $tmp);
			unset($tmp);

			/*
			 * Strip out the table prefix from the key name.
			 */
			$key = preg_replace('/s[0-9]_/', '', $key);

			if ( ($key == 'id') )
			{
				/*
				 * If we have a null value for an ID, then we've reached the end of the populated
				 * columns in this row.
				 */
				if (empty($value))
				{
					break;
				}
				/*
				 * Otherwise, keep track of the ids we've seen.
				 */
				else
				{
					$structure_ids[] = $value;
				}
			}

			$structure->{$prefix-1}->$key = $value;

		}

		/*
		 * Get all of the associated permalinks.
		 */
		$sql = 'SELECT permalinks.* FROM permalinks ' .
			'WHERE object_type = :object_type AND ' .
			'permalinks.relational_id = :id';

		$statement = $db->prepare($sql);

		/*
		 * Reverse the order of the elements of this object and place it in the scope of $this.
		 */
		$j=0;
		for ($i=count((array) $structure)-1; $i>=0; $i--)
		{

			$this->structure->{$j} = $structure->{$i};

			/*
			 * Include the level of this structural element. (e.g., in Virginia, "title" is 1,
			 * "chapter" is 2, "part" is 3.)
			 */
			$this->structure->{$j}->level = $j+1;

			$sql_args = array(
				':object_type' => 'structure',
				':id' => $structure->{$i}->id
			);
			$result = $statement->execute($sql_args);

			if ( ($result === FALSE) || ($statement->rowCount() == 0) )
			{
				return FALSE;
			}

			$permalink = $statement->fetch(PDO::FETCH_OBJ);
			$this->structure->{$j}->url = $permalink->url;
			$this->structure->{$j}->token = $permalink->token;

			if (isset($prior_id))
			{
				$this->structure->{$j}->parent_id = $prior_id;
			}
			$j++;
			$prior_id = $structure->{$i}->id;

		}

		unset($structure);

		/*
		 * We set these variables for the convenience of other functions in this class.
		 */
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
		 * Get any metadata available about this structural unit.
		 */
		$sql = 'SELECT metadata
				FROM structure
				WHERE id=:id
				AND metadata IS NOT NULL';
		$sql_args = array(
			':id' => $this->id
		);

		$statement = $db->prepare($sql);
		$result = $statement->execute($sql_args);

		if ( ($result !== FALSE) && ($statement->rowCount() >= 1) )
		{
			$structure_row = $statement->fetch(PDO::FETCH_OBJ);
			$this->metadata = unserialize(stripslashes($structure_row->metadata));
		}

		/*
		 * Get a list of all sibling structural units.
		 *
		 * If this is anything other than a top-level structural unit. Because of how data is
		 * stored in structure_unified (the most specific structural units are in s1), the parent
		 * is always found in s2.
		 */
		$sql_args = array();
		if (!empty($this->parent_id))
		{

			$sql = 'SELECT s1_id AS id, s1_name AS name, s1_identifier AS identifier,
					permalinks.url, permalinks.token
					FROM structure_unified
					LEFT JOIN structure
						ON structure_unified.s1_id = structure.id
					LEFT JOIN permalinks
						ON structure.id = permalinks.relational_id and
						object_type = :object_type
					WHERE s2_id = :parent_id
					ORDER BY structure.order_by, structure_unified.s1_identifier';
			$sql_args[':object_type'] = 'structure';
			$sql_args[':parent_id'] = $this->parent_id;

		}

		/*
		 * Else this is a top-level structural unit.
		 */
		else
		{

			$sql = 'SELECT id, name, identifier,
					permalinks.url, permalinks.token
					FROM structure
					LEFT JOIN permalinks
						ON structure.id = permalinks.relational_id and
						object_type = :object_type
					WHERE parent_id IS NULL';
			$sql_args[':object_type'] = 'structure';

			/*
			 * Order these by the order_by column, which may or may not be populated.
			 */
			$sql .= ' ORDER BY structure.order_by ASC, ';

			/*
			 * In case the order_by column is not populated, we go on to sort by the structure
			 * identifer, by either Roman numerals or Arabic (traditional) numerals.
			 */
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

		$statement = $db->prepare($sql);
		$result = $statement->execute($sql_args);

		/*
		 * If the query fails, or if no results are found, return false -- we can't make a match.
		 */
		if ( ($result === FALSE) || ($statement->rowCount() == 0) )
		{
			return FALSE;
		}

		/*
		 * Get the result as an object.
		 */
		$this->siblings = $statement->fetchAll(PDO::FETCH_OBJ);

		return TRUE;

	}


	/**
	 * List all of the children of the current structural element. If $this->id is populated, then
	 * that is that used as the parent ID. If it is not populated, then the function will return all
	 * top level (parentless) structural elements.
	 */
	function list_children()
	{

		/*
		 * We're going to need access to the database connection throughout this class.
		 */
		global $db;

		/*
		 * Assemble the SQL query. The subselect is to avoid getting structural units that contain
		 * only repealed (that is, unlisted) laws.
		 */
		$sql = 'SELECT structure_unified.*,
				permalinks.url, permalinks.token';

		/*
		 * If we're ordering by views, select that data.
		 */
		if ( isset($this->order_by) && ($this->order_by == 'views') )
		{
			$sql .= ', COUNT( laws_views.id ) AS view_count';
		}

		$sql .= '
				FROM structure
				LEFT JOIN structure_unified
					ON structure.id = structure_unified.s1_id
				LEFT JOIN permalinks
					ON structure.id = permalinks.relational_id and
					object_type = :object_type';

		/*
		 * If we're ordering children by views, we need to join the structural table to the
		 * laws_views table.
		 */
		if ( isset($this->order_by) && ($this->order_by == 'views') )
		{
			$sql .= '	LEFT JOIN laws
							ON structure.id = laws.structure_id
						LEFT JOIN laws_views
							ON laws.section = laws_views.section';
		}

		$sql_args = array(
			':object_type' => 'structure'
		);

		/*
		 * If a structural ID hasn't been provided, then this request is for the root node -- that
		 * is, the top level of the legal code.
		 */
		if (!isset($this->id))
		{
			$sql .= ' WHERE structure.parent_id IS NULL';
		}
		else
		{

			$sql .= ' WHERE structure.parent_id = :parent_id';
			$sql_args[':parent_id'] = $this->id;

			/*
			 * If this legal code continues to print repealed laws, then make sure that we're not
			 * displaying any structural units that consist entirely of repealed laws.
			 */
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

		/*
		 * If we're sorting children by views, we need to insert a GROUP BY and ORDER BY statement
		 * here.
		 */
		if ( isset($this->order_by) && ($this->order_by == 'views') )
		{
			$sql .= '	GROUP BY structure.id
						ORDER BY view_count DESC, structure.order_by ASC, ';
		}

		/*
		 * Otherwise, by default, order children by the order_by column, which may or may not be
		 * populated.
		 */
		else
		{
			$sql .= ' ORDER BY structure.order_by ASC, ';
		}

		/*
		 * In case the order_by column is not populated, we go on to sort by the structure
		 * identifer, by either Roman numerals or Arabic (traditional) numerals.
		 */
		if (isset($this->sort) && $this->sort == 'roman')
		{
			$sql .= 'fromRoman(structure.identifier) ASC';
		}
		else
		{
			$sql .= 'structure.identifier+0, ABS(SUBSTRING_INDEX(structure.identifier, ".", 1)) ASC,
				ABS(SUBSTRING_INDEX(structure.identifier, ".", -1)) ASC';
		}

		$statement = $db->prepare($sql);
		$result = $statement->execute($sql_args);

		/*
		 * If the query fails, or if no results are found, return false -- we can't make a match.
		 */
		if ( ($result === FALSE) || ($statement->rowCount() == 0) )
		{
			return FALSE;
		}

		/*
		 * Instantiate the object we'll use to store and return the list of child structures.
		 */
		$children = new stdClass();

		/*
		 * Return the result as an object, built up as we loop through the results.
		 */
		$i=0;
		while ($child = $statement->fetch(PDO::FETCH_OBJ))
		{

			/*
			 * Remap the structural column names to simplified column names.
			 */
			$child->id = $child->s1_id;
			$child->label = $child->s1_label;
			$child->name = $child->s1_name;
			$child->identifier = $child->s1_identifier;

			/*
			 *We don't need to display the aggregate view count -- we only use that for sorting.
			 */
			unset($child->view_count);

			/*
			 * Append this child to our list.
			 */
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

		/*
		 * We're going to need access to the database connection throughout this class.
		 */
		global $db;

		/*
		 * If a structural ID hasn't been passed to this function, then there's nothing to do.
		 */
		if (!isset($this->id))
		{
			return FALSE;
		}

		/*
		 * We use SELECT * because it's ultimately more efficient. That's because structure_unified
		 * has a number of columns that varies between states. We could determine how many columns
		 * by first querying the structure of the table, and that might be a worthy modification
		 * at some point. But, for now, this will do.
		 */
		$sql = 'SELECT structure_unified.*
				FROM structure_unified
				WHERE s1_id = :id';
		$sql_args = array(
			':id' => $this->id
		);

		$statement = $db->prepare($sql);
		$result = $statement->execute($sql_args);

		if ( ($result === FALSE) || ($statement->rowCount() == 0) )
		{
			return FALSE;
		}

		$structure = $statement->fetch(PDO::FETCH_OBJ);

		/*
		 * Create a new, blank object.
		 */
		$ancestry = new stdClass();

		/*
		 * Iterate through $structure, cell by cell.
		 */
		foreach ($structure as $column => $cell)
		{

			/*
			 * Some of the fields in our structure_unified table are going to be empty -- that's
			 * just how it works. We're not interested in these fields, so we omit them. We verify
			 * the string's length because 0 evaluates as empty in PHP, and we want to allow the use
			 * of 0 as a valid structural unit identifier.
			 */
			if (empty($cell) && (strlen($cell) == 0))
			{
				continue;
			}

			/*
			 * The first three characters of the column name are the prefix.
			 */
			$prefix = substr($column, 0, 2);

			/*
			 * Strip out everything but the number.
			 */
			$prefix = preg_replace('/[^0-9]/', '', $prefix);

			/*
			 * Assign this datum to an element within $tmp based on its prefix.
			 */
			$label = substr($column, 3);
			$ancestry->$prefix->$label = $cell;
		}

		/*
		 * Go get our urls from the permalinks table.
		 */
		$sql = 'SELECT permalinks.url
				FROM permalinks
				WHERE relational_id = :id
				AND object_type = :object_type';
		$statement = $db->prepare($sql);

		foreach ((array) $ancestry as $key => $level)
		{

			$sql_args = array(
				':id' => $ancestry->$key->id,
				':object_type' => 'structure'
			);

			$result = $statement->execute($sql_args);

			if ( ($result !== FALSE) && ($statement->rowCount() > 0) )
			{
				$permalink = $statement->fetch(PDO::FETCH_OBJ);

				$ancestry->$key->url = $permalink->url;
			}

		}

		unset($structure);
		unset($label);
		unset($cell);
		unset($row);
		return $ancestry;

	}


	/**
	 * Convert an internal structure ID to its public identifier.
	 */
	function id_to_identifier()
	{

		/*
		 * We're going to need access to the database connection throughout this class.
		 */
		global $db;

		/*
		 * If a structural ID hasn't been passed to this function, then there's nothing to do.
		 */
		if (!isset($this->id))
		{
			return FALSE;
		}

		/*
		 * Assemble the SQL query.
		 */
		$sql = 'SELECT identifier
				FROM structure
				WHERE id = :id';
		$sql_args = array(
			':id' => $this->id
		);

		$statement = $db->prepare($sql);
		$result = $statement->execute($sql_args);


		if ( ($result === FALSE) || ($statement->rowCount() == 0) )
		{
			return FALSE;
		}

		$structure = $statement->fetch(PDO::FETCH_OBJ);

		return $structure->identifier;

	}


	/**
	 * Convert a structure's public identifier to its internal ID.
	 */
	function identifier_to_id()
	{

		/*
		 * We're going to need access to the database connection throughout this class.
		 */
		global $db;

		/*
		 * If a structural identifier hasn't been passed to this function, then there's nothing to
		 * do.
		 */
		if (!isset($this->identifier))
		{
			return FALSE;
		}

		/*
		 * Assemble the SQL query.
		 */
		$sql = 'SELECT id
				FROM structure
				WHERE identifier = :identifier';
		$sql_args = array(
			':identifier' => $this->identifier
		);

		$statement = $db->prepare($sql);
		$result = $statement->execute($sql_args);


		if ( ($result === FALSE) || ($statement->rowCount() == 0) )
		{
			return FALSE;
		}

		$structure = $statement->fetch(PDO::FETCH_OBJ);

		return $structure->id;

	}


	/**
	 * Get a listing of all laws for a given structural element.
	 */
	function list_laws()
	{

		/*
		 * We're going to need access to the database connection throughout this class.
		 */
		global $db;

		/*
		 * If a structural ID hasn't been passed to this function, then there's nothing to do.
		 */
		if (!isset($this->id))
		{
			return FALSE;
		}

		/*
		 * Assemble the SQL query. Only get sections that haven't been repealed. We order by the
		 * order_by field primarily, but we also order by section as a backup, in case something
		 * should fail with the order_by field. The section column is not wholly reliable for
		 * sorting (hence the order_by field), but it's a great deal better than an unsorted list.
		 */
		if (INCLUDES_REPEALED !== TRUE)
		{

			$sql = 'SELECT laws.id, laws.structure_id, laws.section AS section_number, laws.catch_line,
					permalinks.url, permalinks.token
					FROM laws
					LEFT JOIN permalinks ON laws.id = permalinks.relational_id
						AND permalinks.object_type = :object_type
					WHERE structure_id = :id
					ORDER BY order_by, section';
			$sql_args = array(
				':object_type' => 'law',
				':id' => $this->id
			);
		}

		else
		{

			$sql = 'SELECT laws.id, laws.structure_id, laws.section AS section_number, laws.catch_line,
					permalinks.url, permalinks.token
					FROM laws
					LEFT OUTER JOIN laws_meta
						ON laws_meta.law_id = laws.id AND laws_meta.meta_key = "repealed"
					LEFT JOIN permalinks ON laws.id = permalinks.relational_id
						AND permalinks.object_type = :object_type
					WHERE structure_id = :id
					AND (laws_meta.meta_value = "n" OR laws_meta.meta_value IS NULL)
					ORDER BY order_by, section';
			$sql_args = array(
				':object_type' => 'law',
				':id' => $this->id
			);
		}

		$statement = $db->prepare($sql);
		$result = $statement->execute($sql_args);

		if ( ($result === FALSE) || ($statement->rowCount() == 0) )
		{
			return FALSE;
		}

		/*
		 * Create a new, empty class.
		 */
		$laws = new stdClass();

		/*
		 * Return the result as an object, built up as we loop through the results.
		 */
		$i=0;
		while ($section = $statement->fetch(PDO::FETCH_OBJ))
		{

			/*
			 * Sometimes there are laws that lack titles. We've got to put something in that field.
			 */
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
