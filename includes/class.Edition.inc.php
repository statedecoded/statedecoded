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

class Edition {

	protected $db;

	public function __construct()
	{
		global $db;
		$this->db =& $db;
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

	public function find_current()
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

}
