<?php

/**
 * This suite of tests requires a real connection to the database.
 * Since the Database class directly extends PDO, we can't verify
 * the connection without it.  We mock wherever possible.
 */

class DatabaseTest extends PHPUnit_Framework_TestCase
{
    protected function setUp()
    {
    	/*
    	 * Setup DB
    	 */
		$this->options = array(PDO::ATTR_ERRMODE => PDO::ERRMODE_SILENT);
		$this->db = new Database( PDO_DSN, PDO_USERNAME, PDO_PASSWORD, $this->options );

		/*
		 * Setup DatabaseStatement & PDOStatement Mock
		 */
		$ignore_methods = array('__wakeup', '__sleep');
		$methods = array_diff( get_class_methods('PDOStatement'), $ignore_methods );

		$this->query = 'SELECT 1=1 AS one';

		$this->pdo_statement_mock = $this->getMock('PDOStatement', $methods);

		$this->database_mock = $this->getMock('Database', get_class_methods('Database'),
			array(PDO_DSN, PDO_USERNAME, PDO_PASSWORD, $this->options));

		$this->statement = new DatabaseStatement($this->db, $this->pdo_statement_mock,
			$this->query);

		$this->args = array(
			'one',
			'two',
			'three',
			'four',
			'five',
			'six'
		);

		/*
		 * Setup some static counters
		 */
		$this->counters = array();
    }


	/**
	 * Database Class Tests
	 */

	public function testConstruct()
	{
		/*
		 * Test the key values.
		 */
		$this->assertEquals( PDO_DSN, $this->db->_properties['dsn'],
			'DSN is set.' );
		$this->assertEquals( PDO_USERNAME, $this->db->_properties['username'],
			'Username is set.' );
		$this->assertEquals( PDO_PASSWORD, $this->db->_properties['password'],
			'Password is set.' );
		$this->assertEquals( $this->options, $this->db->_properties['driver_options'],
			'Driver options are set.' );

		/*
		 * Test that we have a connection. Technically, this is a functional test,
		 * but we have no way of knowing that the underlying system made a connection.
		 */
		$query = 'SHOW TABLES';

		$result = $this->db->query($query);

		$this->assertTrue( $result !== FALSE,
			'Queries should work, if we are connected.' );
	}

	/**
	 * @depends testConstruct
	 */
	public function testQuery()
	{
		$query = 'SHOW TABLES';

		$result = $this->db->query($query);

		$this->assertTrue( $result !== FALSE,
			'Queries should work, if we are connected.' );
		$this->assertEquals( $this->db->_query, $query,
			'We should be storing the query.' );
	}

	/**
	 * @depends testConstruct
	 */
	public function testExec()
	{
		$query = 'SHOW TABLES';

		$result = $this->db->exec($query);

		$this->assertTrue( $result !== FALSE,
			'Queries should work, if we are connected.' );
		$this->assertEquals( $this->db->_query, $query,
			'We should be storing the query.' );
	}

	/**
	 * @depends testConstruct
	 */
	public function testPrepare()
	{
		$query = 'SHOW TABLES';

		$statement = $this->db->prepare($query);

		$this->assertEquals( get_class($statement), 'DatabaseStatement',
			'prepare() should return a DatabaseStatement, not a PDOStatement.' );

		$this->assertEquals( $this->db->_query, $query,
			'We should be storing the query.' );
	}

	/**
	 * @depends testConstruct
	 */
	public function testReconnect()
	{
		$database = $this->db->reconnect();

		$this->assertEquals( get_class($database), 'Database',
			'prepare() should return a DatabaseStatement, not a PDOStatement.' );

		$this->assertEquals( $database->_properties['dsn'],
			$this->db->_properties['dsn'],
			'DSN is set.' );

		$this->assertEquals( $database->_properties['username'],
			$this->db->_properties['username'],
			'Username is set.' );

		$this->assertEquals( $database->_properties['password'],
			$this->db->_properties['password'],
			'Password is set.' );

		$this->assertEquals( $database->_properties['driver_options'],
			$this->db->_properties['driver_options'],
			'Driver options are set.' );
	}

	/**
	 * DatabaseStatement Tests
	 * We include these here since we can't do dependencies between test suites.
	 */

	/**
	 * First set: make sure we didn't make any stupid typos.
	 */


	public function testFetch()
	{
		// 3 args.
		$this->pdo_statement_mock->expects($this->once())
                 ->method('fetch')
                 ->with($this->equalTo($this->args[0]),
                 	$this->equalTo($this->args[1]),
                	$this->equalTo($this->args[2]));

		$this->statement->fetch( $this->args[0], $this->args[1], $this->args[2] );
	}

