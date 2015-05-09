<?php

require_once(INCLUDE_PATH . '/class.DatabaseMigration.inc.php');

class Migration_201402141654480001 extends DatabaseMigration
{
	// Roll forward
	public function up() {

		$this->queue('ALTER TABLE `dictionary` ADD CONSTRAINT `dictionary_law` FOREIGN KEY `law_id` (`law_id`) REFERENCES `laws` (`id`) ON DELETE CASCADE');

		$this->queue('ALTER TABLE `laws` ADD CONSTRAINT `laws_structure` FOREIGN KEY `structure_id` (`structure_id`) REFERENCES `structure` (`id`) ON DELETE CASCADE');
		$this->queue('ALTER TABLE `laws` ADD CONSTRAINT `laws_editions` FOREIGN KEY `edition_id` (`edition_id`) REFERENCES `editions` (`id`) ON DELETE CASCADE;');

		$this->queue('ALTER TABLE `laws_meta` CHANGE `law_id` `law_id` INT UNSIGNED');
		$this->queue('ALTER TABLE `laws_meta` ADD CONSTRAINT `laws_meta_laws` FOREIGN KEY `law_id` (`law_id`) REFERENCES `laws` (`id`) ON DELETE CASCADE;');

		$this->queue('ALTER TABLE `laws_references` ADD CONSTRAINT `laws_references_laws` FOREIGN KEY `law_id` (`law_id`) REFERENCES `laws` (`id`) ON DELETE CASCADE');

		// Skip `laws_views` for now.

		$this->queue('ALTER TABLE `permalinks` ADD `edition_id` INT UNSIGNED');
		$this->queue('ALTER TABLE `permalinks` ADD INDEX `edition_id` (`edition_id`)');
		$this->queue('ALTER TABLE `permalinks` ADD CONSTRAINT `permalinks_editions` FOREIGN KEY `edition_id` (`edition_id`) REFERENCES `editions` (`id`) ON DELETE CASCADE');

		$this->queue('ALTER TABLE `structure` ADD CONSTRAINT `structure_editions` FOREIGN KEY `edition_id` (`edition_id`) REFERENCES `editions` (`id`) ON DELETE CASCADE');

		$this->queue('ALTER TABLE `tags` CHANGE `law_id` `law_id` INT UNSIGNED');
		$this->queue('ALTER TABLE `tags` ADD CONSTRAINT `tags_laws` FOREIGN KEY `law_id` (`law_id`) REFERENCES `laws` (`id`) ON DELETE CASCADE');

		$this->queue('ALTER TABLE `text` ADD CONSTRAINT `text_laws` FOREIGN KEY `law_id` (`law_id`) REFERENCES `laws` (`id`) ON DELETE CASCADE');

		$this->queue('ALTER TABLE `text_sections` ADD CONSTRAINT `text_sections_text` FOREIGN KEY `text_id` (`text_id`) REFERENCES `text` (`id`) ON DELETE CASCADE');

		$this->execute();
	}

	// Roll back
	public function down() {

		$this->queue('ALTER TABLE `dictionary` DROP FOREIGN KEY `dictionary_law`');

		$this->queue('ALTER TABLE `laws` DROP FOREIGN KEY `laws_structure`');
		$this->queue('ALTER TABLE `laws` DROP FOREIGN KEY `laws_editions`');

		$this->queue('ALTER TABLE `laws_meta` DROP FOREIGN KEY `laws_meta_laws`');
		$this->queue('ALTER TABLE `laws_meta` CHANGE `law_id` `law_id` INT');

		$this->queue('ALTER TABLE `laws_references` DROP FOREIGN KEY `laws_references_laws`');

		// Skip `laws_views` for now.

		$this->queue('ALTER TABLE `permalinks` DROP FOREIGN KEY `permalinks_editions`');
		$this->queue('ALTER TABLE `permalinks` DROP `edition_id`');

		$this->queue('ALTER TABLE `structure` DROP FOREIGN KEY `structure_editions`');

		$this->queue('ALTER TABLE `tags` DROP FOREIGN KEY `tags_laws`');
		$this->queue('ALTER TABLE `tags` CHANGE `law_id` `law_id` MEDIUMINT UNSIGNED');

		$this->queue('ALTER TABLE `text` DROP FOREIGN KEY `text_laws`');

		$this->queue('ALTER TABLE `text_sections` DROP FOREIGN KEY `text_sections_text`');

		$this->execute();
	}
}
