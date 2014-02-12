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
	public $current_edition;

	public function __construct()
	{
		global $db;
		$this->db =& $db;
	}

	public function current($field = null)
	{

		if(empty($this->current_edition))
		{
			$statement = $this->db->prepare('SELECT * FROM editions WHERE current = 1');
			$result = $statement->execute();

			$this->current_edition = $statement->fetch(PDO::FETCH_OBJ);
			var_dump('Current', $this->current_edition);
		}

		if(isset($field))
		{
			return $this->current_edition->$field;
		}
		else
		{
			return $this->current_edition;
		}

	}

}
