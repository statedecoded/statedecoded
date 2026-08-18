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

	// -----------------------------------------------------------------------
	// Search operators
	//
	// AND, OR and NOT are written as words, since the punctuation forms are
	// stripped from user input. These were documented long before they worked:
	// every token used to be marked required, so "OR" was silently discarded as
	// a stopword and left both terms required, and "NOT" was required as a
	// literal word -- the opposite of an exclusion.
	// -----------------------------------------------------------------------

	public function testEveryWordIsRequiredByDefault(): void
	{
		$this->assertSame('+radar +detectors',
			$this->engine->boolean_query('radar detectors'));
	}

	/*
	 * AND is what happens anyway, so it is accepted and changes nothing rather
	 * than being required as a literal word.
	 */
	public function testAndIsAcceptedAndRedundant(): void
	{
		$this->assertSame($this->engine->boolean_query('assault battery'),
			$this->engine->boolean_query('assault AND battery'),
			'AND must mean what a space already means.');
	}

	public function testNotExcludesItsTerm(): void
	{
		$this->assertSame('+assault -battery',
			$this->engine->boolean_query('assault NOT battery'));
	}

	/*
	 * The disjunction is expressed as a required group, +(a b), which is how
	 * boolean mode says "either of these". A bare "a b" would make both merely
	 * optional, which matches every row the index knows about.
	 */
	public function testOrGroupsItsTerms(): void
	{
		$this->assertSame('+(assault battery)',
			$this->engine->boolean_query('assault OR battery'));
	}

	public function testOrIsCaseInsensitive(): void
	{
		foreach (['assault or battery', 'assault Or battery', 'assault OR battery'] as $query)
		{
			$this->assertSame('+(assault battery)', $this->engine->boolean_query($query),
				'"' . $query . '" must be read as an OR.');
		}
	}

	public function testOrChainsIntoOneGroup(): void
	{
		$this->assertSame('+(assault battery robbery)',
			$this->engine->boolean_query('assault OR battery OR robbery'));
	}

	/*
	 * OR binds to the term before it as well as the one after, so the earlier
	 * term stops being required.
	 */
	public function testOrCombinesWithRequiredTerms(): void
	{
		$this->assertSame('+vehicle +(assault battery)',
			$this->engine->boolean_query('vehicle assault OR battery'));
	}

	public function testNotChains(): void
	{
		$this->assertSame('+vehicle -assault -battery',
			$this->engine->boolean_query('vehicle NOT assault NOT battery'));
	}

	/*
	 * An operator is a bare word. A quoted phrase containing one is a search for
	 * those words, and a hyphenated word that merely starts with one is a term.
	 */
	public function testOperatorsInsideQuotesAreNotOperators(): void
	{
		$this->assertSame('+"not guilty"', $this->engine->boolean_query('"not guilty"'));
	}

	public function testHyphenatedWordIsNotAnOperator(): void
	{
		$this->assertSame('+"not-for-profit"',
			$this->engine->boolean_query('not-for-profit'));
	}

	public function testAPhraseCanBeExcluded(): void
	{
		$this->assertSame('+assault -"assault and battery"',
			$this->engine->boolean_query('assault NOT "assault and battery"'));
	}

	/*
	 * A search of nothing but exclusions would match every indexed row, which is
	 * not what was meant. Returning an empty query sends the caller to the
	 * REGEXP fallback instead.
	 */
	public function testAnExclusionAloneIsNotASearch(): void
	{
		$this->assertSame('', $this->engine->boolean_query('NOT battery'));
	}

	/*
	 * An operator with nothing to apply to is ignored rather than breaking the
	 * query or being searched for as a word.
	 */
	public function testDanglingOperatorsAreIgnored(): void
	{
		$this->assertSame('+assault', $this->engine->boolean_query('assault OR'));
		$this->assertSame('+battery', $this->engine->boolean_query('OR battery'));
		$this->assertSame('+assault', $this->engine->boolean_query('assault NOT'));
	}

	/*
	 * A term dropped for being unindexable takes any operator waiting on it with
	 * it, rather than leaving the operator to attach to the next term.
	 */
	public function testAnOperatorOnADroppedTermIsDiscarded(): void
	{
		$this->assertSame('+assault', $this->engine->boolean_query('assault NOT xy'),
			'Excluding a word too short to be indexed must not exclude anything else.');
	}


	// -----------------------------------------------------------------------
	// Operators, executed
	//
	// The translations above have to mean what they say once MySQL runs them.
	// -----------------------------------------------------------------------

	public function testOrFindsMoreThanEitherWordAlone(): void
	{
		$either = $this->engine->search(
			['q' => 'assault OR battery', 'page' => 1, 'per_page' => 200]);
		$first = $this->engine->search(['q' => 'assault', 'page' => 1, 'per_page' => 200]);
		$second = $this->engine->search(['q' => 'battery', 'page' => 1, 'per_page' => 200]);

		$this->assertGreaterThanOrEqual($first->get_count(), $either->get_count(),
			'An OR cannot match fewer laws than one of its terms.');
		$this->assertGreaterThanOrEqual($second->get_count(), $either->get_count(),
			'An OR cannot match fewer laws than one of its terms.');
	}

	public function testAndFindsFewerThanEitherWordAlone(): void
	{
		$both = $this->engine->search(
			['q' => 'assault AND battery', 'page' => 1, 'per_page' => 200]);
		$first = $this->engine->search(['q' => 'assault', 'page' => 1, 'per_page' => 200]);

		$this->assertLessThanOrEqual($first->get_count(), $both->get_count(),
			'Requiring both words cannot match more laws than requiring one.');
	}

	/*
	 * The bug that prompted this: "assault NOT battery" used to return laws
	 * containing "battery", because NOT was required as a literal word.
	 */
	public function testNotActuallyExcludes(): void
	{
		$results = $this->engine->search(
			['q' => 'assault NOT battery', 'page' => 1, 'per_page' => 200]);

		$this->assertGreaterThan(0, $results->get_count(),
			'The sample data must contain laws mentioning assault but not battery.');

		$statement = $this->db->prepare(
			'SELECT COUNT(*) FROM laws
			 WHERE MATCH(catch_line, text) AGAINST (:match IN BOOLEAN MODE)
			 AND (catch_line LIKE "%battery%" OR text LIKE "%battery%")');
		$statement->execute([':match' => $this->engine->boolean_query('assault NOT battery')]);

		$this->assertSame(0, (int) $statement->fetchColumn(),
			'No law containing the excluded word may be returned.');
	}

		// -----------------------------------------------------------------------
	// Missing full-text indexes
	//
	// The indexes that MATCH searches against are created by a migration, not
	// by the baseline schema, so an installation whose code was updated without
	// running `statedecoded migrate` does not have them. MySQL answers a MATCH
	// with no matching index with error 1191 rather than with rows, which took
	// down search in production. Search must degrade to REGEXP instead.
	// -----------------------------------------------------------------------

	public function testFulltextIndexesAreDetectedWhenPresent(): void
	{
		$this->assertTrue($this->engine->has_fulltext_indexes(),
			'The test database is migrated, so the full-text indexes must be detected.');
	}

	/*
	 * MySQL matches MATCH(a, b) only to an index on exactly (a, b), so an index
	 * covering some of the columns is as useless as no index at all and must be
	 * reported as absent.
	 */
	public function testPartialFulltextIndexCountsAsMissing(): void
	{
		$this->withoutFulltextIndexes(function (): void
		{
			$this->db->exec('ALTER TABLE laws ADD FULLTEXT INDEX ft_laws_search (text)');

			$engine = new SqlSearchEngine(['db' => $this->db]);

			$this->assertFalse($engine->has_fulltext_indexes(),
				'An index on (text) cannot serve MATCH(catch_line, text), '
				. 'so it must count as missing.');
		});
	}

	public function testSearchFallsBackWhenFulltextIndexesAreMissing(): void
	{
		$expected = $this->engine->search(['q' => 'water', 'page' => 1, 'per_page' => 50]);

		$this->withoutFulltextIndexes(function () use ($expected): void
		{
			$engine = new SqlSearchEngine(['db' => $this->db]);

			$this->assertFalse($engine->has_fulltext_indexes(),
				'The indexes were just dropped, so they must be reported absent.');

			$results = $engine->search(['q' => 'water', 'page' => 1, 'per_page' => 50]);

			$this->assertGreaterThan(0, $results->get_count(),
				'A search must still find laws with no full-text index to search.');

			$this->assertEquals($expected->get_count(), $results->get_count(),
				'The REGEXP fallback must find the same laws the full-text search does.');
		});
	}

	/*
	 * The count query and the results query are built separately, so both have
	 * to take the fallback path; if only one did, the page would report a
	 * number of results it could not then display.
	 */
	public function testFallbackReturnsRowsNotJustACount(): void
	{
		$this->withoutFulltextIndexes(function (): void
		{
			$engine = new SqlSearchEngine(['db' => $this->db]);
			$results = $engine->search(['q' => 'water', 'page' => 1, 'per_page' => 10]);

			$this->assertNotEmpty($results->get_results(),
				'The fallback must return rows, not merely a count.');
		});
	}

	/*
	 * Run a test with the full-text indexes dropped, restoring them afterwards
	 * even if it fails -- otherwise one failure leaves the test database broken
	 * for every test that follows.
	 */
	private function withoutFulltextIndexes(callable $test): void
	{
		$this->db->exec('ALTER TABLE laws DROP INDEX ft_laws_search');
		$this->db->exec('ALTER TABLE structure DROP INDEX ft_structure_search');

		try
		{
			$test();
		}
		finally
		{
			/*
			 * Dropping an index that a test already re-created would error, so
			 * each is dropped again first if it is present.
			 */
			foreach ([['laws', 'ft_laws_search'], ['structure', 'ft_structure_search']] as $index)
			{
				list($table, $name) = $index;

				$statement = $this->db->prepare(
					'SELECT COUNT(*) FROM information_schema.STATISTICS
					 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?');
				$statement->execute([$table, $name]);

				if ((int) $statement->fetchColumn() > 0)
				{
					$this->db->exec('ALTER TABLE `' . $table . '` DROP INDEX `' . $name . '`');
				}
			}

			$this->db->exec(
				'ALTER TABLE laws ADD FULLTEXT INDEX ft_laws_search (catch_line, text)');
			$this->db->exec(
				'ALTER TABLE structure ADD FULLTEXT INDEX ft_structure_search (name)');
		}
	}

	private function lawIdForSection(string $section)
	{
		$statement = $this->db->prepare('SELECT id FROM laws WHERE section = :s LIMIT 1');
		$statement->execute([':s' => $section]);

		return $statement->fetchColumn();
	}
}
