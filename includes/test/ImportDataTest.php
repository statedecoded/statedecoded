<?php

/**
 * Tests that verify the import pipeline produced correct data.
 *
 * All tests skip gracefully when the `laws` table is empty (import has not run).
 * In CI, the import step runs before PHPUnit; in Docker, docker-test.sh handles it.
 *
 * PHP version 8
 *
 * @license   http://www.gnu.org/licenses/gpl.html GPL 3
 */

class ImportDataTest extends PHPUnit\Framework\TestCase
{
	private PDO $db;

	protected function setUp(): void
	{
		$this->db = new PDO(PDO_DSN, PDO_USERNAME, PDO_PASSWORD,
			[PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
			 PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ]);

		$count = (int) $this->db->query('SELECT COUNT(*) FROM laws')->fetchColumn();
		if ($count === 0) {
			$this->markTestSkipped('laws table is empty — run the import before testing import data.');
		}
	}


	// -----------------------------------------------------------------------
	// 1. Import integrity
	// -----------------------------------------------------------------------

	public function testLawCount(): void
	{
		$count = (int) $this->db->query('SELECT COUNT(*) FROM laws')->fetchColumn();
		$this->assertEquals(570, $count,
			'Expected 570 laws (one per XML file in deploy/import-data/).');
	}

	public function testEditionExists(): void
	{
		$count = (int) $this->db->query('SELECT COUNT(*) FROM editions')->fetchColumn();
		$this->assertGreaterThanOrEqual(1, $count, 'At least one edition must exist after import.');
	}

	public function testStructureCount(): void
	{
		$count = (int) $this->db->query('SELECT COUNT(*) FROM structure')->fetchColumn();
		$this->assertGreaterThan(0, $count, 'structure table must be populated after import.');
	}

	public function testTextRowsExist(): void
	{
		$count = (int) $this->db->query('SELECT COUNT(*) FROM text')->fetchColumn();
		$this->assertGreaterThan(0, $count, 'text table must be populated after import.');
	}


	// -----------------------------------------------------------------------
	// 2. Specific-content tests
	// -----------------------------------------------------------------------

	public function testLaw1Dash1CatchLine(): void
	{
		$stmt = $this->db->prepare('SELECT catch_line FROM laws WHERE section = :s');
		$stmt->execute([':s' => '1-1']);
		$row = $stmt->fetch();
		$this->assertNotFalse($row, 'Law 1-1 must exist in the database.');
		$this->assertEquals('Contents and designation of Code', $row->catch_line);
	}

	public function testLaw182Dash9CatchLine(): void
	{
		$stmt = $this->db->prepare('SELECT catch_line FROM laws WHERE section = :s');
		$stmt->execute([':s' => '18.2-9']);
		$row = $stmt->fetch();
		$this->assertNotFalse($row, 'Law 18.2-9 must exist in the database.');
		$this->assertEquals('Classification of criminal offenses', $row->catch_line);
	}

	public function testLaw3Dot2Dash100CatchLine(): void
	{
		$stmt = $this->db->prepare('SELECT catch_line FROM laws WHERE section = :s');
		$stmt->execute([':s' => '3.2-100']);
		$row = $stmt->fetch();
		$this->assertNotFalse($row, 'Law 3.2-100 must exist in the database.');
		$this->assertEquals('Definitions', $row->catch_line);
	}

