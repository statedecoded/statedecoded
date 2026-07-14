<?php

/**
 * This suite of tests requires a real connection to the database.
 * Since the Database class directly extends PDO, we can't verify
 * the connection without it.  We mock wherever possible.
 */

class DatabaseTest extends PHPUnit\Framework\TestCase
{
    private array $options;
    private Database $db;
    private string $query;
    private PDOStatement $pdo_statement_mock;
    private Database $database_mock;
    private DatabaseStatement $statement;
    private array $counters;

    protected function setUp(): void
    {
        $this->options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_SILENT];
        $this->db = new Database(PDO_DSN, PDO_USERNAME, PDO_PASSWORD, $this->options);

        $this->query = 'SELECT 1=1 AS one';

        // createMock() disables the constructor and stubs all methods.
        $this->pdo_statement_mock = $this->createMock(PDOStatement::class);

        // Database extends PDO; disable the constructor so no real connection is made
        // for the mock (the real $this->db above is used for connectivity tests).
        $this->database_mock = $this->getMockBuilder(Database::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->statement = new DatabaseStatement(
            $this->db, $this->pdo_statement_mock, $this->query);

        $this->counters = [];
    }


    // -----------------------------------------------------------------------
    // Database connectivity tests
    // -----------------------------------------------------------------------

    public function testConstruct(): void
    {
        $this->assertEquals(PDO_DSN,      $this->db->_properties['dsn'],            'DSN is set.');
        $this->assertEquals(PDO_USERNAME, $this->db->_properties['username'],       'Username is set.');
        $this->assertEquals(PDO_PASSWORD, $this->db->_properties['password'],       'Password is set.');
        $this->assertEquals($this->options, $this->db->_properties['driver_options'], 'Driver options are set.');

        $result = $this->db->query('SHOW TABLES');
        $this->assertNotFalse($result, 'Queries should work when connected.');
    }

    /** @depends testConstruct */
    public function testQuery(): void
    {
        $query  = 'SHOW TABLES';
        $result = $this->db->query($query);

        $this->assertNotFalse($result, 'Queries should work when connected.');
        $this->assertEquals($query, $this->db->_query, 'Query should be stored.');
    }

    /** @depends testConstruct */
    public function testExec(): void
    {
        $query  = 'SHOW TABLES';
        $result = $this->db->exec($query);

        $this->assertNotFalse($result, 'Queries should work when connected.');
        $this->assertEquals($query, $this->db->_query, 'Query should be stored.');
    }

    /** @depends testConstruct */
    public function testPrepare(): void
    {
        $query     = 'SHOW TABLES';
        $statement = $this->db->prepare($query);

        $this->assertEquals('DatabaseStatement', get_class($statement),
            'prepare() should return a DatabaseStatement.');
        $this->assertEquals($query, $this->db->_query, 'Query should be stored.');
    }

    /** @depends testConstruct */
    public function testReconnect(): void
    {
        $database = $this->db->reconnect();

        $this->assertEquals('Database', get_class($database), 'reconnect() should return a Database.');
        $this->assertEquals($database->_properties['dsn'],            $this->db->_properties['dsn'],            'DSN matches.');
        $this->assertEquals($database->_properties['username'],       $this->db->_properties['username'],       'Username matches.');
        $this->assertEquals($database->_properties['password'],       $this->db->_properties['password'],       'Password matches.');
        $this->assertEquals($database->_properties['driver_options'], $this->db->_properties['driver_options'], 'Driver options match.');
    }


    // -----------------------------------------------------------------------
    // DatabaseStatement passthrough tests
    // -----------------------------------------------------------------------

    // Passthrough tests use real PDO constants so PHP 8.3's typed mock signatures are satisfied.

    public function testFetch(): void
    {
        $this->pdo_statement_mock->expects($this->once())
            ->method('fetch')
            ->with(PDO::FETCH_ASSOC, PDO::FETCH_ORI_NEXT, 0);

        $this->statement->fetch(PDO::FETCH_ASSOC, PDO::FETCH_ORI_NEXT, 0);
    }

