<?php

/*
 * Provides methods that can be added to setUp() and tearDown() to
 * ensure the database is in a consistent state while testing.
 */
class TestDbHelper
{

	protected $db;
	protected $logger;
	protected $parser;

	/*
	 * @param array $options
	 *
	 * $options = array('db' => $db
	 * 					'logger' => $logger,
	 * 					'parser' => $parser)
	 */
	public function __construct($options)
	{
		$this->db = $options['db'];
		$this->logger = $options['logger'];
		$this->parser = $options['parser'];
	}

	/*
	 * Populate the database and run migrations.
	 *
	 * Add to your TestCase setUp()
	 */
	public function setupDb()
	{
		if ($this->parser->test_environment() === FALSE)
		{
			return $this->assertTrue(false, 'There was an error testing the environment.');
		}

		if ($this->parser->populate_db() === FALSE)
		{
			return $this->assertTrue(false, 'There was an error populating the database.');
		}

		$this->parser->run_migrations();
	}

	/*
	 * Delete database tables.
	 *
	 * Add to your TestCase tearDown()
	 *
	 * !!! Data will be lost, use with care. !!!
	 */
	public function destroyDb()
	{
		/* These functions are noisy, quiet log level */
		$wasLevel = $this->logger->level;
		$this->logger->level = 10;

		$this->parser->clear_db();

		/* api_keys not included in clear_db() */
		$sql = 'DELETE FROM api_keys';
		$statement = $this->db->prepare($sql);
		$result = $statement->execute();

		$this->parser->clear_index();

		/* Return logging level */
		$this->logger->level = $wasLevel;
	}

}