	public function testBindParam()
	{
		// 5 args.
		$this->pdo_statement_mock->expects($this->once())
                 ->method('bindParam')
                 ->with($this->equalTo($this->args[0]),
                 	$this->equalTo($this->args[1]),
                 	$this->equalTo($this->args[2]),
                 	$this->equalTo($this->args[3]),
                	$this->equalTo($this->args[4]));

		$this->statement->bindParam( $this->args[0], $this->args[1], $this->args[2],
			$this->args[3], $this->args[4] );

	}

	public function testBindColumn()
	{
		// 5 args.
		$this->pdo_statement_mock->expects($this->once())
                 ->method('bindColumn')
                 ->with($this->equalTo($this->args[0]),
                 	$this->equalTo($this->args[1]),
                 	$this->equalTo($this->args[2]),
                 	$this->equalTo($this->args[3]),
                	$this->equalTo($this->args[4]));

		$this->statement->bindColumn( $this->args[0], $this->args[1], $this->args[2],
			$this->args[3], $this->args[4] );
	}

	public function testBindValue()
	{
		// 3 args.
		$this->pdo_statement_mock->expects($this->once())
                 ->method('bindValue')
                 ->with($this->equalTo($this->args[0]),
                 	$this->equalTo($this->args[1]),
                	$this->equalTo($this->args[2]));

		$this->statement->bindValue( $this->args[0], $this->args[1], $this->args[2] );
	}

	public function testRowCount()
	{
		// 0 args.
		$this->pdo_statement_mock->expects($this->once())
                 ->method('rowCount')
                 ->with();

		$this->statement->rowCount();
	}

	public function testFetchColumn()
	{
		// 1 arg.
		$this->pdo_statement_mock->expects($this->once())
                 ->method('fetchColumn')
                 ->with($this->equalTo($this->args[0]));

		$this->statement->fetchColumn( $this->args[0] );
	}

	public function testFetchAll()
	{
		// 3 args.
		$this->pdo_statement_mock->expects($this->once())
                 ->method('fetchAll')
                 ->with($this->equalTo($this->args[0]),
                 	$this->equalTo($this->args[1]),
                	$this->equalTo($this->args[2]));

		$this->statement->fetchAll( $this->args[0], $this->args[1], $this->args[2] );
	}

	public function testFetchObject()
	{
		// 2 args.
		$this->pdo_statement_mock->expects($this->once())
                 ->method('fetchObject')
                 ->with($this->equalTo($this->args[0]),
                 	$this->equalTo($this->args[1]));

		$this->statement->fetchObject( $this->args[0], $this->args[1] );
	}

	public function testErrorCode()
	{
		// 0 args.
		$this->pdo_statement_mock->expects($this->once())
                 ->method('errorCode')
                 ->with();

		$this->statement->errorCode();
	}

	public function testErrorInfo()
	{
		// 0 args.
		$this->pdo_statement_mock->expects($this->once())
                 ->method('errorInfo')
                 ->with();

		$this->statement->errorInfo();
	}

	public function testSetAttribute()
	{
		// 2 args.
		$this->pdo_statement_mock->expects($this->once())
                 ->method('setAttribute')
                 ->with($this->equalTo($this->args[0]),
                 	$this->equalTo($this->args[1]));

		$this->statement->setAttribute( $this->args[0], $this->args[1] );
	}

	public function testGetAttribute()
	{
		// 1 arg.
		$this->pdo_statement_mock->expects($this->once())
                 ->method('getAttribute')
                 ->with($this->equalTo($this->args[0]));

		$this->statement->getAttribute( $this->args[0] );
	}

	public function testColumnCount()
	{
		// 0 args.
		$this->pdo_statement_mock->expects($this->once())
                 ->method('columnCount')
                 ->with();

		$this->statement->columnCount();
	}

	public function testGetColumnMeta()
	{
		// 1 arg.
		$this->pdo_statement_mock->expects($this->once())
                 ->method('getColumnMeta')
                 ->with($this->equalTo($this->args[0]));

		$this->statement->getColumnMeta( $this->args[0] );
	}

	public function testSetFetchMode()
	{
		$this->pdo_statement_mock->expects($this->once())
                 ->method('setFetchMode')
                 ->with($this->equalTo($this->args[0]),
                 	$this->equalTo($this->args[1]),
                	$this->equalTo($this->args[2]));

		$this->statement->setFetchMode( $this->args[0], $this->args[1], $this->args[2] );
	}

