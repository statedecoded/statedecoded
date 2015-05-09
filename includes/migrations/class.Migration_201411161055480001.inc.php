<?php

require_once(INCLUDE_PATH . '/class.DatabaseMigration.inc.php');

class Migration_201411161055480001 extends DatabaseMigration
{
	// Roll forward
	public function up() {
		$this->queue('ALTER TABLE `permalinks` ADD INDEX `permalink` (`permalink`)');
		$this->queue('ALTER TABLE `permalinks` ADD INDEX `preferred` (`preferred`)');
		$this->execute();
	}

	// Roll back
	public function down() {
		$this->queue('ALTER TABLE `permalinks` DROP INDEX `permalink`');
		$this->queue('ALTER TABLE `permalinks` DROP INDEX `preferred`');
		$this->execute();
	}
}
