<?php

/**
 * Tests for ParserController::update_laws_references(), which crosslinks the
 * references between laws.
 *
 * These run against a scratch edition built inside the test database, rather
 * than against imported data, so that the many-to-one case (two laws sharing a
 * section number) is actually exercised. The sample legal code has no duplicate
 * section numbers, so that branch would otherwise never be tested -- which is
 * how it came to be rewritten with no coverage at all.
 *
 * PHP version 8
 *
 * @license   http://www.gnu.org/licenses/gpl.html GPL 3
 */

class LawsReferencesTest extends PHPUnit\Framework\TestCase
{
	private Database $db;
	private ParserController $parser;
	private int $edition_id;
	private int $structure_id;

	protected function setUp(): void
	{
		global $db;

		$this->db = new Database(PDO_DSN, PDO_USERNAME, PDO_PASSWORD);
		$db = $this->db;

		$logger = new Logger();
		$logger->level = 10;

		$this->parser = new ParserController(['db' => &$this->db, 'logger' => $logger]);

		$this->createScratchEdition();

		$this->parser->edition_id = $this->edition_id;
	}

	protected function tearDown(): void
	{
		if (isset($this->edition_id))
		{
			/*
			 * laws_references, laws and structure all cascade from editions.
			 */
			$statement = $this->db->prepare('DELETE FROM editions WHERE id = :id');
			$statement->execute([':id' => $this->edition_id]);
		}
	}