	public function testNextRowset()
	{
		// 0 args.
		$this->pdo_statement_mock->expects($this->once())
                 ->method('nextRowset')
                 ->with();

		$this->statement->nextRowset();
	}

	public function testCloseCursor()
	{
		// 0 args.
		$this->pdo_statement_mock->expects($this->once())
                 ->method('closeCursor')
                 ->with();

		$this->statement->closeCursor();
	}

	public function testDebugDumpParams()
	{
		// 0 args.
		$this->pdo_statement_mock->expects($this->once())
                 ->method('debugDumpParams')
                 ->with();

		$this->statement->debugDumpParams();
	}


	/*
	 * Second set: slightly more useful tests.
	 */


	/**
	 * Basic test, no errors
	 *
	 * Execute should be called once and return not-FALSE.
	 */
	public function testExecuteSuccess()
	{
		$this->pdo_statement_mock->expects($this->exactly(1))
        	->method('execute')
            ->will($this->returnValue( $this->args[0] ))
            ->with($this->equalTo( $this->args[1] ));

		$this->assertEquals( $this->statement->execute($this->args[1]), $this->args[0] );
	}

	/**
	 * Test error recovery.
	 *
	 * We need to stub the recoverError() method on our object.
	 */
	public function testExecuteReconnect()
	{
		$statement = $this->getMock('DatabaseStatement', array('recoverError'),
			array($this->db, $this->pdo_statement_mock, $this->query));
		$statement->expects($this->once())
			->method('recoverError')
			->will($this->returnValue( TRUE ));

		/*
		 * Execute should be called twice.
		 */
		$this->pdo_statement_mock->expects($this->exactly(2))
        	->method('execute')
            ->will($this->returnCallback( array($this, 'executeCallback') ));

		$this->assertTrue( $statement->execute() );
	}

	/**
	 * Helper function, callback for execute() tests.
	 */
	public function executeCallback()
	{
		if(!isset($this->counters['execute']))
		{
			$this->counters['execute'] = FALSE;
		}
		else
		{
			$this->counters['execute'] = TRUE;
		}

		return $this->counters['execute'];
	}

	/**
	 * Test failure to recover. Should throw an Exception.
	 *
     * @expectedException Exception
	 *
	 * We need to stub the recoverError() method on our object.
	 */
	public function testExecuteFailure()
	{
		$statement = $this->getMock('DatabaseStatement', array('recoverError'),
			array($this->db, $this->pdo_statement_mock, $this->query));
		$statement->expects($this->once())
			->method('recoverError')
			->will($this->returnValue( FALSE ));

		/*
		 * Execute should be called twice.
		 */
		$this->pdo_statement_mock->expects($this->exactly(1))
        	->method('execute')
            ->will($this->returnCallback( array($this, 'executeCallback') ));

		$this->assertFalse( $statement->execute() );
	}

	/**
	 * Attempt to recover from a disconnect error.
	 * We must do a lot of mocking and setup here.
	 */
	public function testRecoverErrorStatement()
	{
		/*
		 * Mock the main database statement, so we can test just the
		 * recoverError() method.
		 */
		$statement = $this->getMock('DatabaseStatement', array(),
			// Constructor args
			array(&$this->database_mock, $this->pdo_statement_mock, $this->query) );
		$statement->expects($this->once())
			->method('errorInfo')
			->will($this->returnValue( array(TRUE, TRUE, 'MySQL server has gone away') ));

		/*
		 * Set our expectations of the database mock.
		 */

		$statement_mock = new StdClass();
		$statement_mock->pdo_statement =& $this->pdo_statement_mock;

		$this->database_mock->expects($this->once())
			->method('reconnect')
			->will($this->returnValue( $this->database_mock ));

		$this->database_mock->expects($this->once())
			->method('prepare')
			->with($this->query)
			->will($this->returnValue( $statement_mock ));

		$this->assertTrue($statement->recoverError());

		$this->assertEquals($this->database_mock,
			PHPUnit_Framework_Assert::readAttribute($statement, 'database'));

		$this->assertEquals($this->pdo_statement_mock,
			PHPUnit_Framework_Assert::readAttribute($statement, 'pdo_statement'));
	}

