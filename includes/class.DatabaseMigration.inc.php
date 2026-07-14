<?php

/**
 * Database migration task for The State Decoded.
 *
 * PHP version 8
 *
 * @license		http://www.gnu.org/licenses/gpl.html GPL 3
 * @version		1.0
 * @link		https://www.statedecoded.com/
 * @since		0.9
 */

abstract class DatabaseMigration
{
	protected $db;

	public $queries = [];
	public $verbose = false;

	public function __construct(&$db)
	{
		$this->db =& $db;
	}

	// Roll forward
	abstract public function up();

	// Roll back
	abstract public function down();

	public function queue($query)
	{
		$this->queries[] = $query;
	}

	/*
	 * Queue a query only if the named constraint does not already exist.
	 * Needed because MySQL DDL implicitly commits and cannot be rolled back,
	 * so a migration that partially applied cannot be re-run without this guard.
	 */
	public function queueConstraint($constraint_name, $query)
	{
		$statement = $this->db->prepare(
			'SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
			 WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = ?'
		);
		$statement->execute([$constraint_name]);
		if ((int) $statement->fetchColumn() === 0)
		{
			$this->queries[] = $query;
		}
	}

	/*
	 * Queue a query only if the named column does not already exist on the table.
	 */
	public function queueColumn($table, $column, $query)
	{
		$statement = $this->db->prepare(
			'SELECT COUNT(*) FROM information_schema.COLUMNS
			 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
		);
		$statement->execute([$table, $column]);
		if ((int) $statement->fetchColumn() === 0)
		{
			$this->queries[] = $query;
		}
	}

	/*
	 * Queue a query only if the named index does not already exist on the table.
	 */
	public function queueIndex($table, $index_name, $query)
	{
		$statement = $this->db->prepare(
			'SELECT COUNT(*) FROM information_schema.STATISTICS
			 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?'
		);
		$statement->execute([$table, $index_name]);
		if ((int) $statement->fetchColumn() === 0)
		{
			$this->queries[] = $query;
		}
	}

	/*
	 * Queue a CHANGE/MODIFY column query only if the column is not already the target type.
	 * $target_type should match the COLUMN_TYPE value from information_schema (e.g. 'int unsigned').
	 */
	public function queueColumnType($table, $column, $target_type, $query)
	{
		$statement = $this->db->prepare(
			'SELECT COUNT(*) FROM information_schema.COLUMNS
			 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?
			 AND COLUMN_TYPE = ?'
		);
		$statement->execute([$table, $column, $target_type]);
		if ((int) $statement->fetchColumn() === 0)
		{
			$this->queries[] = $query;
		}
	}

	/*
	 * We allow the user the option of using transactions, which is
	 * turned on by default.  However, note that some actions in MySQL
	 * implicitly commit transactions, including altering any table
	 * structures!
	 * http://dev.mysql.com/doc/refman/5.1/en/implicit-commit.html
	 */
	public function execute($use_transactions = true)
	{
		if(empty($this->queries))
		{
			return true;
		}

		if($use_transactions)
		{
			/*
			 * Start the transaction.
			 */
			$this->db->beginTransaction();
			if($this->verbose)
			{
				print "-> BEGIN Transaction\n";
			}
		}

		/*
		 * Run our queries.
		 */
		try {
			foreach($this->queries as $query)
			{
				if($this->db->exec($query) === false)
				{
					throw new Exception('Query failed: ' . $query);
				}
				if($this->verbose)
				{
					print "-> $query\n";
				}
			}
		}
		catch(Exception $except) {
			print $except->getMessage() . "\n";

			if($use_transactions)
			{
				print "Rolling back if possible. \nNote that some migrations are not able to be rolled back!\n\n";
				if($this->db->inTransaction())
				{
					$this->db->rollBack();
				}

				/*
				 * Bubble up our exception.
				 */
				throw $except;

				return false;
			}

		}

		if($use_transactions)
		{
			/*
			 * DDL statements (ALTER TABLE, etc.) implicitly commit the transaction in MySQL,
			 * so the transaction may already be gone by the time we reach here.
			 * Only call commit() if a transaction is still active.
			 */
			if($this->db->inTransaction())
			{
				if($this->verbose)
				{
					print "-> COMMIT Transaction\n";
				}
				$this->db->commit();
			}
		}

		return true;
	}
}
