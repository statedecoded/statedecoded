<?php

/**
 * Export tests — verify that the bulk download files produced by the import
 * pipeline exist on disk and contain correct content.
 *
 * All tests skip gracefully when the export directory for the current edition
 * does not exist (i.e. the import has not been run with export plugins enabled).
 *
 * PHP version 8
 *
 * @license   http://www.gnu.org/licenses/gpl.html GPL 3
 */

class ExportTest extends PHPUnit\Framework\TestCase
{
	private string $downloadsDir;

	protected function setUp(): void
	{
		$db = new PDO(PDO_DSN, PDO_USERNAME, PDO_PASSWORD,
			[PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
			 PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ]);

		$lawCount = (int) $db->query('SELECT COUNT(*) FROM laws')->fetchColumn();
		if ($lawCount === 0) {
			$this->markTestSkipped('laws table is empty — run the import before testing exports.');
		}

		$edition = $db->query(
			'SELECT slug FROM editions WHERE current = 1 LIMIT 1'
		)->fetch();

		if (!$edition) {
			$this->markTestSkipped('No current edition found.');
		}

		$dir = WEB_ROOT . 'downloads/' . $edition->slug;
		if (!is_dir($dir)) {
			$this->markTestSkipped(
				"Export directory '$dir' does not exist — " .
				're-run the import with export plugins enabled.');
		}

		$this->downloadsDir = $dir;
	}


	// -------------------------------------------------------------------------
	// File existence — known sections
	// -------------------------------------------------------------------------

	public function testHtmlExportExistsForSection1Dash1(): void
	{
		$this->assertFileExists(
			$this->downloadsDir . '/code-html/1-1.html',
			'HTML export for § 1-1 must exist after import.'
		);
	}

	public function testJsonExportExistsForSection1Dash1(): void
	{
		$this->assertFileExists(
			$this->downloadsDir . '/code-json/1-1.json',
			'JSON export for § 1-1 must exist after import.'
		);
	}

	public function testTextExportExistsForSection1Dash1(): void
	{
		$this->assertFileExists(
			$this->downloadsDir . '/code-text/1-1.txt',
			'Plain-text export for § 1-1 must exist after import.'
		);
	}

	public function testHtmlExportExistsForSection182Dash9(): void
	{
		$this->assertFileExists(
			$this->downloadsDir . '/code-html/18.2-9.html',
			'HTML export for § 18.2-9 must exist after import.'
		);
	}

	public function testHtmlExportExistsForSection3Dot2Dash100(): void
	{
		$this->assertFileExists(
			$this->downloadsDir . '/code-html/3.2-100.html',
			'HTML export for § 3.2-100 must exist after import.'
		);
	}

	public function testJsonExportExistsForSection3Dot2Dash100(): void
	{
		$this->assertFileExists(
			$this->downloadsDir . '/code-json/3.2-100.json',
			'JSON export for § 3.2-100 must exist after import.'
		);
	}

	public function testTextExportExistsForSection3Dot2Dash100(): void
	{
		$this->assertFileExists(
			$this->downloadsDir . '/code-text/3.2-100.txt',
			'Plain-text export for § 3.2-100 must exist after import.'
		);
	}


	// -------------------------------------------------------------------------
	// File counts
	// -------------------------------------------------------------------------

