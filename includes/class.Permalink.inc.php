<?php

/**
 * Permalink model for The State Decoded.
 *
 * PHP version 5
 *
 * @license		http://www.gnu.org/licenses/gpl.html GPL 3
 * @version		0.9
 * @link		http://www.statedecoded.com/
 * @since		0.9
 */

class Permalink
{
	protected $db;

	public function __construct($args = array())
	{
		/*
		 * Set our defaults
		 */
		foreach($args as $key=>$value)
		{
			$this->$key = $value;
		}
	}

	public function create($insert_data)
	{
		static $insert_statement;
		if(!isset($insert_statement))
		{
			$insert_sql = 'INSERT INTO permalinks SET
				object_type = :object_type,
				relational_id = :relational_id,
				identifier = :identifier,
				token = :token,
				url = :url,
				edition_id = :edition_id,
				preferred = :preferred,
				permalink = :permalink';
			$insert_statement = $this->db->prepare($insert_sql);
		}
		$insert_result = $insert_statement->execute($insert_data);

		return $insert_result;
	}

	public function get_preferred($id, $type, $edition_id)
	{
		static $preferred_statement;
		if(!isset($preferred_statement))
		{
			$preferred_sql = 'SELECT * FROM permalinks WHERE
			`relational_id` = :relational_id AND
			`object_type` = :object_type AND
			`edition_id` = :edition_id AND
			`preferred` = 1 LIMIT 1';
			$preferred_statement = $this->db->prepare($preferred_sql);
		}

		$preferred_args = array(
			':relational_id' => $id,
			':object_type' => $type,
			':edition_id' => $edition_id
		);

		$preferred_result = $preferred_statement->execute($preferred_args);

		if ($preferred_result === FALSE || $preferred_statement->rowCount() == 0)
		{
			return FALSE;
		}
		else
		{
			return $preferred_statement->fetch(PDO::FETCH_OBJ);
		}
	}

	public function get_permalink($id, $type, $edition_id)
	{
		static $preferred_statement;
		if(!isset($preferred_statement))
		{
			$preferred_sql = 'SELECT * FROM permalinks WHERE
			`relational_id` = :relational_id AND
			`object_type` = :object_type AND
			`edition_id` = :edition_id AND
			`permalink` = 1 LIMIT 1';
			$preferred_statement = $this->db->prepare($preferred_sql);
		}

		$preferred_args = array(
			':relational_id' => $id,
			':object_type' => $type,
			':edition_id' => $edition_id
		);

		$preferred_result = $preferred_statement->execute($preferred_args);

		if ($preferred_result === FALSE || $preferred_statement->rowCount() == 0)
		{
			return FALSE;
		}
		else
		{
			return $preferred_statement->fetch(PDO::FETCH_OBJ);
		}
	}

	/*
	 * Given an existing url, find a permalink that matches that url
	 * for a different edition.
	 * E.g. "Show the version of this law for edition 7."
	 */
	public function translate_permalink($url, $edition_id, $preferred = 1)
	{
		$query = 'SELECT p2.* FROM permalinks AS p1
		LEFT JOIN permalinks AS p2
		 ON p1.token = p2.token
		 AND p1.object_type = p2.object_type
		WHERE p1.url = :url
		AND p2.edition_id = :edition_id ';
		$sql_args = array(
			':url' => $url,
			':edition_id' => $edition_id
		);
		// If it's preferred, we want that.
		if($preferred)
		{
			$query .= 'AND p2.preferred = 1 ';
		}
		// Otherwise we want the permalink.
		else
		{
			$query .= 'AND p2.permalink = 1 ';
		}

		$statement = $this->db->prepare($query);
		$result = $statement->execute($sql_args);


		if ($result === FALSE || $statement->rowCount() == 0)
		{
			return FALSE;
		}
		else
		{
			$permalink = $statement->fetch(PDO::FETCH_OBJ);

			// Get our extended data.
			if($permalink->object_type == 'law')
			{
				$law_statement = $this->db->prepare('SELECT * FROM laws
					WHERE id = :law_id');
				$law_args = array(':law_id' => $permalink->relational_id);

				$law_result = $law_statement->execute($law_args);
				if ($law_result !== FALSE && $law_statement->rowCount() > 0)
				{
					$permalink->law = $law_statement->fetch(PDO::FETCH_OBJ);
					$permalink->title = SECTION_SYMBOL . '&nbsp;' .
						$permalink->law->section . ' ' .
						$permalink->law->catch_line;
				}
			}
			elseif($permalink->object_type == 'structure')
			{
				$structure_statement = $this->db->prepare('SELECT * FROM
					structure WHERE id = :structure_id');
				$structure_args = array(':structure_id' => $permalink->relational_id);

				$structure_result = $structure_statement->execute($structure_args);
				if ($structure_result !== FALSE &&
					$structure_statement->rowCount() > 0)
				{
					$permalink->structure = $structure_statement->fetch(PDO::FETCH_OBJ);
					$permalink->title = $permalink->structure->identifier .
						' ' . $permalink->structure->name;
				}
			}

			return $permalink;
		}
	}
}
