<?php

require_once(INCLUDE_PATH . '/class.DatabaseMigration.inc.php');

class Migration_201504251824130001 extends DatabaseMigration
{
	// Roll forward
	public function up() {
		$this->queue('ALTER TABLE `permalinks` ADD INDEX `token` (`token`)');
		$this->execute();
	}

	// Roll back
	public function down() {
		$this->queue('ALTER TABLE `permalinks` DROP INDEX `token`');
		$this->execute();
	}
}
