<?php

/**
 * The Edition class, for retrieving data about available editions.
 *
 * PHP version 5
 *
 * @license		http://www.gnu.org/licenses/gpl.html GPL 3
 * @version		0.9
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

	public function find_by_name($name)
	{
		$sql = 'SELECT *
				FROM editions
				WHERE name = :name
				LIMIT 1';
		$sql_args[':name'] = $name;
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

	public function create($edition)
	{
		static $statement;
		if(!isset($statement))
		{
			$sql = 'INSERT INTO editions SET
					name=:name,
					slug=:slug,
					current=:current,
					order_by=:order_by,
					date_created=NOW(),
					date_modified=NOW()';
			$statement = $this->db->prepare($sql);
		}

		if (!isset($edition['order_by']))
		{
			$edition['order_by'] = $this->get_max_order() + 1;
		}

		$sql_args = array(
			':name' => $edition['name'],
			':slug' => $edition['slug'],
			':current' => $edition['current'],
			':order_by' => $edition['order_by']
		);
		$result = $statement->execute($sql_args);

		if ($result !== FALSE)
		{
			return $this->db->lastInsertId();
		}
		else
		{
			return FALSE;
		}
	}

	public function unset_current($exception = FALSE)
	{
		$sql = 'UPDATE editions
				SET current = :current';
		$sql_args = array(
			':current' => 0
		);

		if($exception)
		{
			$sql .= ' WHERE id <> :id';
			$sql_args['id'] = $exception;
		}

		$statement = $this->db->prepare($sql);
		$result = $statement->execute($sql_args);

		return $result;
	}

	public function get_max_order()
	{
		$sql = 'SELECT MAX(order_by) AS order_by
				FROM editions
				ORDER BY order_by';
		$statement = $this->db->prepare($sql);
		$result = $statement->execute();
		if ($result !== FALSE && $statement->rowCount() > 0)
		{
			$edition_row = $statement->fetch(PDO::FETCH_ASSOC);
			return (int) $edition_row['order_by'];
		}
		else
		{
			return 0;
		}
	}
}
