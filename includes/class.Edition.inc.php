<?php

/**
 * The Edition class, for retrieving data about available editions.
 *
 * PHP version 5
 *
 * @license		http://www.gnu.org/licenses/gpl.html GPL 3
 * @version		0.8
 * @link		http://www.statedecoded.com/
 * @since		0.8
 *
 *
 */

class Edition
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

		if(!isset($this->db))
		{
			global $db;
			$this->db = $db;
		}
	}

	public function find_by_id($id)
	{
		$sql = 'SELECT *
				FROM editions
				WHERE id = :id
				LIMIT 1';
		$sql_args[':id'] = $id;
		$statement = $this->db->prepare($sql);
		$result = $statement->execute($sql_args);

		if ($result === FALSE || $statement->rowCount() == 0)
		{
			return FALSE;
		}
		else
		{
			$edition = $statement->fetch(PDO::FETCH_OBJ);
		}
		return $edition;
	}

	public function find_by_slug($slug)
	{
		$sql = 'SELECT *
				FROM editions
				WHERE slug = :slug
				LIMIT 1';
		$sql_args[':slug'] = $slug;
		$statement = $this->db->prepare($sql);
		$result = $statement->execute($sql_args);

		if ($result === FALSE || $statement->rowCount() == 0)
		{
			return FALSE;
		}
		else
		{
			$edition = $statement->fetch(PDO::FETCH_OBJ);
		}
		return $edition;
	}

	public function current()
	{
		$sql = 'SELECT *
				FROM editions
				WHERE current = 1
				LIMIT 1';
		$statement = $this->db->prepare($sql);
		$result = $statement->execute();

		if ($result === FALSE || $statement->rowCount() == 0)
		{
			return FALSE;
		}
		else
		{
			$edition = $statement->fetch(PDO::FETCH_OBJ);
		}
		return $edition;
	}

	public function all()
	{
		$sql = 'SELECT *
				FROM editions
				ORDER BY order_by';
		$statement = $this->db->prepare($sql);
		$result = $statement->execute();

		if ($result === FALSE || $statement->rowCount() == 0)
		{
			return FALSE;
		}
		else
		{
			$editions = array();
			$editions = $statement->fetchAll(PDO::FETCH_OBJ);
		}
		return $editions;
	}


	public function count()
	{
		$sql = 'SELECT COUNT(*) AS count
				FROM editions';
		$statement = $this->db->prepare($sql);
		$result = $statement->execute();


		if ($result === FALSE || $statement->rowCount() == 0)
		{
			$count = 0;
		}
		else
		{
			$count = (int) $statement->fetchColumn();
		}
		return $count;
	}

	public function update_last_import($edition_id, $datetime = FALSE)
	{
		$sql = 'UPDATE editions
				SET last_import = :last_import
				WHERE id = :id
				LIMIT 1';
		$sql_args[':id'] = $edition_id;
		if($datetime)
		{
			$sql_args['last_import'] = $datetime;
		}
		else
		{
			$sql = str_replace(':last_import', 'NOW()', $sql);
		}
		$statement = $this->db->prepare($sql);
		$result = $statement->execute($sql_args);

		return $result;
	}

}