	public function testHtmlExportCountMatchesLawCount(): void
	{
		$db = new PDO(PDO_DSN, PDO_USERNAME, PDO_PASSWORD,
			[PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
		$lawCount = (int) $db->query('SELECT COUNT(*) FROM laws')->fetchColumn();

		$files = glob($this->downloadsDir . '/code-html/*.html');
		$this->assertGreaterThanOrEqual(
			$lawCount,
			count($files),
			'There must be at least as many HTML export files as there are laws.'
		);
	}

	public function testJsonExportCountMatchesLawCount(): void
	{
		$db = new PDO(PDO_DSN, PDO_USERNAME, PDO_PASSWORD,
			[PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
		$lawCount = (int) $db->query('SELECT COUNT(*) FROM laws')->fetchColumn();

		$files = glob($this->downloadsDir . '/code-json/*.json');
		$this->assertGreaterThanOrEqual(
			$lawCount,
			count($files),
			'There must be at least as many JSON export files as there are laws.'
		);
	}


	// -------------------------------------------------------------------------
	// HTML content checks
	// -------------------------------------------------------------------------

	public function testHtmlExportHasCharsetMetaTag(): void
	{
		$html = file_get_contents($this->downloadsDir . '/code-html/3.2-100.html');
		$this->assertStringContainsString(
			'<meta charset="UTF-8">',
			$html,
			'HTML export must contain <meta charset="UTF-8"> to prevent encoding corruption.'
		);
	}

	public function testHtmlExportHasDoctype(): void
	{
		$html = file_get_contents($this->downloadsDir . '/code-html/1-1.html');
		$this->assertStringStartsWith('<!doctype html>', $html,
			'HTML export must begin with a <!doctype html> declaration.');
	}

	public function testHtmlExportContainsCatchLine(): void
	{
		$html = file_get_contents($this->downloadsDir . '/code-html/1-1.html');
		$this->assertStringContainsString(
			'Contents and designation of Code',
			$html,
			'HTML export for § 1-1 must contain the correct catch line.'
		);
	}

	public function testHtmlExportContainsNoPhpErrors(): void
	{
		$html = file_get_contents($this->downloadsDir . '/code-html/18.2-9.html');
		$this->assertStringNotContainsStringIgnoringCase('Fatal error', $html,
			'HTML export must not contain PHP fatal errors.');
		$this->assertStringNotContainsStringIgnoringCase('Warning:', $html,
			'HTML export must not contain PHP warnings.');
	}


	// -------------------------------------------------------------------------
	// JSON content checks
	// -------------------------------------------------------------------------

	public function testJsonExportIsValidJson(): void
	{
		$raw  = file_get_contents($this->downloadsDir . '/code-json/1-1.json');
		$data = json_decode($raw, true);
		$this->assertNotNull($data, 'JSON export for § 1-1 must be valid JSON.');
	}

	public function testJsonExportContainsCatchLine(): void
	{
		$raw  = file_get_contents($this->downloadsDir . '/code-json/1-1.json');
		$data = json_decode($raw, true);
		$this->assertSame(
			'Contents and designation of Code',
			$data['catch_line'] ?? null,
			'JSON export for § 1-1 must have the correct catch_line field.'
		);
	}

	public function testJsonExportContainsSectionNumber(): void
	{
		$raw  = file_get_contents($this->downloadsDir . '/code-json/18.2-9.json');
		$data = json_decode($raw, true);
		$this->assertSame('18.2-9', $data['section_number'] ?? null,
			'JSON export for § 18.2-9 must include the section_number field.');
	}


	// -------------------------------------------------------------------------
	// Plain-text content checks
	// -------------------------------------------------------------------------

	public function testTextExportIsNotEmpty(): void
	{
		$text = file_get_contents($this->downloadsDir . '/code-text/1-1.txt');
		$this->assertNotEmpty(trim($text),
			'Plain-text export for § 1-1 must not be empty.');
	}

	public function testTextExportContainsCatchLine(): void
	{
		$text = file_get_contents($this->downloadsDir . '/code-text/1-1.txt');
		$this->assertStringContainsStringIgnoringCase(
			'Contents and designation of Code',
			$text,
			'Plain-text export for § 1-1 must contain the catch line.'
		);
	}


	// -------------------------------------------------------------------------
	// Zip archives
	// -------------------------------------------------------------------------

	public function testHtmlZipExists(): void
	{
		$this->assertFileExists($this->downloadsDir . '/code-html.zip',
			'code-html.zip must be created by the export step.');
	}

	public function testJsonZipExists(): void
	{
		$this->assertFileExists($this->downloadsDir . '/code-json.zip',
			'code-json.zip must be created by the export step.');
	}

	public function testHtmlZipIsNotEmpty(): void
	{
		$size = filesize($this->downloadsDir . '/code-html.zip');
		$this->assertGreaterThan(1000, $size,
			'code-html.zip must be larger than 1 KB.');
	}

	public function testJsonZipIsNotEmpty(): void
	{
		$size = filesize($this->downloadsDir . '/code-json.zip');
		$this->assertGreaterThan(1000, $size,
			'code-json.zip must be larger than 1 KB.');
	}
}
