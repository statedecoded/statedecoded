<?php

/**
 * Tests for ParserController::export_edition_id(), which records the current
 * edition's ID in .htaccess as the EDITION_ID environment variable.
 *
 * Several queries take their default edition from that constant, so an import
 * that fails to update it leaves parts of the site serving an older edition of
 * the code while otherwise looking like it succeeded. The write used to fail
 * silently, logging at a level nobody reads; it now reports the failure to its
 * caller.
 *
 * These tests write to the real .htaccess, since WEB_ROOT is a constant. The
 * file is backed up in setUp() and restored in tearDown(), including its
 * permissions.
 *
 * They also run in a separate process. export_edition_id() defines the
 * EDITION_ID constant, which cannot be undefined again, and other tests in the
 * suite assert that it is undefined -- deliberately, since the parser must not
 * depend on it. Isolating these keeps that constant out of the shared process.
 *
 * PHP version 8
 *
 * @license   http://www.gnu.org/licenses/gpl.html GPL 3
 */

#[PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
class ExportEditionIdTest extends PHPUnit\Framework\TestCase
{
	private ParserController $parser;
	private string $htaccess_path;
	private string $htaccess_backup;
	private $htaccess_mode;

	protected function setUp(): void
	{
		global $db;

		$database = new Database(PDO_DSN, PDO_USERNAME, PDO_PASSWORD);
		$db = $database;

		$this->parser = new ParserController(
			['db' => &$database, 'logger' => new Logger(['level' => 10])]);

		$this->htaccess_path = WEB_ROOT . '/.htaccess';

		if (!file_exists($this->htaccess_path))
		{
			$this->markTestSkipped('No .htaccess to test against.');
		}

		$this->htaccess_backup = file_get_contents($this->htaccess_path);
		$this->htaccess_mode = fileperms($this->htaccess_path) & 0777;
	}

	protected function tearDown(): void
	{
		if (isset($this->htaccess_backup))
		{
			@chmod($this->htaccess_path, 0644);
			file_put_contents($this->htaccess_path, $this->htaccess_backup);
			@chmod($this->htaccess_path, $this->htaccess_mode);
		}
	}

	private function editionIdInFile(): ?string
	{
		if (preg_match('/SetEnv EDITION_ID (\d+)/', file_get_contents($this->htaccess_path),
			$matches))
		{
			return $matches[1];
		}

		return null;
	}

	public function testUpdatesAnExistingEditionId(): void
	{
		$result = $this->parser->export_edition_id(4321);

		$this->assertTrue($result, 'A successful write must return true.');
		$this->assertSame('4321', $this->editionIdInFile(),
			'The edition ID in .htaccess must be replaced with the new one.');
	}

	/*
	 * The point of the whole mechanism: a later import has to be able to move
	 * the pointer off the edition an earlier one set.
	 */
	public function testOverwritesRatherThanAppending(): void
	{
		$this->parser->export_edition_id(101);
		$this->parser->export_edition_id(202);

		$contents = file_get_contents($this->htaccess_path);

		$this->assertSame(1, substr_count($contents, 'SetEnv EDITION_ID'),
			'Repeated writes must not accumulate EDITION_ID lines.');
		$this->assertSame('202', $this->editionIdInFile());
	}

	public function testAddsTheEditionIdWhenAbsent(): void
	{
		file_put_contents($this->htaccess_path,
			preg_replace('/SetEnv EDITION_ID (\d+)/', '', $this->htaccess_backup));

		$result = $this->parser->export_edition_id(555);

		$this->assertTrue($result);
		$this->assertSame('555', $this->editionIdInFile(),
			'An .htaccess with no EDITION_ID must have one added.');
	}

	/*
	 * The failure this was written for. Running as root defeats the permission
	 * bits, so the test skips rather than reporting a false pass.
	 */
	public function testReportsAnUnwritableFile(): void
	{
		chmod($this->htaccess_path, 0444);

		if (is_writable($this->htaccess_path))
		{
			$this->markTestSkipped('Running as a user that can write read-only files.');
		}

		$result = $this->parser->export_edition_id(9999);

		$this->assertIsString($result,
			'An unwritable .htaccess must return an explanation, not true.');
		$this->assertStringContainsString('Cannot write', $result);

		$this->assertNotSame('9999', $this->editionIdInFile(),
			'Nothing may have been written.');
	}

	/*
	 * The error has to name the file and say what the consequence is, since
	 * the symptom -- the site serving an old edition -- is otherwise hard to
	 * trace back to a line in an import log.
	 */
	public function testTheErrorExplainsTheConsequence(): void
	{
		chmod($this->htaccess_path, 0444);

		if (is_writable($this->htaccess_path))
		{
			$this->markTestSkipped('Running as a user that can write read-only files.');
		}

		$result = $this->parser->export_edition_id(9999);

		$this->assertStringContainsString('.htaccess', $result,
			'The error must name the file that could not be written.');
		$this->assertStringContainsString('EDITION_ID', $result,
			'The error must say what is now stale.');
	}
}
