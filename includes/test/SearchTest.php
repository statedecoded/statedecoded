<?php

/**
 * Smoke tests for search: run real SqlSearchEngine queries against the test
 * database.
 *
 * These exist because search can break at the PHP–MySQL boundary in ways no
 * mock will catch (e.g. MySQL 8 rejecting the [[:<:]] regex word-boundary
 * syntax with error 3685, which broke every search while the test suite
 * stayed green). Every test here executes the engine's actual SQL.
 *
 * All tests skip gracefully when the `laws` table is empty (import has not
 * run). In CI, the import step runs before PHPUnit; in Docker, docker-test.sh
 * handles it.
 *
 * PHP version 8
 *
 * @license   http://www.gnu.org/licenses/gpl.html GPL 3
 */

class SearchTest extends PHPUnit\Framework\TestCase
{
	private Database $db;
	private SqlSearchEngine $engine;

	protected function setUp(): void
	{
		$this->db = new Database(PDO_DSN, PDO_USERNAME, PDO_PASSWORD);

		$count = (int) $this->db->query('SELECT COUNT(*) FROM laws')->fetchColumn();
		if ($count === 0) {
			$this->markTestSkipped('laws table is empty — run the import before testing search.');
		}

		$this->engine = new SqlSearchEngine(['db' => $this->db]);
	}

	public function testSingleWordSearch(): void
	{
		$results = $this->engine->search(['q' => 'water', 'page' => 1, 'per_page' => 10]);

		$this->assertGreaterThan(0, $results->get_count(),
			'A search for "water" must match laws in the sample data.');

		$rows = $results->get_results();
		$this->assertNotEmpty($rows, 'get_results() must return rows when get_count() > 0.');

		$first = $rows[0];
		$this->assertObjectHasProperty('id', $first);
		$this->assertObjectHasProperty('name', $first);
		$this->assertObjectHasProperty('object_type', $first);
		$this->assertObjectHasProperty('edition_id', $first);
	}

	public function testMultiWordSearch(): void
	{
		/*
		 * A term with a space exercises the tokenized-keyword query path,
		 * which builds additional REGEXP clauses per word.
		 */
		$results = $this->engine->search(['q' => 'water quality', 'page' => 1, 'per_page' => 10]);

		$this->assertGreaterThan(0, $results->get_count(),
			'A multi-word search must match laws containing any of its words.');
	}

	public function testExactSectionNumberSearch(): void
	{
		$results = $this->engine->search(['q' => '1-1', 'page' => 1, 'per_page' => 10]);

		$this->assertGreaterThan(0, $results->get_count(),
			'Searching for a section number must find that law.');

		$found_law = false;
		foreach ($results->get_results() as $row)
		{
			if ($row->object_type === 'law')
			{
				$found_law = true;
				break;
			}
		}
		$this->assertTrue($found_law, 'A section-number search must return at least one law.');
	}

	public function testEditionFilter(): void
	{
		$edition_id = (int) $this->db->query('SELECT MIN(edition_id) FROM laws')->fetchColumn();

		$results = $this->engine->search(
			['q' => 'water', 'edition_id' => $edition_id, 'page' => 1, 'per_page' => 10]);

		$this->assertGreaterThan(0, $results->get_count(),
			'An edition-filtered search must still find results.');

		foreach ($results->get_results() as $row)
		{
			$this->assertEquals($edition_id, $row->edition_id,
				'Every result must belong to the requested edition.');
		}
	}

	public function testPagination(): void
	{
		$all = $this->engine->search(['q' => 'water', 'page' => 1, 'per_page' => 100]);
		$page = $this->engine->search(['q' => 'water', 'page' => 1, 'per_page' => 5]);

		$this->assertLessThanOrEqual(5, count($page->get_results()),
			'per_page must cap the number of rows returned.');
		$this->assertEquals($all->get_count(), $page->get_count(),
			'The total count must be unaffected by pagination.');
	}
}
