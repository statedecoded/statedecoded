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
 * Exposes the lookup and merge helpers, which are protected on the controller.
 */
class TestableAPISuggestController extends APISuggestController
{
	public function editionId()
	{
		return $this->current_edition_id();
	}

	public function suggestionsFor($column, $prefix, $edition_id)
	{
		return $this->suggestions($column, $prefix, $edition_id);
	}

	public function mergeSuggestions($sections, $catch_lines)
	{
		return $this->merge_suggestions($sections, $catch_lines);
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


	// -------------------------------------------------------------------------
	// How the handful of suggestion slots is divided
	//
	// Section numbers and catch lines used to be fetched as one UNION with a
	// single LIMIT across both, so a prefix matching several section numbers
	// spent every slot and no catch line was ever suggested. They are now
	// queried separately and interleaved.
	// -------------------------------------------------------------------------

	public function testCatchLinesAreNotCrowdedOutBySectionNumbers(): void
	{
		$sections = ['A-1', 'A-2', 'A-3', 'A-4', 'A-5', 'A-6'];
		$catch_lines = ['Apples', 'Apricots'];

		$controller = new TestableAPISuggestController();
		$merged = $controller->mergeSuggestions($sections, $catch_lines);

		$this->assertCount(APISuggestController::SUGGESTION_LIMIT, $merged);

		$this->assertNotEmpty(array_intersect($merged, $catch_lines),
			'Catch lines must appear even when section numbers could fill every slot.');
		$this->assertNotEmpty(array_intersect($merged, $sections),
			'Section numbers must appear too.');
	}

	/*
	 * Where only one kind matches, it takes every slot: dividing the list must
	 * not mean returning fewer suggestions than there are matches.
	 */
	public function testOneKindOfMatchFillsTheList(): void
	{
		$controller = new TestableAPISuggestController();

		$sections = ['A-1', 'A-2', 'A-3', 'A-4', 'A-5'];

		$this->assertCount(APISuggestController::SUGGESTION_LIMIT,
			$controller->mergeSuggestions($sections, []),
			'Section numbers alone must fill the list.');

		$this->assertCount(APISuggestController::SUGGESTION_LIMIT,
			$controller->mergeSuggestions([], $sections),
			'Catch lines alone must fill the list.');
	}

	public function testFewerMatchesThanSlotsReturnsThemAll(): void
	{
		$controller = new TestableAPISuggestController();
		$merged = $controller->mergeSuggestions(['A-1'], ['Apples']);

		$this->assertSame(['A-1', 'Apples'], $merged);
	}

	/*
	 * A section number and a catch line can be the same string. The UNION used
	 * to collapse those; the merge has to do it now.
	 */
	public function testDuplicatesAreCollapsed(): void
	{
		$controller = new TestableAPISuggestController();
		$merged = $controller->mergeSuggestions(['Repealed'], ['Repealed']);

		$this->assertSame(['Repealed'], $merged,
			'A term appearing as both a section and a catch line must be listed once.');
	}

	public function testMergeOfNothingIsEmpty(): void
	{
		$controller = new TestableAPISuggestController();

		$this->assertSame([], $controller->mergeSuggestions([], []));
	}


	// -------------------------------------------------------------------------
	// Ordering
	// -------------------------------------------------------------------------

	/*
	 * For a prefix search the shortest match is the most specific one, so
	 * searching for a section number must suggest that section first rather
	 * than an arbitrary five of the sections beginning with it.
	 */
	public function testClosestMatchIsSuggestedFirst(): void
	{
		$edition_id = (int) $this->pdo->query(
			'SELECT edition_id FROM laws LIMIT 1')->fetchColumn();

		$section = $this->pdo->query(
			'SELECT section FROM laws WHERE section IN
			 (SELECT section FROM laws GROUP BY section HAVING COUNT(*) = 1)
			 ORDER BY LENGTH(section) LIMIT 1')->fetchColumn();

		if ($section === false)
		{
			$this->markTestSkipped('No laws to order.');
		}

		$controller = new TestableAPISuggestController();
		$suggestions = $controller->suggestionsFor('section', $section . '%', $edition_id);

		$this->assertNotEmpty($suggestions);
		$this->assertSame($section, $suggestions[0],
			'An exact match must be suggested ahead of longer sections that extend it.');
	}

	/*
	 * Asserting only that the five returned rows are in order is too weak a
	 * test: an unordered query truncated by LIMIT can satisfy it by chance.
	 * What matters is that the five are the shortest five of every candidate.
	 */
	public function testSuggestionsAreTheShortestMatches(): void
	{
		$edition_id = (int) $this->pdo->query(
			'SELECT edition_id FROM laws LIMIT 1')->fetchColumn();

		$statement = $this->pdo->prepare(
			'SELECT DISTINCT section FROM laws WHERE section LIKE ? AND edition_id = ?');
		$statement->execute(['1%', $edition_id]);
		$candidates = $statement->fetchAll(PDO::FETCH_COLUMN);

		if (count($candidates) <= APISuggestController::SUGGESTION_LIMIT)
		{
			$this->markTestSkipped('Too few matching sections for the limit to bite.');
		}

		usort($candidates,
			function ($a, $b)
			{
				return [strlen($a), $a] <=> [strlen($b), $b];
			});

		$expected = array_slice($candidates, 0, APISuggestController::SUGGESTION_LIMIT);

		$controller = new TestableAPISuggestController();

		$this->assertSame($expected,
			$controller->suggestionsFor('section', '1%', $edition_id),
			'The suggestions must be the shortest matches, in order, not an arbitrary five.');
	}

	public function testSuggestionsAreCapped(): void
	{
		$edition_id = (int) $this->pdo->query(
			'SELECT edition_id FROM laws LIMIT 1')->fetchColumn();

		$controller = new TestableAPISuggestController();

		$this->assertLessThanOrEqual(APISuggestController::SUGGESTION_LIMIT,
			count($controller->suggestionsFor('section', '%', $edition_id)),
			'A query matching everything must still return no more than the limit.');
	}

	/*
	 * The column name is interpolated into the SQL, since a column cannot be a
	 * bound parameter. It must therefore never be anything but a known literal.
	 */
	public function testUnknownColumnIsRejected(): void
	{
		$controller = new TestableAPISuggestController();

		$this->expectException(InvalidArgumentException::class);
		$controller->suggestionsFor('id; DROP TABLE laws', 'x%', 1);
	}
}
