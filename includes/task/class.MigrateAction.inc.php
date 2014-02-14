<?php

/*
 * Migrate Action
 *
 * Updates the database to the latest version.
 *
 * NOTE: Currently only supports upgrading to the latest version.
 *
 * TODO:
 *   + Support rolling to a specific version.
 *   + Support rolling back.
 */

require_once 'class.CliAction.inc.php';

class MigrateAction extends CliAction
{
	static public $name = 'migrate';
	static public $summary = 'Updates the database schema.';

	public function __construct()
	{
		$this->db = new Database( PDO_DSN, PDO_USERNAME, PDO_PASSWORD );
	}

	public function execute($args = array())
	{
		if($this->checkSetup())
		{
			return $this->doMigrations();
		}
		else
		{
			return "Unspecified error occured running migration setup.\n\n";
		}
	}

	public function checkSetup()
	{
		$statement = $this->db->query('SHOW TABLES LIKE "migrations"');
		if($statement !== FALSE && $statement->rowCount() > 0)
		{
			return TRUE;
		}
		else
		{
			return $this->doSetup();
		}

	}

	public function doSetup()
	{
		// Todo : don't print here.  We need a logging system.
		print "Creating migrations table.\n";

		$query = '
			CREATE TABLE `migrations` (
				`id` INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
				`name` varchar(255) NOT NULL,
				`date_created` datetime NOT NULL,
				`date_modified` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				KEY `name` (`name`)
			)';

		$this->db->exec($query);

		return TRUE;
	}

	public function doMigrations()
	{
		/*
		 * Get completed migrations from db.
		 */
		$done_migrations = $this->getDoneMigrations();


		/*
		 * Get all migrations from files.
		 */
		$all_migrations = $this->getAllMigrations();

		/*
		 * Determine what's left to be done.
		 */
		$migrations = array_diff($all_migrations, $done_migrations);

		if(count($migrations) < 1)
		{
			return "All up to date, nothing to migrate.\n";
		}
		else
		{
			sort($migrations);

			foreach($migrations as $migration_name)
			{
				$this->doMigration($migration_name);
			}
		}

	}

	public function getDoneMigrations()
	{
		$done_migrations = array();

		$query = 'SELECT name FROM `migrations`';
		$statement = $this->db->query($query);

		if($statement->rowCount() > 0)
		{
			while($done_migration = $statement->fetchColumn())
			{
				$done_migrations[] = $done_migration;
			}
		}

		return $done_migrations;
	}

	public function getAllMigrations()
	{
		$all_migrations = array();
		$migrations = glob(INCLUDE_PATH . '/migrations/class.Migration_*.inc.php');

		/*
		 * We just care about the migration number, for now.
		 */
		foreach($migrations as $migration)
		{
			preg_match('/class\.Migration_(.*?)\.inc\.php/', $migration, $matches);
			$all_migrations[] = $matches[1];
		}

		return $all_migrations;
	}

	public function doMigration($migration_name)
	{
		print "Running migration $migration_name\n";
		require_once(INCLUDE_PATH . '/migrations/class.Migration_'.$migration_name.'.inc.php');
		$obj = 'Migration_' . $migration_name;
		$migration = new $obj($this->db);

		try
		{
			$migration->up();
		}
		catch(Exception $except)
		{
			print "An error occurred running migration $migration_name.\n\n";
			print $except->getMessage();
			print "\n\nExiting.\n";
			exit();
		}

		$this->recordMigration($migration_name);
	}

	public function recordMigration($migration_name)
	{
		static $statement;
		if(empty($statement))
		{
			$statement = $this->db->prepare('INSERT INTO migrations SET name=?, ' .
				'date_created=NOW(), date_modified=NOW()');
		}

		return $statement->execute(array($migration_name));
	}
}
