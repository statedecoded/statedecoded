<?php

require_once(INCLUDE_PATH . '/class.DatabaseMigration.inc.php');

class Migration_201505052126040001 extends DatabaseMigration
{
	// Roll forward
	public function up() {
		/*
		 * We have to allow for a more specific key since we are now adding
		 * multiple records for some references where the section number is not
		 * unique.
		 */
		$this->queue('ALTER TABLE laws_references
		DROP INDEX `overlap`,
		ADD UNIQUE KEY `overlap`
	   	(`law_id`,`target_section_number`,`target_law_id`,`edition_id`)');

		$this->execute();
	}

	// Roll back
	public function down() {
		$this->queue('ALTER TABLE laws_references
		DROP INDEX `overlap`,
		ADD UNIQUE KEY `overlap`
	   	(`law_id`,`target_section_number`)');

		$this->execute();
	}
}
