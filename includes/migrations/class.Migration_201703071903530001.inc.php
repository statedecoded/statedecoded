<?php

require_once(INCLUDE_PATH . '/class.DatabaseMigration.inc.php');

class Migration_201703071903530001 extends DatabaseMigration
{
	// Roll forward
	public function up() {
		$this->queue('ALTER TABLE text_sections MODIFY identifier varchar(255)');
		$this->queue('ALTER TABLE laws MODIFY section varchar(255) NOT NULL');
		$this->queue('ALTER TABLE structure MODIFY identifier varchar(255) NOT NULL');
		$this->queue('ALTER TABLE laws_references MODIFY target_section_number varchar(255)');
		$this->execute();
	}

	// Roll back
	public function down() {
		// This will truncate data, so it won't run on modern MySQL.
		// To be non-destructive, we just don't rollback on this one.
		// $this->queue('ALTER TABLE text_sections MODIFY identifier varchar(3)');
		// $this->queue('ALTER TABLE laws MODIFY section varchar( 16) NOT NULL');
		// $this->queue('ALTER TABLE structure MODIFY identifier varchar(16) NOT NULL');
		// $this->queue('ALTER TABLE laws_references MODIFY target_section_number varchar(16)');
		// $this->execute();
	}
}