	public function testLaw1Dash1TextNotEmpty(): void
	{
		$stmt = $this->db->prepare(
			'SELECT t.text FROM text t
			 JOIN laws l ON l.id = t.law_id
			 WHERE l.section = :s
			 LIMIT 1');
		$stmt->execute([':s' => '1-1']);
		$row = $stmt->fetch();
		$this->assertNotFalse($row, 'Law 1-1 must have text rows.');
		$this->assertNotEmpty(trim($row->text), 'Law 1-1 text must not be empty.');
	}

	public function testLaw182Dash9HasNestedSections(): void
	{
		$stmt = $this->db->prepare(
			'SELECT COUNT(*) FROM text t
			 JOIN laws l ON l.id = t.law_id
			 WHERE l.section = :s');
		$stmt->execute([':s' => '18.2-9']);
		$count = (int) $stmt->fetchColumn();
		$this->assertGreaterThan(1, $count,
			'Law 18.2-9 has nested sections and must produce multiple text rows.');
	}


	// -----------------------------------------------------------------------
	// 3. Structural hierarchy tests
	// -----------------------------------------------------------------------

	public function testTitle182Exists(): void
	{
		$stmt = $this->db->prepare(
			"SELECT id FROM structure WHERE label = 'title' AND identifier = :id");
		$stmt->execute([':id' => '18.2']);
		$row = $stmt->fetch();
		$this->assertNotFalse($row, 'Title 18.2 must exist in the structure table.');
	}

	public function testChapter1UnderTitle182(): void
	{
		$stmt = $this->db->prepare(
			"SELECT s2.id
			 FROM structure s1
			 JOIN structure s2 ON s2.parent_id = s1.id
			 WHERE s1.label = 'title' AND s1.identifier = :title
			   AND s2.label = 'chapter' AND s2.identifier = :chapter");
		$stmt->execute([':title' => '18.2', ':chapter' => '1']);
		$row = $stmt->fetch();
		$this->assertNotFalse($row,
			'Chapter 1 must be a direct child of Title 18.2 in the structure table.');
	}

	public function testArticle3UnderChapter1(): void
	{
		$stmt = $this->db->prepare(
			"SELECT s3.id
			 FROM structure s1
			 JOIN structure s2 ON s2.parent_id = s1.id
			 JOIN structure s3 ON s3.parent_id = s2.id
			 WHERE s1.label = 'title'   AND s1.identifier = :title
			   AND s2.label = 'chapter' AND s2.identifier = :chapter
			   AND s3.label = 'article' AND s3.identifier = :article");
		$stmt->execute([':title' => '18.2', ':chapter' => '1', ':article' => '3']);
		$row = $stmt->fetch();
		$this->assertNotFalse($row,
			'Article 3 must be a child of Chapter 1 which is a child of Title 18.2.');
	}

	public function testSection182Dash9UnderArticle3(): void
	{
		$stmt = $this->db->prepare(
			"SELECT l.id
			 FROM structure s1
			 JOIN structure s2 ON s2.parent_id = s1.id
			 JOIN structure s3 ON s3.parent_id = s2.id
			 JOIN laws l ON l.structure_id = s3.id
			 WHERE s1.label = 'title'   AND s1.identifier = :title
			   AND s2.label = 'chapter' AND s2.identifier = :chapter
			   AND s3.label = 'article' AND s3.identifier = :article
			   AND l.section = :section");
		$stmt->execute([
			':title'   => '18.2',
			':chapter' => '1',
			':article' => '3',
			':section' => '18.2-9',
		]);
		$row = $stmt->fetch();
		$this->assertNotFalse($row,
			'§ 18.2-9 must sit within Title 18.2 → Chapter 1 → Article 3.');
	}


	// -----------------------------------------------------------------------
	// 4. Permalink / URL tests
	// -----------------------------------------------------------------------

	public function testAllLawsHavePermalinks(): void
	{
		$lawCount = (int) $this->db->query('SELECT COUNT(*) FROM laws')->fetchColumn();
		$stmt = $this->db->query(
			"SELECT COUNT(DISTINCT relational_id) FROM permalinks WHERE object_type = 'law'");
		$permaCount = (int) $stmt->fetchColumn();
		$this->assertEquals($lawCount, $permaCount,
			'Every law must have at least one permalink row.');
	}

	public function testAllStructuresHavePermalinks(): void
	{
		$structCount = (int) $this->db->query('SELECT COUNT(*) FROM structure')->fetchColumn();
		$stmt = $this->db->query(
			"SELECT COUNT(DISTINCT relational_id) FROM permalinks WHERE object_type = 'structure'");
		$permaCount = (int) $stmt->fetchColumn();
		$this->assertEquals($structCount, $permaCount,
			'Every structural unit must have at least one permalink row.');
	}

	public function testSection1Dash1Permalink(): void
	{
		$stmt = $this->db->prepare(
			"SELECT p.url
			 FROM permalinks p
			 JOIN laws l ON l.id = p.relational_id AND p.object_type = 'law'
			 WHERE l.section = :s
			 LIMIT 1");
		$stmt->execute([':s' => '1-1']);
		$row = $stmt->fetch();
		$this->assertNotFalse($row, 'A permalink must exist for § 1-1.');
		$this->assertStringContainsString('1-1', $row->url,
			"The permalink URL for § 1-1 must contain '1-1'.");
	}

	public function testSection182Dash9Permalink(): void
	{
		$stmt = $this->db->prepare(
			"SELECT p.url
			 FROM permalinks p
			 JOIN laws l ON l.id = p.relational_id AND p.object_type = 'law'
			 WHERE l.section = :s
			 LIMIT 1");
		$stmt->execute([':s' => '18.2-9']);
		$row = $stmt->fetch();
		$this->assertNotFalse($row, 'A permalink must exist for § 18.2-9.');
		$this->assertStringContainsString('18.2-9', $row->url,
			"The permalink URL for § 18.2-9 must contain '18.2-9'.");
	}
}
