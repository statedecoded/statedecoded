<?php

/**
 * Tests for loading the baseline schema file, htdocs/admin/statedecoded.sql.
 *
 * That file is written for the mysql command-line client, which understands the
 * DELIMITER directive that protects a stored routine's internal semicolons. PDO
 * does not: it passes the text straight to the server, which rejects DELIMITER
 * as a syntax error. ParserController::extract_routines() bridges the two, and
 * without it populate_db() fails partway through -- creating the tables but not
 * the fromRoman() function -- on every new installation.
 *
 * PHP version 8
 *
 * @license   http://www.gnu.org/licenses/gpl.html GPL 3
 */

class SchemaLoadTest extends PHPUnit\Framework\TestCase
{
	private ParserController $parser;

	protected function setUp(): void
	{
		global $db;

		$database = new Database(PDO_DSN, PDO_USERNAME, PDO_PASSWORD);
		$db = $database;

		$logger = new Logger();
		$logger->level = 10;

		$this->parser = new ParserController(['db' => &$database, 'logger' => $logger]);
	}

	/*
	 * The schema file must not be handed to PDO with DELIMITER still in it.
	 */
	public function testSchemaFileStillUsesDelimiter(): void
	{
		$sql = file_get_contents(WEB_ROOT . '/admin/statedecoded.sql');

		$this->assertStringContainsString('DELIMITER', $sql,
			'This test is only meaningful while the schema file uses DELIMITER.');
	}

	public function testRoutinesAreExtractedFromTheSchemaFile(): void
	{
		$sql = file_get_contents(WEB_ROOT . '/admin/statedecoded.sql');

		list($remaining, $routines) = $this->parser->extract_routines($sql);

		$this->assertStringNotContainsString('DELIMITER', $remaining,
			'No DELIMITER directive may survive into the SQL handed to PDO.');

		$this->assertCount(1, $routines,
			'The schema file defines exactly one stored routine, fromRoman().');

		$this->assertStringContainsString('CREATE FUNCTION', $routines[0],
			'The extracted routine must be the CREATE FUNCTION statement.');

		$this->assertStringContainsString('fromRoman', $routines[0],
			'The extracted routine must be fromRoman().');

		$this->assertStringNotContainsString('DELIMITER', $routines[0],
			'The extracted routine must not carry the DELIMITER directive.');
	}

	/*
	 * The table definitions must survive extraction untouched.
	 */
	public function testTableDefinitionsSurviveExtraction(): void
	{
		$sql = file_get_contents(WEB_ROOT . '/admin/statedecoded.sql');

		list($remaining, $routines) = $this->parser->extract_routines($sql);

		$this->assertStringContainsString('CREATE TABLE IF NOT EXISTS `laws`', $remaining,
			'Table definitions must be left in the main body of the SQL.');

		$this->assertStringContainsString('INSERT INTO `dictionary_general`', $remaining,
			'Seed data must be left in the main body of the SQL.');
	}

	/*
	 * A file with no routines at all must pass through unharmed.
	 */
	public function testFileWithoutRoutinesIsUnchanged(): void
	{
		$sql = "CREATE TABLE `foo` (`id` int);\nINSERT INTO `foo` VALUES (1);\n";

		list($remaining, $routines) = $this->parser->extract_routines($sql);

		$this->assertSame($sql, $remaining, 'A file with no routines must be returned as-is.');
		$this->assertSame([], $routines, 'A file with no routines must yield no routines.');
	}

	/*
	 * More than one routine, as the schema may eventually have.
	 */
	public function testMultipleRoutinesAreEachExtracted(): void
	{
		$sql = "CREATE TABLE `foo` (`id` int);\n"
			. "DELIMITER $$\n"
			. "CREATE FUNCTION `first`() RETURNS int BEGIN RETURN 1; END;\n"
			. "$$\n"
			. "DELIMITER ;\n"
			. "DELIMITER $$\n"
			. "CREATE FUNCTION `second`() RETURNS int BEGIN RETURN 2; END;\n"
			. "$$\n"
			. "DELIMITER ;\n";

		list($remaining, $routines) = $this->parser->extract_routines($sql);

		$this->assertCount(2, $routines, 'Each routine must be extracted separately.');
		$this->assertStringContainsString('`first`', $routines[0]);
		$this->assertStringContainsString('`second`', $routines[1]);
		$this->assertStringNotContainsString('DELIMITER', $remaining);
		$this->assertStringContainsString('CREATE TABLE `foo`', $remaining);
	}
}
