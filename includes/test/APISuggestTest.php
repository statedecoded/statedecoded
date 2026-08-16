<?php

/**
 * Tests for the autocomplete API's choice of edition.
 *
 * Suggestions are scoped to one edition, and that scoping is exclusive: laws in
 * any other edition are not suggested at all. The edition used to come from the
 * EDITION_ID constant, which is written into .htaccess at import time and names
 * whichever edition was imported then. Once that value went stale -- a failed
 * write, or the current edition changed by other means -- autocomplete went on
 * suggesting from an old edition, and laws added since stopped appearing.
 *
 * The edition now comes from the database, with the constant kept as a fallback.
 *
 * PHP version 8
 *
 * @license   http://www.gnu.org/licenses/gpl.html GPL 3
 */

/*
 * Exposes the edition lookup, which is protected on the controller.
 */
class TestableAPISuggestController extends APISuggestController
{
	public function editionId()
	{
		return $this->current_edition_id();
	}
}

class APISuggestTest extends PHPUnit\Framework\TestCase
{
	private PDO $pdo;
	private $original_current_id;
	private $edition_id;

	private const TEMP_SLUG = 'api-suggest-test';

	protected function setUp(): void
	{
		global $db;

		$this->pdo = new PDO(PDO_DSN, PDO_USERNAME, PDO_PASSWORD,
			[PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
			 PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ]);

		$db = new Database(PDO_DSN, PDO_USERNAME, PDO_PASSWORD);

		$row = $this->pdo->query('SELECT id FROM editions WHERE current = 1 LIMIT 1')->fetch();
		if ($row === false)
		{
			$this->markTestSkipped('No current edition — run the import before testing.');
		}
		$this->original_current_id = $row->id;
	}

	protected function tearDown(): void
	{
		if (isset($this->edition_id))
		{
			$statement = $this->pdo->prepare('DELETE FROM editions WHERE id = ?');
			$statement->execute([$this->edition_id]);
		}

		if (isset($this->original_current_id))
		{
			$this->pdo->exec('UPDATE editions SET current = 0');
			$statement = $this->pdo->prepare('UPDATE editions SET current = 1 WHERE id = ?');
			$statement->execute([$this->original_current_id]);
		}
	}

	/*
	 * A newer edition than the one the import created, flagged current.
	 */
	private function createCurrentEdition(): int
	{
		$statement = $this->pdo->prepare(
			"INSERT INTO editions
			 SET name = 'API Suggest Test', slug = ?, current = 0, order_by = 9999,
			     date_created = NOW(), date_modified = NOW()");
		$statement->execute([self::TEMP_SLUG]);
		$this->edition_id = (int) $this->pdo->lastInsertId();

		$this->pdo->exec('UPDATE editions SET current = 0');
		$statement = $this->pdo->prepare('UPDATE editions SET current = 1 WHERE id = ?');
		$statement->execute([$this->edition_id]);

		return $this->edition_id;
	}

	public function testUsesTheCurrentEditionFromTheDatabase(): void
	{
		$expected = $this->createCurrentEdition();

		$controller = new TestableAPISuggestController();

		$this->assertEquals($expected, $controller->editionId(),
			'Suggestions must be scoped to the edition the database flags as current.');
	}

	/*
	 * The bug itself. The edition the import created is the one EDITION_ID
	 * names; production autocompleted from it long after a newer edition had
	 * been promoted. Whichever edition is flagged current must win instead.
	 */
	public function testDoesNotUseThePreviouslyCurrentEdition(): void
	{
		$previous = $this->original_current_id;
		$expected = $this->createCurrentEdition();

		$controller = new TestableAPISuggestController();

		$this->assertNotEquals($previous, $controller->editionId(),
			'Autocomplete must not go on suggesting from the previously current edition.');
		$this->assertEquals($expected, $controller->editionId());

		if (defined('EDITION_ID'))
		{
			$this->assertNotEquals(EDITION_ID, $controller->editionId(),
				'A stale EDITION_ID must not decide which edition is suggested from.');
		}
	}

	/*
	 * With no edition flagged current there is nothing to read, so the constant
	 * is used. Where it is not defined either -- as in the test configuration --
	 * false asks the caller to suggest from every edition rather than none.
	 */
	public function testFallsBackWithNoCurrentEdition(): void
	{
		$this->createCurrentEdition();
		$this->pdo->exec('UPDATE editions SET current = 0');

		$controller = new TestableAPISuggestController();
		$expected = defined('EDITION_ID') ? EDITION_ID : false;

		$this->assertEquals($expected, $controller->editionId(),
			defined('EDITION_ID')
				? 'With no current edition, the constant is used.'
				: 'With neither a current edition nor the constant, scoping is dropped.');
	}
}
