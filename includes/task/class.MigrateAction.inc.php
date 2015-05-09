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

	public function __construct($args = array())
	{
		parent::__construct($args);

		if(!isset($this->db))
		{
			$this->db = new Database( PDO_DSN, PDO_USERNAME, PDO_PASSWORD );
		}
	}

	public function execute($args = array())
	{
		if($this->checkSetup())
		{
			return $this->doMigrations($args);
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

	public function doMigrations($args = array())
	{
		/*
		 * If we're rolling back.
		 */
		if(isset($this->options['down']))
		{
			$migrations = $this->getDoneMigrations();
		}
		/*
		 * If we're moving forward.
		 */
		else
		{
			$migrations = $this->getUndoneMigrations();
		}

		if(count($migrations) < 1)
		{
			return "All up to date, nothing to migrate.\n";
		}
		else
		{

			foreach($migrations as $migration_name)
			{
				$this->doMigration($migration_name, $args);
			}
		}

		return "Done";

	}

	public function getDoneMigrations()
	{
		$done_migrations = array();

		$query = 'SELECT name FROM `migrations` ORDER BY id DESC';
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

	public function getUndoneMigrations()
	{
		/*
		 * Get all migrations from files.
		 */
		$all_migrations = $this->getAllMigrations();

		/*
		 * Determine what's left to be done.
		 */
		$migrations = array_diff($all_migrations, $this->getDoneMigrations());
		sort($migrations);

		return $migrations;
	}

	public function doMigration($migration_name, $args = array())
	{
		print "Running migration $migration_name";
		if(isset($this->options['down']))
		{
			print " ROLLBACK";
		}
		print "\n";

		require_once(INCLUDE_PATH . '/migrations/class.Migration_'.$migration_name.'.inc.php');
		$obj = 'Migration_' . $migration_name;
		$migration = new $obj($this->db);

		/*
		 * Set verbose mode.
		 */
		if(isset($this->options['verbose']))
		{
			$migration->verbose = TRUE;
		}

		try
		{
			if(isset($this->options['down']))
			{
				$migration->down();
			}
			else
			{
				$migration->up();
			}
		}
		catch(Exception $except)
		{
			print "An error occurred running migration $migration_name.\n\n";
			print $except->getMessage();
			print "\n\nExiting.\n";
			exit();
		}

		$this->recordMigration($migration_name, $args);
	}

	public function recordMigration($migration_name, $args = array())
	{
		// We are assuming there is never a mix of down and up in the same
		// action.  Just be careful, ok?
		static $statement;
		if(empty($statement))
		{
			if(isset($this->options['down']))
			{
				$statement = $this->db->prepare('DELETE FROM migrations WHERE name=?');
			}
			else
			{
				$statement = $this->db->prepare('INSERT INTO migrations SET name=?, ' .
					'date_created=NOW(), date_modified=NOW()');
			}
		}

		return $statement->execute(array($migration_name));
	}

	static public function getHelp()
	{
		return <<<EOS
statedecoded : migrate

This action updates the database.  Currently, it is all-or-nothing, we cannot do incremental updates!

Usage:

  statedecoded migrate [--verbose] [--down]

Available options:

  --verbose : Prints detailed logs of queries run.
  --down    : Rolls back migrations, if possible.
EOS;
	}
}
