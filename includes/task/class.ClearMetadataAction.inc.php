<?php

require_once 'class.CliAction.inc.php';
require_once CUSTOM_FUNCTIONS;

global $db;

class ClearMetadataAction extends CliAction
{
	static public $name = 'clearmetadata';
	static public $summary = 'Deletes law metadata from the database.';

	public function __construct($args = array())
	{
		parent::__construct($args);

		global $db;
		$db = new Database( PDO_DSN, PDO_USERNAME, PDO_PASSWORD );
		$this->db = $db;

		$this->logger = new Logger();
	}

	public function execute($args = array())
	{
		$query = 'DELETE FROM laws_meta WHERE 1=1 ';
		$query_args = array();

		if(isset($this->options['edition']))
		{
			$edition_obj = new Edition($this->db);
			$edition = $edition_obj->find_by_slug($this->options['edition']);

			if(!$edition) {
				die('Unable to find edition "'. $this->options['edition'].'"');
			}

			$query .= 'AND edition_id = :edition_id ';
			$query_args[':edition_id'] = $edition->id;
		}

		if(isset($this->options['field']))
		{
			$query .= 'AND meta_key = :field ';
			$query_args[':field'] = $this->options['field'];
		}

		$statement = $this->db->prepare($query);
		$result = $statement->execute($query_args);

		if($result)
		{
			print "Metadata cleared.";
		}
		else
		{
			print "There was a problem clearing the metadata.";
		}
	}

	public static function getHelp($args = array()) {
		return <<<EOS
statedecoded : import

Clears metadata from the database.

Usage:

  statedecoded import [--edition=slug] [--field=name]

Available options:

  --edition=slug
      The query will only clear out the edition selected. Expects an
      edition slug.

	--field=name
			If specified, only this field will be cleared.

EOS;

	}
}
