<?php

require_once(INCLUDE_PATH . '/class.DatabaseMigration.inc.php');

class Migration_201411021850320001 extends DatabaseMigration
{
	// Roll forward
	public function up() {
		$this->queue('ALTER TABLE `editions` ADD `last_import` DATETIME');
		$this->execute();
	}

	// Roll back
	public function down() {
		$this->queue('ALTER TABLE `editions` DROP `last_import`');
		$this->execute();
	}
}
