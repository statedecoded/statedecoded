<?php

require_once(INCLUDE_PATH . '/class.DatabaseMigration.inc.php');

class Migration_201704132033450001 extends DatabaseMigration
{
	// Roll forward
	public function up() {
    $this->queue('ALTER TABLE dictionary MODIFY term varchar(255)');
    $this->execute();
	}

	// Roll back
	public function down() {
    // This will truncate data, so it won't run on modern MySQL.
    // To be non-destructive, we just don't rollback on this one.
	}
}
