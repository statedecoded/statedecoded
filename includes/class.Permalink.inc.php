<?php

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
}
