<?php

/**
 * The Structure class, for retrieving data about structural units (e.g., titles, chapters, etc.)
 *
 * PHP version 5
 *
 * @license		http://www.gnu.org/licenses/gpl.html GPL 3
 * @version		1.0
 * @link		http://www.statedecoded.com/
 * @since		0.1
 *
 */

class Structure
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
	 * Takes a URL, returns an object all about that structural component. This isn't for laws,
	 * but for the containing units (titles, chapters, parts, etc.). It can be fed a URL or, if not,
	 * it'll just use the requested URL.
	 */
	function url_to_structure()
	{
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
		$permalink_statement = $this->db->prepare($permalink_sql);

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
		$permalink_sql = 'SELECT relational_id FROM permalinks WHERE token = :token';
		$permalink_args = array(':token' => $token);
		$permalink_statement = $this->db->prepare($permalink_sql);

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
		 * If we don't have an ID of the structure element that we're looking for, then there's
		 * really nothing for us to do here.
		 */
		if (!isset($this->structure_id))
		{
			return false;
		}

		$this->structure = $this->id_ancestry($this->structure_id);

		/*
		 * We set these variables for the convenience of other functions in this class.
		 */
		if(is_array($this->structure) && count($this->structure))
		{
			$this->structure = array_reverse($this->structure);

			$tmp = end($this->structure);

			$this->id = $tmp->id;
			$this->label = $tmp->label;
			$this->name = $tmp->name;
			$this->identifier = $tmp->identifier;
			$this->permalink = $tmp->permalink;
			$this->metadata = $tmp->metadata;
			if (isset($tmp->parent_id))
			{
				$this->parent_id = $tmp->parent_id;
			}
			unset($tmp);
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
					AND permalinks.preferred = 1
					ORDER BY structure.order_by, structure_unified.s1_identifier';
			$sql_args[':object_type'] = 'structure';
			$sql_args[':parent_id'] = $this->parent_id;

		}

		/*
		 * Else this is a top-level structural unit.
		 */
		else
		{

			$sql = 'SELECT structure.id, structure.name, structure.identifier,
					permalinks.url, permalinks.token
					FROM structure
					LEFT JOIN permalinks
						ON structure.id = permalinks.relational_id and
						object_type = :object_type
					WHERE parent_id IS NULL
					AND permalinks.preferred = 1';
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

		$statement = $this->db->prepare($sql);
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
		 * Assemble the SQL query. The subselect is to avoid getting structural units that contain
		 * only repealed (that is, unlisted) laws.
		 */
		$sql = 'SELECT structure_unified.*';

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
					ON structure.id = structure_unified.s1_id';

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

		$sql_args = array();

		/*
		 * Check edition.
		 */
		$sql .= ' WHERE structure.edition_id = :edition_id';
		if(isset($this->edition_id))
		{
			$sql_args[':edition_id'] = $this->edition_id;
		}
		else
		{
			$sql_args[':edition_id'] = EDITION_ID;
		}

		/*
		 * If a structural ID hasn't been provided, then this request is for the root node -- that
		 * is, the top level of the legal code.
		 */
		if (!isset($this->id))
		{
			$sql .= ' AND structure.parent_id IS NULL';
		}
		else
		{

			$sql .= ' AND structure.parent_id = :parent_id';
			$sql_args[':parent_id'] = $this->id;

			/*
			 * If this legal code continues to print repealed laws, then make sure that we're not
			 * displaying any structural units that consist entirely of repealed laws.
			 */
			if (INCLUDES_REPEALED === TRUE)
			{
				$sql .= ' AND
						((SELECT COUNT(*)
						FROM laws
						LEFT OUTER JOIN laws_meta
							ON laws.id = laws_meta.law_id AND laws_meta.meta_key = "repealed"
						WHERE laws.structure_id=structure.id
						AND laws.edition_id = :edition_id
						AND ((laws_meta.meta_value = "n") OR laws_meta.meta_value IS NULL)  ) > 0
						OR (SELECT COUNT(*) FROM structure AS s2 WHERE s2.parent_id = structure.id) > 0)';
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

		$statement = $this->db->prepare($sql);
		$result = $statement->execute($sql_args);

		/*
		 * If the query fails, or if no results are found, return false -- we can't make a match.
		 */
		if ( ($result === FALSE) || ($statement->rowCount() == 0) )
		{
			return FALSE;
		}

		/*
		 * Instantiate the array we'll use to store and return the list of child structures.
		 */
		$children = array();

		/*
		 * Return the result as an object, built up as we loop through the results.
		 */
		$i=0;
		while ($child = $statement->fetch(PDO::FETCH_OBJ))
		{
			$children[] = $this->get_by_id($child->s1_id);
		}

		return $children;

	}


	/**
	 * Get a structure ID's ancestry. For example, when given the ID of a chapter, it will return
	 * the chapter's ID, identifier, and name, along with its containing title's ID, number, and
	 * name.
	 */
	function id_ancestry($id)
	{

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
			':id' => $id
		);

		$statement = $this->db->prepare($sql);

		$result = $statement->execute($sql_args);

		if ( ($result === FALSE) || ($statement->rowCount() == 0) )
		{
			return FALSE;
		}

		$structure_row = $statement->fetch(PDO::FETCH_OBJ);

		/*
		 * Create a new, blank object.
		 */
		$structure = array();

		/*
		 * Iterate through $structure, cell by cell.
		 */
		foreach ($structure_row as $key => $value)
		{

			/*
			 * Some of the fields in our structure_unified table are going to be empty -- that's
			 * just how it works. We're not interested in these fields, so we omit them. We verify
			 * the string's length because 0 evaluates as empty in PHP, and we want to allow the use
			 * of 0 as a valid structural unit identifier.
			 */
			if (empty($value))
			{
				continue;
			}

			$value = stripslashes($value);

			/*
			 * Determine the table prefix name, so that we can use the number contained within it as
			 * the object element name.
			 */
			$tmp = explode('_', $key);
			$prefix = ltrim($tmp[0], 's');
			$key = $tmp[1];
			unset($tmp);

			if ( $key === 'id' )
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
					/*
					 * Get everything about this structure.
					 */
					$structure[$prefix-1] = $this->get_by_id($value);
				}
			}
		}

		return $structure;
	}


	/**
	 * Convert an internal structure ID to its public identifier.
	 */
	function id_to_identifier()
	{
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

		$statement = $this->db->prepare($sql);
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
				WHERE identifier = :identifier
				AND structure.edition_id = :edition_id';
		$sql_args = array(
			':identifier' => $this->identifier,
			':edition_id' => $this->edition_id
		);

		$statement = $this->db->prepare($sql);
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
					AND laws.edition_id = :edition_id
					AND permalinks.preferred = 1
					ORDER BY order_by, section';
			$sql_args = array(
				':object_type' => 'law',
				':edition_id' => $this->edition_id,
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
					AND laws.edition_id = :edition_id
					AND permalinks.preferred = 1
					ORDER BY order_by, section';
			$sql_args = array(
				':object_type' => 'law',
				':edition_id' => $this->edition_id,
				':id' => $this->id
			);
		}

		$statement = $this->db->prepare($sql);
		$result = $statement->execute($sql_args);

		if ( ($result === FALSE) || ($statement->rowCount() == 0) )
		{
			return FALSE;
		}

		/*
		 * Create a new, empty array.
		 */
		$laws = array();

		/*
		 * Instantiate our laws class.
		 */
		$law = new Law(array('db' => $this->db));

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

			$law->section_id = $section->id;
			$section->metadata = $law->get_metadata();

			$laws[] = $section;

		}

		return $laws;
	}

	/**
	 * Get ids of all of the laws for a given edition.
	 *
	 * param int  $edition_id The edition to query.
	 * param bool $result_handle_only Get the results or just return the handle.
	 *                                This is useful when we don't have much memory.
	 */
	public function get_all($edition_id, $result_handle_only = false)
	{
		$query_args = array(
			':edition_id' => $edition_id
		);
		$query = 'SELECT structure.id
			FROM structure
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

	public function get_by_id($id)
	{
		static $statement;
		if(!$statement)
		{
			$query = 'SELECT *
				FROM structure
				WHERE id = :id';
			$statement = $this->db->prepare($query);
		}

		$query_args = array(
			':id' => $id
		);
		$result = $statement->execute($query_args);

		if($result)
		{
			$structure = $statement->fetch(PDO::FETCH_OBJ);

			if($structure->metadata)
			{
				$structure->metadata = unserialize($structure->metadata);
			}

			$permalink_obj = new Permalink(array('db' => $this->	db));
			$structure->permalink = $permalink_obj->get_preferred($structure->id, 'structure', $structure->edition_id);

			return $structure;
		}
		else {
			return FALSE;
		}
	}


	/**
	 * Get count of structures.
	 *
	 * param int  $edition_id The edition to query.
	 */
	public function count($edition_id, $result_handle_only = false)
	{
		$query_args = array();
		$query = 'SELECT count(*) AS count FROM structure ';
		if($edition_id)
		{
			$query .= 'WHERE edition_id = :edition_id ';
			$query_args[':edition_id'] = $edition_id;
		}

		$statement = $this->db->prepare($query);
		$result = $statement->execute($query_args);

		return (int) $statement->fetchColumn();
	}

}
