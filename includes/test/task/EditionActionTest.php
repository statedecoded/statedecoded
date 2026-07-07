<?php

/**
 * Tests for the CLI edition action, particularly promoting an edition
 * to current (`statedecoded edition current <slug>`).
 *
 * The permalink rebuild, cache clearing, and .htaccess write are stubbed
 * out, so these tests exercise the real database promotion logic without
 * side effects outside of the editions table. All changes to that table
 * are reverted in tearDown().
 *
 * PHP version 8
 *
 * @license   http://www.gnu.org/licenses/gpl.html GPL 3
 */

require_once __DIR__ . '/../../task/class.EditionAction.inc.php';

/*
 * An EditionAction whose parser controller can be replaced with a test
 * double. Fails loudly if the parser is used when none was provided.
 */
class TestableEditionAction extends EditionAction
{
	public $parser;

	public function getParserController()
	{
		if (!isset($this->parser))
		{
			throw new RuntimeException('getParserController() should not have been called.');
		}
		return $this->parser;
	}
}

class EditionActionTest extends PHPUnit\Framework\TestCase
{
	private PDO $pdo;
	private $original_current_id;
	private $temp_edition_id;

	private const TEMP_SLUG = 'edition-action-test';

	protected function setUp(): void
	{
		$this->pdo = new PDO(PDO_DSN, PDO_USERNAME, PDO_PASSWORD,
			[PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
			 PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ]);

		$row = $this->pdo->query('SELECT id FROM editions WHERE current = 1 LIMIT 1')->fetch();
		if ($row === false) {
			$this->markTestSkipped('No current edition — run the import before testing editions.');
		}
		$this->original_current_id = $row->id;
	}

	protected function tearDown(): void
	{
		/*
		 * Restore the original current edition and remove the scratch edition.
		 */
		if (isset($this->original_current_id))
		{
			$this->pdo->exec('UPDATE editions SET current = 0');
			$stmt = $this->pdo->prepare('UPDATE editions SET current = 1 WHERE id = ?');
			$stmt->execute([$this->original_current_id]);
		}

		$stmt = $this->pdo->prepare('DELETE FROM editions WHERE slug = ?');
		$stmt->execute([self::TEMP_SLUG]);
	}

	private function createTempEdition(): int
	{
		$stmt = $this->pdo->prepare(
			"INSERT INTO editions
			 SET name = 'Edition Action Test', slug = ?, current = 0, order_by = 99,
			     date_created = NOW(), date_modified = NOW()");
		$stmt->execute([self::TEMP_SLUG]);
		$this->temp_edition_id = (int) $this->pdo->lastInsertId();
		return $this->temp_edition_id;
	}

	/*
	 * A real ParserController with its side-effecting steps stubbed out:
	 * no permalink rebuild, no cache clearing, no .htaccess write.
	 */
	private function stubbedParser()
	{
		global $db;
		$db = new Database(PDO_DSN, PDO_USERNAME, PDO_PASSWORD);

		return $this->getMockBuilder(ParserController::class)
			->setConstructorArgs([['logger' => new Logger(['level' => 10]), 'db' => $db]])
			->onlyMethods(['build_permalinks', 'clear_cache', 'export_edition_id'])
			->getMock();
	}

	public function testShowCurrentEdition(): void
	{
		$expected = $this->pdo->query('SELECT name FROM editions WHERE current = 1 LIMIT 1')
			->fetch()->name;

		$action = new EditionAction();
		$this->assertSame($expected, $action->execute([]));
		$this->assertSame($expected, $action->execute(['current']),
			'"edition current" without a slug must show the current edition.');
	}

	public function testSetCurrentWithUnknownSlugFails(): void
	{
		$action = new TestableEditionAction();
		$output = $action->execute(['current', 'no-such-edition']);

		$this->assertSame(1, $action->result);
		$this->assertStringContainsString('Unable to find edition', $output);
	}

	public function testSetCurrentIsANoOpForTheCurrentEdition(): void
	{
		$slug = $this->pdo->query('SELECT slug FROM editions WHERE current = 1 LIMIT 1')
			->fetch()->slug;

		/*
		 * No parser is injected, so any attempt to promote would throw.
		 */
		$action = new TestableEditionAction();
		$output = $action->execute(['current', $slug]);

		$this->assertSame(0, $action->result);
		$this->assertStringContainsString('already current', $output);
	}

	public function testSetCurrentPromotesEditionAndRebuildsPermalinks(): void
	{
		$temp_id = $this->createTempEdition();

		$parser = $this->stubbedParser();
		$parser->expects($this->once())->method('build_permalinks');

		$action = new TestableEditionAction();
		$action->parser = $parser;
		$output = $action->execute(['current', self::TEMP_SLUG]);

		$this->assertSame(0, $action->result);
		$this->assertStringContainsString('is now current', $output);

		$current = $this->pdo->query('SELECT id FROM editions WHERE current = 1')->fetchAll();
		$this->assertCount(1, $current, 'Exactly one edition must be current.');
		$this->assertEquals($temp_id, $current[0]->id,
			'The promoted edition must be the current one.');
	}

	public function testGetHelpDocumentsCurrent(): void
	{
		$this->assertStringContainsString('edition current', EditionAction::getHelp());
	}
}
