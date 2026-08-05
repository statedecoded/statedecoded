<?php

require_once(INCLUDE_PATH . '/class.DatabaseMigration.inc.php');

class Migration_20260804202443 extends DatabaseMigration
{
	// Roll forward
	public function up() {

		/*
		 * Full-text indexes for searching.
		 *
		 * SqlSearchEngine previously searched with REGEXP, which no index can
		 * serve: every search read every row of every law. On a full-sized
		 * legal code that is a full scan of tens of megabytes of text per
		 * query. These indexes let the same searches run through
		 * MATCH ... AGAINST instead.
		 *
		 * Note that InnoDB will not index tokens shorter than
		 * innodb_ft_min_token_size (three characters by default), so
		 * SqlSearchEngine keeps a REGEXP path for very short search terms.
		 */
		$this->queueIndex('laws', 'ft_laws_search',
			'ALTER TABLE `laws` ADD FULLTEXT INDEX `ft_laws_search` (`catch_line`, `text`)');

		$this->queueIndex('structure', 'ft_structure_search',
			'ALTER TABLE `structure` ADD FULLTEXT INDEX `ft_structure_search` (`name`)');

		$this->execute();
	}

	// Roll back
	public function down() {

		$this->queue('ALTER TABLE `laws` DROP INDEX `ft_laws_search`');
		$this->queue('ALTER TABLE `structure` DROP INDEX `ft_structure_search`');

		$this->execute();
	}
}
