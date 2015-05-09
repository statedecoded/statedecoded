<?php

require_once(INCLUDE_PATH . '/class.DatabaseMigration.inc.php');

class Migration_201504272043290001 extends DatabaseMigration
{
	// Roll forward
	public function up() {
		$this->queue('ALTER TABLE `dictionary` ADD `edition_id` INT UNSIGNED');
		$this->queue('ALTER TABLE `dictionary` ADD INDEX `edition_id` (`edition_id`)');
		$this->queue('ALTER TABLE `dictionary` ADD CONSTRAINT `dictionary_editions` FOREIGN KEY `edition_id` (`edition_id`) REFERENCES `editions` (`id`) ON DELETE CASCADE');

		$this->execute();
	}

	// Roll back
	public function down() {
		$this->queue('ALTER TABLE `dictionary` DROP FOREIGN KEY `dictionary_editions`');
		$this->queue('ALTER TABLE `dictionary` DROP `edition_id`');

		$this->execute();
	}
}
