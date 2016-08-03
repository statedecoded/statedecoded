<?php

require_once(INCLUDE_PATH . '/class.DatabaseMigration.inc.php');

class Migration_201608030736220001 extends DatabaseMigration
{
	// Roll forward
	public function up() {
		$this->queue('ALTER TABLE `laws` DROP FOREIGN KEY `laws_structure`');
		$this->queue('ALTER TABLE `structure` MODIFY `id` int unsigned NOT NULL AUTO_INCREMENT');
		$this->queue('ALTER TABLE `laws` MODIFY `structure_id` int unsigned DEFAULT NULL');
		$this->queue('ALTER TABLE `laws` ADD CONSTRAINT `laws_structure` FOREIGN KEY (`structure_id`) REFERENCES `structure` (`id`) ON DELETE CASCADE');
		$this->execute();
	}

	// Roll back
	public function down() {
		$this->queue('ALTER TABLE `laws` DROP FOREIGN KEY `laws_structure`');
		$this->queue('ALTER TABLE `structure` MODIFY `id` smallint unsigned NOT NULL AUTO_INCREMENT');
		$this->queue('ALTER TABLE `laws` MODIFY `structure_id` smallint unsigned DEFAULT NULL');
		$this->queue('ALTER TABLE `laws` ADD CONSTRAINT `laws_structure` FOREIGN KEY (`structure_id`) REFERENCES `structure` (`id`) ON DELETE CASCADE');
		$this->execute();
	}
}
