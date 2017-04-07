<?php

require_once(INCLUDE_PATH . '/class.DatabaseMigration.inc.php');

class Migration_201607201956460001 extends DatabaseMigration
{
	public $tables = array(
		'dictionary',
		'dictionary_general',
		'editions',
		'laws',
		'structure',
		'tags',
		'text',
		'text_sections',
	);

	// Roll forward
	public function up() {

		/*
		 * Change collation for tables to support case-insensitive search.
		 */
		foreach($this->tables as $table)
		{
			$this->queue('ALTER TABLE `' . $table . '` CONVERT TO CHARACTER SET utf8 COLLATE utf8_general_ci');
		}

		$this->execute();

	}

	// Roll back
	public function down() {

		foreach($this->tables as $table)
		{
			$this->queue('ALTER TABLE `' . $table . '` CONVERT TO CHARACTER SET utf8 COLLATE utf8_bin');
		}

		$this->execute();

	}
}
