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
}
