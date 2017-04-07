<?php

require_once(INCLUDE_PATH . '/class.DatabaseMigration.inc.php');

class Migration_201703192016290001 extends DatabaseMigration
{
  // Roll forward
  public function up() {
    $this->queue('ALTER TABLE permalinks MODIFY identifier varchar(255)');
    $this->queue('ALTER TABLE permalinks MODIFY token varchar(255) NOT NULL');
    $this->execute();
  }

  // Roll back
  public function down() {
    // This will truncate data, so it won't run on modern MySQL.
    // To be non-destructive, we just don't rollback on this one.
    // $this->queue('ALTER TABLE permalinks MODIFY identifier varchar(64)');
    // $this->queue('ALTER TABLE permalinks MODIFY token varchar(128) NOT NULL');
    // $this->execute();
  }
}
