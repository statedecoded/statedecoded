<?php

require_once(INCLUDE_PATH . '/class.DatabaseMigration.inc.php');

class Migration_201411112123510001 extends DatabaseMigration
{
	// Roll forward
	public function up() {
		$this->queue('ALTER TABLE `permalinks` CHANGE `relational_id` `relational_id` int unsigned NULL');
		$this->queue('ALTER TABLE `permalinks` CHANGE `identifier` `identifier` varchar(64) NULL');
		$this->queue('ALTER TABLE `permalinks` CHANGE `token` `token` varchar(128) NULL');
		$this->execute();
	}

	// Roll back
	public function down() {
		$this->queue('ALTER TABLE `permalinks` CHANGE `relational_id` `relational_id` int unsigned NOT NULL');
		$this->queue('ALTER TABLE `permalinks` CHANGE `identifier` `identifier` varchar(64) NOT NULL');
		$this->queue('ALTER TABLE `permalinks` CHANGE `token` `token` varchar(128) NOT NULL');
		$this->execute();
	}
}