	/**
	 * Same as above, only with the error generated at the Database level.
	 */
	public function testRecoverErrorDatabase()
	{
		/*
		 * Mock the main database statement, so we can test just the
		 * recoverError() method.
		 */
		$statement = $this->getMock('DatabaseStatement', array(),
			// Constructor args
			array(&$this->database_mock, $this->pdo_statement_mock, $this->query) );
		$statement->expects($this->once())
			->method('errorInfo')
			->will($this->returnValue( array(FALSE, FALSE, '') ));

		$this->database_mock->expects($this->once())
			->method('errorInfo')
			->will($this->returnValue( array(TRUE, TRUE, 'MySQL server has gone away') ));

		/*
		 * Set our expectations of the database mock.
		 */

		$statement_mock = new StdClass();
		$statement_mock->pdo_statement =& $this->pdo_statement_mock;

		$this->database_mock->expects($this->once())
			->method('reconnect')
			->will($this->returnValue( $this->database_mock ));

		$this->database_mock->expects($this->once())
			->method('prepare')
			->with($this->query)
			->will($this->returnValue( $statement_mock ));

		$this->assertTrue($statement->recoverError());

		$this->assertEquals($this->database_mock,
			PHPUnit_Framework_Assert::readAttribute($statement, 'database'));

		$this->assertEquals($this->pdo_statement_mock,
			PHPUnit_Framework_Assert::readAttribute($statement, 'pdo_statement'));
	}

	/**
	 * Same as above, only with unrecoverable error.
	 */
	public function testRecoverErrorFailure()
	{
		/*
		 * Mock the main database statement, so we can test just the
		 * recoverError() method.
		 */
		$statement = $this->getMock('DatabaseStatement', array(),
			// Constructor args
			array(&$this->database_mock, $this->pdo_statement_mock, $this->query) );
		$statement->expects($this->once())
			->method('errorInfo')
			->will($this->returnValue( array(FALSE, FALSE, '') ));

		$this->database_mock->expects($this->once())
			->method('errorInfo')
			->will($this->returnValue( array(TRUE, TRUE, 'Derp!') ));// Unrecoverable error.

		/*
		 * Set our expectations of the database mock.
		 */

		$statement_mock = new StdClass();
		$statement_mock->pdo_statement =& $this->pdo_statement_mock;

		$this->database_mock->expects($this->never())
			->method('reconnect')
			->will($this->returnValue( $this->database_mock ));

		$this->database_mock->expects($this->never())
			->method('prepare')
			//->with($this->query)
			->will($this->returnValue( $statement_mock ));

		$this->assertFalse($statement->recoverError());

	}

	public function testFormatErrors()
	{
		$this->assertEquals(
			print_r($this->args, TRUE),
			$this->statement->formatErrors($this->args)
		);
	}

	public function testFetchErrors()
	{
		$statement = $this->getMock('DatabaseStatement', array(),
			// Constructor args
			array(&$this->database_mock, &$this->pdo_statement_mock, $this->query) );

		$statement->expects($this->exactly(2))
			->method('errorInfo')
			->will($this->returnValue( $this->args[0] ));
		$statement->expects($this->exactly(2))
			->method('errorCode')
			->will($this->returnValue( $this->args[1] ));
		$this->database_mock->expects($this->exactly(2))
			->method('errorInfo')
			->will($this->returnValue( $this->args[2] ));
		$this->database_mock->expects($this->exactly(2))
			->method('errorCode')
			->will($this->returnValue( $this->args[3] ));
		$this->pdo_statement_mock->expects($this->once())
			->method('debugDumpParams')
			->will($this->returnValue( $this->args[4] ));


		$this->assertEquals(
			$statement->fetchErrors($this->args[5]),
			array(
				'Statement Info'       => $this->args[0],
				'Statement Code'       => $this->args[1],
				'Database Info'        => $this->args[2],
				'Database Code'        => $this->args[3],
				'Statement Parameters' => $this->args[4],
				'Input Parameters'     => $this->args[5]
			)
		);


	}

	/**
	 * Functional Test for Timeout.
	 * @depends testPrepare
	 */
    public function testTimeout()
    {
		// Setup our new Database class
		$db = new Database( PDO_DSN, PDO_USERNAME, PDO_PASSWORD,
			array(PDO::ATTR_ERRMODE => PDO::ERRMODE_SILENT) );


		// Make the timeout 1 second.
		$db->exec('SET GLOBAL connect_timeout=1');
		$db->exec('SET GLOBAL wait_timeout=1');
		// Wait two seconds.
		sleep(2);

		// Run a query to see if it reconnects.
		$statement = $db->prepare('SELECT * FROM editions');

		$result = $statement->execute();

		$this->assertEquals(true, $result,
			'Database reconnects on timeout.');
	}
}

