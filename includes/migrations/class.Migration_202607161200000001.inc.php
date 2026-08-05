<?php

require_once(INCLUDE_PATH . '/class.DatabaseMigration.inc.php');

class Migration_202607161200000001 extends DatabaseMigration
{
	// Roll forward
	public function up() {
		$this->queueIndex('laws_references', 'target_lookup',
			'ALTER TABLE `laws_references`
			ADD INDEX `target_lookup` (`edition_id`, `target_section_number`)');

		$this->execute();
	}

	// Roll back
	public function down() {
		$this->queue('ALTER TABLE `laws_references` DROP INDEX `target_lookup`');

		$this->execute();
	}
}