    public function testBindParam(): void
    {
        $variable = 'test_value';
        $this->pdo_statement_mock->expects($this->once())
            ->method('bindParam')
            ->with(':param', $variable, PDO::PARAM_STR, 50, null);

        $this->statement->bindParam(':param', $variable, PDO::PARAM_STR, 50, null);
    }

    public function testBindColumn(): void
    {
        $variable = null;
        $this->pdo_statement_mock->expects($this->once())
            ->method('bindColumn')
            ->with(1, $variable, PDO::PARAM_STR, 0, null);

        $this->statement->bindColumn(1, $variable, PDO::PARAM_STR, 0, null);
    }

    public function testBindValue(): void
    {
        $this->pdo_statement_mock->expects($this->once())
            ->method('bindValue')
            ->with(':val', 'test_value', PDO::PARAM_STR);

        $this->statement->bindValue(':val', 'test_value', PDO::PARAM_STR);
    }

    public function testRowCount(): void
    {
        $this->pdo_statement_mock->expects($this->once())->method('rowCount');
        $this->statement->rowCount();
    }

    public function testFetchColumn(): void
    {
        $this->pdo_statement_mock->expects($this->once())
            ->method('fetchColumn')
            ->with(0);

        $this->statement->fetchColumn(0);
    }

    public function testFetchAll(): void
    {
        $this->pdo_statement_mock->expects($this->once())
            ->method('fetchAll')
            ->with(PDO::FETCH_ASSOC);

        $this->statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function testFetchObject(): void
    {
        $this->pdo_statement_mock->expects($this->once())
            ->method('fetchObject')
            ->with('stdClass', []);

        $this->statement->fetchObject('stdClass', []);
    }

    public function testErrorCode(): void
    {
        $this->pdo_statement_mock->expects($this->once())->method('errorCode');
        $this->statement->errorCode();
    }

    public function testErrorInfo(): void
    {
        $this->pdo_statement_mock->expects($this->once())->method('errorInfo');
        $this->statement->errorInfo();
    }

    public function testSetAttribute(): void
    {
        $this->pdo_statement_mock->expects($this->once())
            ->method('setAttribute')
            ->with(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        $this->statement->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    }

    public function testGetAttribute(): void
    {
        $this->pdo_statement_mock->expects($this->once())
            ->method('getAttribute')
            ->with(PDO::ATTR_DEFAULT_FETCH_MODE);

        $this->statement->getAttribute(PDO::ATTR_DEFAULT_FETCH_MODE);
    }

    public function testColumnCount(): void
    {
        $this->pdo_statement_mock->expects($this->once())->method('columnCount');
        $this->statement->columnCount();
    }

    public function testGetColumnMeta(): void
    {
        $this->pdo_statement_mock->expects($this->once())
            ->method('getColumnMeta')
            ->with(0);

        $this->statement->getColumnMeta(0);
    }

    public function testSetFetchMode(): void
    {
        $this->pdo_statement_mock->expects($this->once())
            ->method('setFetchMode')
            ->with(PDO::FETCH_ASSOC);

        $this->statement->setFetchMode(PDO::FETCH_ASSOC);
    }

    public function testNextRowset(): void
    {
        $this->pdo_statement_mock->expects($this->once())->method('nextRowset');
        $this->statement->nextRowset();
    }

    public function testCloseCursor(): void
    {
        $this->pdo_statement_mock->expects($this->once())->method('closeCursor');
        $this->statement->closeCursor();
    }

    public function testDebugDumpParams(): void
    {
        $this->pdo_statement_mock->expects($this->once())->method('debugDumpParams');
        $this->statement->debugDumpParams();
    }


    // -----------------------------------------------------------------------
    // DatabaseStatement execute / error-recovery tests
    // -----------------------------------------------------------------------

    public function testExecuteSuccess(): void
    {
        $params = ['key' => 'value'];
        $this->pdo_statement_mock->expects($this->exactly(1))
            ->method('execute')
            ->with($params)
            ->will($this->returnValue(true));

        $this->assertTrue($this->statement->execute($params));
    }

    public function testExecuteReconnect(): void
    {
        $statement = $this->getMockBuilder(DatabaseStatement::class)
            ->onlyMethods(['recoverError'])
            ->setConstructorArgs([$this->db, $this->pdo_statement_mock, $this->query])
            ->getMock();

        $statement->expects($this->once())
            ->method('recoverError')
            ->will($this->returnValue(true));

        $this->pdo_statement_mock->expects($this->exactly(2))
            ->method('execute')
            ->will($this->returnCallback([$this, 'executeCallback']));

        $this->assertTrue($statement->execute());
    }

    public function testExecuteFailure(): void
    {
        $this->expectException(\Exception::class);

        $statement = $this->getMockBuilder(DatabaseStatement::class)
            ->onlyMethods(['recoverError'])
            ->setConstructorArgs([$this->db, $this->pdo_statement_mock, $this->query])
            ->getMock();

        $statement->expects($this->once())
            ->method('recoverError')
            ->will($this->returnValue(false));

        $this->pdo_statement_mock->expects($this->exactly(1))
            ->method('execute')
            ->will($this->returnCallback([$this, 'executeCallback']));

        $statement->execute();
    }

    /** Helper callback for execute() tests. */
    public function executeCallback(): bool
    {
        if (!isset($this->counters['execute'])) {
            $this->counters['execute'] = false;
        } else {
            $this->counters['execute'] = true;
        }
        return $this->counters['execute'];
    }

    public function testRecoverErrorStatement(): void
    {
        $statement = $this->getMockBuilder(DatabaseStatement::class)
            ->onlyMethods(['errorInfo'])
            ->setConstructorArgs([$this->database_mock, $this->pdo_statement_mock, $this->query])
            ->getMock();

        $statement->expects($this->once())
            ->method('errorInfo')
            ->will($this->returnValue([true, true, 'Database server has gone away']));

        // prepare() is typed DatabaseStatement|false, so we need a real DatabaseStatement
        $statement_mock = new DatabaseStatement($this->db, $this->pdo_statement_mock, $this->query);

        $this->database_mock->expects($this->once())
            ->method('reconnect')
            ->will($this->returnValue($this->database_mock));

        $this->database_mock->expects($this->once())
            ->method('prepare')
            ->with($this->query)
            ->will($this->returnValue($statement_mock));

        $this->assertTrue($statement->recoverError());

        $this->assertSame(
            $this->database_mock,
            (new \ReflectionProperty(DatabaseStatement::class, 'database'))->getValue($statement));
        $this->assertSame(
            $this->pdo_statement_mock,
            (new \ReflectionProperty(DatabaseStatement::class, 'pdo_statement'))->getValue($statement));
    }

    public function testRecoverErrorDatabase(): void
    {
        $statement = $this->getMockBuilder(DatabaseStatement::class)
            ->onlyMethods(['errorInfo'])
            ->setConstructorArgs([$this->database_mock, $this->pdo_statement_mock, $this->query])
            ->getMock();

        $statement->expects($this->once())
            ->method('errorInfo')
            ->will($this->returnValue([false, false, '']));

        $this->database_mock->expects($this->once())
            ->method('errorInfo')
            ->will($this->returnValue([true, true, 'Database server has gone away']));

        $statement_mock = new DatabaseStatement($this->db, $this->pdo_statement_mock, $this->query);

        $this->database_mock->expects($this->once())
            ->method('reconnect')
            ->will($this->returnValue($this->database_mock));

        $this->database_mock->expects($this->once())
            ->method('prepare')
            ->with($this->query)
            ->will($this->returnValue($statement_mock));

        $this->assertTrue($statement->recoverError());

        $this->assertSame(
            $this->database_mock,
            (new \ReflectionProperty(DatabaseStatement::class, 'database'))->getValue($statement));
        $this->assertSame(
            $this->pdo_statement_mock,
            (new \ReflectionProperty(DatabaseStatement::class, 'pdo_statement'))->getValue($statement));
    }

    public function testRecoverErrorFailure(): void
    {
        $statement = $this->getMockBuilder(DatabaseStatement::class)
            ->onlyMethods(['errorInfo'])
            ->setConstructorArgs([$this->database_mock, $this->pdo_statement_mock, $this->query])
            ->getMock();

        $statement->expects($this->once())
            ->method('errorInfo')
            ->will($this->returnValue([false, false, '']));

        $this->database_mock->expects($this->once())
            ->method('errorInfo')
            ->will($this->returnValue([true, true, 'Derp!']));

        $this->database_mock->expects($this->never())->method('reconnect');
        $this->database_mock->expects($this->never())->method('prepare');

        $this->assertFalse($statement->recoverError());
    }

    public function testFormatErrors(): void
    {
        $errors = ['key_one' => 'value_one', 'key_two' => 'value_two'];
        $this->assertEquals(print_r($errors, true), $this->statement->formatErrors($errors));
    }

    public function testFetchErrors(): void
    {
        $stmt_error_info = ['HY000', 2006, 'MySQL server has gone away'];
        $stmt_error_code = 'HY000';
        $db_error_info   = ['42000', 1234, 'some database error'];
        $db_error_code   = '42000';
        $input_params    = [':id' => 42];

        $statement = $this->getMockBuilder(DatabaseStatement::class)
            ->onlyMethods(['errorInfo', 'errorCode'])
            ->setConstructorArgs([$this->database_mock, $this->pdo_statement_mock, $this->query])
            ->getMock();

        $statement->expects($this->exactly(2))
            ->method('errorInfo')
            ->will($this->returnValue($stmt_error_info));
        $statement->expects($this->exactly(2))
            ->method('errorCode')
            ->will($this->returnValue($stmt_error_code));

        // PDO::errorInfo() returns array; the typed mock enforces this
        $this->database_mock->expects($this->exactly(2))
            ->method('errorInfo')
            ->will($this->returnValue($db_error_info));
        $this->database_mock->expects($this->exactly(2))
            ->method('errorCode')
            ->will($this->returnValue($db_error_code));

        // debugDumpParams outputs to stdout (ob_start captures it); the mock returns a value
        // but does not output anything, so Statement Parameters will be an empty string.
        $this->pdo_statement_mock->expects($this->once())
            ->method('debugDumpParams');

        $result = $statement->fetchErrors($input_params);

        $this->assertEquals($this->query,    $result['Query']);
        $this->assertEquals($stmt_error_code, $result['Statement Code']);
        $this->assertEquals($stmt_error_info, $result['Statement Info']);
        $this->assertEquals($db_error_code,   $result['Database Code']);
        $this->assertEquals($db_error_info,   $result['Database Info']);
        $this->assertEquals($input_params,    $result['Input Parameters']);
        $this->assertEquals([],               $result['Bound Parameters']);
        $this->assertEquals('',               $result['Statement Parameters']);
    }

    /**
     * Functional test: confirms the Database wrapper reconnects after a wait_timeout.
     * Requires SYSTEM_VARIABLES_ADMIN on the test DB user; skipped otherwise.
     *
     * @depends testPrepare
     */
    public function testTimeout(): void
    {
        $db = new Database(PDO_DSN, PDO_USERNAME, PDO_PASSWORD,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_SILENT]);

        if ($db->exec('SET GLOBAL connect_timeout=1') === false ||
            $db->exec('SET GLOBAL wait_timeout=1') === false) {
            $this->markTestSkipped('Test DB user lacks SYSTEM_VARIABLES_ADMIN; skipping timeout test.');
        }

        sleep(2);

        $statement = $db->prepare('SELECT * FROM editions');
        $result    = $statement->execute();

        $this->assertTrue($result, 'Database reconnects on timeout.');
    }
}
