<?php

/**
 * Tests for the edition selector on the search form.
 *
 * The form used to default to whichever edition the EDITION_ID constant named.
 * That constant is written into .htaccess at import time and names the edition
 * imported then, so once a newer edition was published the form went on
 * offering an old one -- in production, the oldest -- as the default. The
 * default now comes from the edition the database flags as current.
 *
 * PHP version 8
 *
 * @license   http://www.gnu.org/licenses/gpl.html GPL 3
 */

class SearchFormTest extends PHPUnit\Framework\TestCase
{
	private Database $db;
	private array $edition_ids = [];

	protected function setUp(): void
	{
		global $db;

		$this->db = new Database(PDO_DSN, PDO_USERNAME, PDO_PASSWORD);
		$db = $this->db;

		/*
		 * Which edition the import left flagged as current is restored in
		 * tearDown, so these tests can move the flag around freely.
		 */
		$this->db->exec('UPDATE editions SET current = 0');
	}

	protected function tearDown(): void
	{
		foreach ($this->edition_ids as $id)
		{
			$statement = $this->db->prepare('DELETE FROM editions WHERE id = :id');
			$statement->execute([':id' => $id]);
		}

		/*
		 * Put the flag back on the edition the import created, which the rest
		 * of the suite expects to find current.
		 */
		$this->db->exec('UPDATE editions SET current = 0');
		$this->db->exec('UPDATE editions SET current = 1 ORDER BY order_by LIMIT 1');
	}

	/*
	 * Two editions newer than the one the import created, the newest of which
	 * is flagged current -- the shape that exposed the bug.
	 */
	private function createEditions(): array
	{
		$ids = [];

		foreach ([['Older Test Edition', 9998], ['Newer Test Edition', 9999]] as $edition)
		{
			list($name, $order_by) = $edition;

			$statement = $this->db->prepare(
				'INSERT INTO editions (name, slug, date_created, current, order_by)
					VALUES (:name, :slug, NOW(), 0, :order_by)');
			$statement->execute(
				[
					':name' => $name,
					':slug' => 'search-form-test-' . uniqid(),
					':order_by' => $order_by
				]);

			$id = (int) $this->db->lastInsertId();
			$ids[] = $id;
			$this->edition_ids[] = $id;
		}

		$statement = $this->db->prepare(
			'UPDATE editions SET current = 1 WHERE id = :id');
		$statement->execute([':id' => $ids[1]]);

		return $ids;
	}

	private function selectedEditionId(string $html): ?string
	{
		if (preg_match('/<option value="([^"]*)" selected="selected"/', $html, $matches))
		{
			return $matches[1];
		}

		return null;
	}

	public function testDefaultsToTheCurrentEdition(): void
	{
		$newer = $this->createEditions()[1];

		$search = new Search(['db' => $this->db]);

		$this->assertSame((string) $newer,
			$this->selectedEditionId($search->build_edition(null)),
			'With no edition specified, the form must default to the current edition.');
	}

	/*
	 * The bug itself. The oldest edition is the one the import created, and the
	 * one EDITION_ID names; production defaulted to it. Whichever edition is
	 * flagged current must win instead.
	 */
	public function testDefaultIsNotTheOldestEdition(): void
	{
		$newer = $this->createEditions()[1];

		$oldest = $this->db->query(
			'SELECT id FROM editions ORDER BY order_by LIMIT 1')->fetchColumn();

		$search = new Search(['db' => $this->db]);
		$selected = $this->selectedEditionId($search->build_edition(null));

		$this->assertNotSame((string) $oldest, $selected,
			'The form must not default to the oldest edition.');
		$this->assertSame((string) $newer, $selected,
			'The form must default to the edition flagged current.');
	}

	/*
	 * An edition the visitor picked has to survive, or the selector could never
	 * be used to look at an older edition.
	 */
	public function testExplicitEditionIsRespected(): void
	{
		$older = $this->createEditions()[0];

		$search = new Search(['db' => $this->db]);

		$this->assertSame((string) $older,
			$this->selectedEditionId($search->build_edition($older)),
			'An explicitly requested edition must stay selected.');
	}

	/*
	 * "Search All Editions" submits an empty edition_id. That is a choice, not
	 * an absence of one, so it must not be replaced with the current edition.
	 */
	public function testSearchAllEditionsIsNotOverridden(): void
	{
		$this->createEditions();

		$search = new Search(['db' => $this->db]);

		$this->assertNull($this->selectedEditionId($search->build_edition('')),
			'"Search All Editions" must leave every named edition unselected.');
	}

	/*
	 * With no edition flagged current there is nothing to default to. The
	 * EDITION_ID constant is used if it is defined; where it is not -- as in
	 * the test configuration -- the form must still render, with nothing
	 * selected, rather than raising an error over the undefined constant.
	 */
	public function testHandlesNoCurrentEdition(): void
	{
		$this->createEditions();
		$this->db->exec('UPDATE editions SET current = 0');

		$search = new Search(['db' => $this->db]);
		$html = $search->build_edition(null);

		$this->assertStringContainsString('<select name="edition_id"', $html,
			'The selector must still render with no edition flagged current.');

		$expected = defined('EDITION_ID') ? (string) EDITION_ID : null;

		$this->assertSame($expected, $this->selectedEditionId($html),
			defined('EDITION_ID')
				? 'With no current edition, the form falls back to EDITION_ID.'
				: 'With neither a current edition nor EDITION_ID, nothing is selected.');
	}
}
