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


	// -----------------------------------------------------------------------
	// Full-text search behaviour
	//
	// Searches run through MySQL's full-text indexes. These tests cover the
	// behaviours that depends on -- phrases, required terms, relevance
	// ordering -- and the cases that fall back to REGEXP because the full-text
	// indexer cannot serve them.
	// -----------------------------------------------------------------------

	public function testMultiWordSearchRequiresEveryWord(): void
	{
		$both = $this->engine->search(
			['q' => 'water quality', 'page' => 1, 'per_page' => 200]);
		$one = $this->engine->search(
			['q' => 'water', 'page' => 1, 'per_page' => 200]);

		$this->assertGreaterThan(0, $both->get_count(),
			'A multi-word search must still find the laws containing both words.');

		$this->assertLessThanOrEqual($one->get_count(), $both->get_count(),
			'Requiring both words cannot match more laws than requiring one of them.');
	}

	public function testPhraseSearchIsNarrowerThanItsWords(): void
	{
		$phrase = $this->engine->search(
			['q' => '"water quality"', 'page' => 1, 'per_page' => 200]);
		$words = $this->engine->search(
			['q' => 'water quality', 'page' => 1, 'per_page' => 200]);

		$this->assertLessThanOrEqual($words->get_count(), $phrase->get_count(),
			'An exact phrase cannot appear in more laws than its words appear in together.');
	}

	public function testResultsAreOrderedByRelevance(): void
	{
		$results = $this->engine->search(['q' => 'water', 'page' => 1, 'per_page' => 20]);

		$previous = null;
		foreach ($results->get_results() as $row)
		{
			$this->assertObjectHasProperty('relevance', $row,
				'Every result must carry a relevance score to be ordered by.');

			if ($previous !== null)
			{
				$this->assertLessThanOrEqual((float) $previous, (float) $row->relevance,
					'Results must be ordered from most to least relevant.');
			}

			$previous = $row->relevance;
		}
	}

	/*
	 * Stopwords ("the", "of") are absent from the full-text index, so a search
	 * for one has to fall back to REGEXP rather than silently finding nothing.
	 */
	public function testStopwordSearchFallsBackRatherThanFindingNothing(): void
	{
		$results = $this->engine->search(['q' => 'the', 'page' => 1, 'per_page' => 10]);

		$this->assertGreaterThan(0, $results->get_count(),
			'A search for a stopword must fall back to REGEXP, not return nothing.');
	}

	/*
	 * Likewise for words shorter than innodb_ft_min_token_size, which the
	 * indexer never stored.
	 */
	public function testShortTermSearchFallsBack(): void
	{
		$engine = $this->engine;
		$minimum = $engine->min_token_size();

		$this->assertGreaterThan(0, $minimum,
			'The engine must know the indexer minimum token size.');

		$this->assertSame('', $engine->boolean_query(str_repeat('a', $minimum - 1)),
			'A term below the minimum token size must produce no full-text query, '
			. 'so that the caller falls back to REGEXP.');
	}

	public function testSectionNumberSearchIsUnaffectedByTokenizing(): void
	{
		$statement = $this->db->query(
			'SELECT section FROM laws WHERE section LIKE "%.%-%" LIMIT 1');
		$section = $statement->fetchColumn();

		if ($section === false)
		{
			$this->markTestSkipped('No punctuated section numbers in the sample data.');
		}

		$results = $this->engine->search(['q' => $section, 'page' => 1, 'per_page' => 50]);

		$this->assertGreaterThan(0, $results->get_count(),
			'Searching for a section number must find the law that bears it.');

		$found = false;
		foreach ($results->get_results() as $row)
		{
			if ($row->object_type === 'law' && $row->id == $this->lawIdForSection($section))
			{
				$found = true;
				break;
			}
		}

		$this->assertTrue($found,
			'The law whose section number was searched for must be among the results.');
	}

	/*
	 * Boolean-mode operators in a user's search must not change its meaning or
	 * cause a syntax error.
	 */
	public function testOperatorCharactersAreNeutralized(): void
	{
		foreach (['water +++', 'water ~()', '"unclosed quote', 'water @distance'] as $term)
		{
			$results = $this->engine->search(['q' => $term, 'page' => 1, 'per_page' => 5]);

			$this->assertGreaterThanOrEqual(0, $results->get_count(),
				'A search containing "' . $term . '" must execute without error.');
		}
	}

	private function lawIdForSection(string $section)
	{
		$statement = $this->db->prepare('SELECT id FROM laws WHERE section = :s LIMIT 1');
		$statement->execute([':s' => $section]);

		return $statement->fetchColumn();
	}
}
