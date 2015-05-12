<?php

/**
 * Database migration task for The State Decoded.
 *
 * PHP version 5
 *
 * @license		http://www.gnu.org/licenses/gpl.html GPL 3
 * @version		0.9
 * @link		http://www.statedecoded.com/
 * @since		0.9
 */

abstract class DatabaseMigration
{
	protected $db;

	public $queries = array();
	public $verbose = FALSE;

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
	 * We allow the user the option of using transactions, which is
	 * turned on by default.  However, note that some actions in MySQL
	 * implicitly commit transactions, including altering any table
	 * structures!
	 * http://dev.mysql.com/doc/refman/5.1/en/implicit-commit.html
	 */
	public function execute($use_transactions = TRUE)
	{
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
				if($this->db->exec($query) === FALSE)
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
				$this->db->rollBack();

				/*
				 * Bubble up our exception.
				 */
				throw $except;

				return FALSE;
			}

		}

		if($use_transactions)
		{
			if($this->verbose)
			{
				print "-> COMMIT Transaction\n";
			}
			/*
			 * End the transaction.
			 */
			$this->db->commit();
		}

		return TRUE;
	}
}