	/*
	 * Build an edition of our own, so that these tests neither depend on nor
	 * disturb whatever the import produced.
	 */
	private function createScratchEdition(): void
	{
		$statement = $this->db->prepare(
			'INSERT INTO editions (name, slug, date_created, current, order_by)
				VALUES ("Reference Test", :slug, NOW(), 0, 9999)');
		$statement->execute([':slug' => 'reference-test-' . uniqid()]);
		$this->edition_id = (int) $this->db->lastInsertId();

		$statement = $this->db->prepare(
			'INSERT INTO structure (name, label, identifier, order_by, edition_id, date_created)
				VALUES ("Test Title", "Title", "1", "1", :edition_id, NOW())');
		$statement->execute([':edition_id' => $this->edition_id]);
		$this->structure_id = (int) $this->db->lastInsertId();
	}

	private function addLaw(string $section, string $catch_line): int
	{
		$statement = $this->db->prepare(
			'INSERT INTO laws
				(structure_id, section, catch_line, order_by, history, edition_id, date_created)
			VALUES
				(:structure_id, :section, :catch_line, "1", "", :edition_id, NOW())');
		$statement->execute(
			[
				':structure_id' => $this->structure_id,
				':section' => $section,
				':catch_line' => $catch_line,
				':edition_id' => $this->edition_id
			]
		);

		return (int) $this->db->lastInsertId();
	}

	/*
	 * Add an unresolved reference, as the parser leaves them: target_law_id 0.
	 */
	private function addReference(int $law_id, string $target_section_number): void
	{
		$statement = $this->db->prepare(
			'INSERT INTO laws_references
				(law_id, target_section_number, target_law_id, mentions, date_created, edition_id)
			VALUES
				(:law_id, :target_section_number, 0, 1, NOW(), :edition_id)');
		$statement->execute(
			[
				':law_id' => $law_id,
				':target_section_number' => $target_section_number,
				':edition_id' => $this->edition_id
			]
		);
	}

	/*
	 * The law IDs a given law's references resolve to, in ascending order.
	 */
	private function resolvedTargets(int $law_id): array
	{
		$statement = $this->db->prepare(
			'SELECT target_law_id FROM laws_references
			WHERE law_id = :law_id AND edition_id = :edition_id
			ORDER BY target_law_id');
		$statement->execute([':law_id' => $law_id, ':edition_id' => $this->edition_id]);

		return array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN));
	}


	// -----------------------------------------------------------------------
	// One reference, one matching law
	// -----------------------------------------------------------------------

	public function testResolvesReferenceToSingleMatchingLaw(): void
	{
		$citing = $this->addLaw('1-1', 'The citing law');
		$cited  = $this->addLaw('1-2', 'The cited law');
		$this->addReference($citing, '1-2');

		$this->parser->update_laws_references();

		$this->assertSame([$cited], $this->resolvedTargets($citing),
			'A reference to a unique section number must resolve to that law.');
	}

	// -----------------------------------------------------------------------
	// Many references to the same section: the redundancy that made this slow
	// -----------------------------------------------------------------------

	public function testResolvesManyReferencesToTheSameSection(): void
	{
		$cited = $this->addLaw('2-1', 'The popular law');

		$citing_ids = [];
		for ($i = 2; $i <= 6; $i++)
		{
			$citing_id = $this->addLaw('2-' . $i, 'Citing law ' . $i);
			$citing_ids[] = $citing_id;
			$this->addReference($citing_id, '2-1');
		}

		$this->parser->update_laws_references();

		foreach ($citing_ids as $citing_id)
		{
			$this->assertSame([$cited], $this->resolvedTargets($citing_id),
				'Every law citing the same section must resolve to it exactly once.');
		}
	}

	// -----------------------------------------------------------------------
	// One reference, several laws sharing that section number
	// -----------------------------------------------------------------------

	public function testDuplicateSectionNumbersProduceARowPerMatch(): void
	{
		$citing = $this->addLaw('3-1', 'The citing law');

		/*
		 * Two laws share the section number 3-2, so a single reference to it
		 * must fan out into one row per matching law.
		 */
		$first  = $this->addLaw('3-2', 'First law numbered 3-2');
		$second = $this->addLaw('3-2', 'Second law numbered 3-2');

		$this->addReference($citing, '3-2');

		$this->parser->update_laws_references();

		$targets = $this->resolvedTargets($citing);
		sort($targets);
		$expected = [$first, $second];
		sort($expected);

		$this->assertSame($expected, $targets,
			'A reference to a duplicated section number must yield one row per matching law.');
	}

	// -----------------------------------------------------------------------
	// References that match nothing
	// -----------------------------------------------------------------------

	public function testUnmatchedReferencesAreDeleted(): void
	{
		$citing = $this->addLaw('4-1', 'The citing law');
		$this->addReference($citing, '4-999');

		$this->parser->update_laws_references();

		$this->assertSame([], $this->resolvedTargets($citing),
			'A reference matching no law is spurious and must be deleted.');
	}

	public function testNoUnresolvedReferencesRemain(): void
	{
		$citing = $this->addLaw('5-1', 'The citing law');
		$this->addLaw('5-2', 'The cited law');
		$this->addReference($citing, '5-2');
		$this->addReference($citing, '5-nonexistent');

		$this->parser->update_laws_references();

		$statement = $this->db->prepare(
			'SELECT COUNT(*) FROM laws_references
			WHERE edition_id = :edition_id AND target_law_id = 0');
		$statement->execute([':edition_id' => $this->edition_id]);

		$this->assertSame(0, (int) $statement->fetchColumn(),
			'No reference may be left unresolved once crosslinking has run.');
	}

	// -----------------------------------------------------------------------
	// Editions must not bleed into one another
	// -----------------------------------------------------------------------

	public function testReferencesDoNotCrossEditions(): void
	{
		$citing = $this->addLaw('6-1', 'The citing law');
		$this->addReference($citing, '6-2');

		/*
		 * The only law with this section number belongs to a different
		 * edition, so the reference must not resolve to it.
		 */
		$other_edition_id = $this->edition_id;

		$statement = $this->db->prepare(
			'INSERT INTO editions (name, slug, date_created, current, order_by)
				VALUES ("Other Edition", :slug, NOW(), 0, 9998)');
		$statement->execute([':slug' => 'other-edition-' . uniqid()]);
		$other_edition_id = (int) $this->db->lastInsertId();

		$statement = $this->db->prepare(
			'INSERT INTO structure (name, label, identifier, order_by, edition_id, date_created)
				VALUES ("Other Title", "Title", "1", "1", :edition_id, NOW())');
		$statement->execute([':edition_id' => $other_edition_id]);
		$other_structure_id = (int) $this->db->lastInsertId();

		$statement = $this->db->prepare(
			'INSERT INTO laws
				(structure_id, section, catch_line, order_by, history, edition_id, date_created)
			VALUES
				(:structure_id, "6-2", "Law in another edition", "1", "", :edition_id, NOW())');
		$statement->execute(
			[
				':structure_id' => $other_structure_id,
				':edition_id' => $other_edition_id
			]
		);

		$this->parser->update_laws_references();

		$this->assertSame([], $this->resolvedTargets($citing),
			'A reference must not resolve to a law in a different edition.');

		$statement = $this->db->prepare('DELETE FROM editions WHERE id = :id');
		$statement->execute([':id' => $other_edition_id]);
	}
}
