<?php

require_once(INCLUDE_PATH . '/class.DatabaseMigration.inc.php');

class Migration_201410262037580001 extends DatabaseMigration
{
	// Roll forward
	public function up() {
		$this->queue('ALTER TABLE `permalinks` ADD `permalink` TINYINT(1) DEFAULT 0');
		$this->queue('ALTER TABLE `permalinks` ADD `preferred` TINYINT(1) DEFAULT 0');
		$this->execute();
	}

	// Roll back
	public function down() {
		$this->queue('ALTER TABLE `permalinks` DROP `preferred`');
		$this->queue('ALTER TABLE `permalinks` DROP `permalink`');
		$this->execute();
	}
}
