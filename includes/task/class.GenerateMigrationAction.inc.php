<?php

/*
 * GenerateMigrationAction - creates a new DB migration
 *
 * We make several assumptions about migrations:
 *    1. Migrations coming from a single user that are interrelated
 *        will be sequential.
 *    2. Migrations coming from multiple users may *not* be sequential.
 *    3. System local time may be different between users.
 *
 * To solve these issues we do two simple things:
 *    1. Name all migrations with timestamps for sequence within a user.
 *    2. Run all migrations that have not been run, by keeping track of
 *       what has been run, not just a number.
 *
 * Todo:
 *    # Allow dependencies within migrations: "requires" flag or similar.
 */


require_once 'class.CliAction.inc.php';

class GenerateMigrationAction extends CliAction
{
	static public $name = 'generate-migration';
	static public $summary = 'Creates a database migration.';

	public function execute($args = array())
	{

		if (!file_exists(INCLUDE_PATH . 'migrations'))
		{
			mkdir (INCLUDE_PATH . 'migrations', 0755);
		}

		do
		{
			$migration = $this->generateFilename();
		}
		while (file_exists($migration['fullpath']));

		$content = <<<EOS
<?php

require_once(INCLUDE_PATH . '/class.DatabaseMigration.inc.php');

class Migration_{$migration['basename']} extends DatabaseMigration
{
	// Roll forward
	public function up() {

	}

	// Roll back
	public function down() {

	}
}

EOS;
		try {
			file_put_contents($migration['fullpath'], $content);
		}
		catch(Exception $except)
		{
			print $except->getMessage();
			exit;
		}

		print "Writing file " . $migration['fullpath'];

	}

	public function generateFilename()
	{
		static $counter;
		$counter++;

		$datetime = new DateTime();
		// Year + Month + Day + Hour + Minute + Second + counter
		// We need the counter because we can definitely run this
		// multiple times per second if automated.


		$basename = $datetime->format('YmdHis') . str_pad($counter, 4, '0', STR_PAD_LEFT);


		$filename = 'class.Migration_' . $basename . '.inc.php';

		$fullpath = INCLUDE_PATH . 'migrations/' . $filename;

		return array(
			'basename' => $basename,
			'filename' => $filename,
			'fullpath' => $fullpath
		);
	}

	static public function getHelp()
	{
		$include_path = INCLUDE_PATH;
		return <<<EOS
statedecoded : generate-migration

This action creates a skeleton database migration file in the folder:

  {$include_path}migrations/

This file will be named with the current date & time.  Please do not change the name, unless it follows this format!

Usage:

  statedecoded generate-migration
EOS;
	}
}
