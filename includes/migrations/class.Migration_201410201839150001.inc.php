<?php

require_once(INCLUDE_PATH . '/class.DatabaseMigration.inc.php');

class Migration_201410201839150001 extends DatabaseMigration
{
	public $tables = array(
		'laws_references',
		'text',
		'text_sections',
		'tags',
		'laws_meta'
	);

	// Roll forward
	public function up() {

		// Add edition_id to tables.
		foreach($this->tables as $table)
		{
			$this->queue('ALTER TABLE `'. $table . '` ' .
				'ADD `edition_id` INT UNSIGNED');
			$this->queue('ALTER TABLE `'. $table . '` ' .
				'ADD INDEX `edition_id` (`edition_id`)');
			$this->queue('ALTER TABLE `'. $table . '` ' .
				'ADD CONSTRAINT `'. $table . '_editions` ' .
				'FOREIGN KEY `edition_id` (`edition_id`) ' .
				'REFERENCES `editions` (`id`) ON DELETE CASCADE');
		}

		$this->execute();
	}

	// Roll back
	public function down() {

		foreach($this->tables as $table)
		{
			$this->queue('ALTER TABLE `'. $table . '` ' .
				'DROP FOREIGN KEY `'. $table . '_editions`');
			$this->queue('ALTER TABLE `'. $table . '` ' .
				'DROP `edition_id`');
		}

		$this->execute();
	}
}
