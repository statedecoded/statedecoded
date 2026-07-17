<?php

/*
 * CheckupAction - verifies that an edition was imported correctly
 *
 * Inspects the artifacts that ImportAction leaves behind — database
 * records, exports on disk, the sitemap, the search index — and
 * reports whether each looks complete. Where an exact expected count
 * is knowable (e.g. every law must have a preferred permalink), a
 * shortfall is a failure; where the right number depends on the legal
 * code itself (e.g. dictionary terms), an implausible count is only a
 * warning.
 */

require_once 'class.CliAction.inc.php';

class CheckupAction extends CliAction
{
	static public $name = 'checkup';
	static public $summary = 'Verifies that an edition was imported correctly.';

	public $edition;
	public $failures = [];
	public $warnings = [];

	public function __construct($args = [])
	{
		parent::__construct($args);

		global $db;
		$db = new Database( PDO_DSN, PDO_USERNAME, PDO_PASSWORD );
		$this->db = $db;
	}

	public function execute($args = [])
	{

		if ($this->findEdition() === false)
		{
			$this->result = 1;
			fwrite(STDERR, "checkup: could not find the edition to check\n");
			return '';
		}

		$law_count = $this->checkLaws();
		$structure_count = $this->checkStructures();

		/*
		 * With no laws at all, the per-law checks would only repeat
		 * the same failure five more times.
		 */
		if ($law_count > 0)
		{
			$this->checkOrphanedLaws();
			$this->checkText($law_count);
			$this->checkReferences();
			$this->checkPermalinks('law', $law_count);
			$this->checkPermalinks('structure', $structure_count);
			$this->checkDictionary();
			$this->checkExports($law_count, $structure_count);
			$this->checkSitemap($law_count);
		}

		$this->checkApiKey();

		return $this->summarize();

	}

	/*
	 * Resolve the edition to check: --edition=slug, or else the
	 * current edition.
	 */
	public function findEdition()
	{

		$edition_obj = new Edition(['db' => $this->db]);

		if (isset($this->options['edition']))
		{
			$this->edition = $edition_obj->find_by_slug($this->options['edition']);
			if ($this->edition === false)
			{
				$this->report('FAIL', 'Edition',
					'no edition with the slug “' . $this->options['edition'] . '” exists');
				return false;
			}
		}
		else
		{
			$this->edition = $edition_obj->current();
			if ($this->edition === false)
			{
				$this->report('FAIL', 'Edition', 'no current edition exists');
				return false;
			}
		}

		$detail = '“' . $this->edition->name . '” (id ' . $this->edition->id . ')';

		/*
		 * finish_import() stamps last_import when the import runs to
		 * completion, so a NULL here means the import died partway.
		 */
		if (empty($this->edition->last_import))
		{
			$this->report('WARN', 'Edition',
				$detail . ' exists, but has never had an import finish');
		}
		else
		{
			$this->report('OK', 'Edition',
				$detail . ', last import finished ' . $this->edition->last_import);
		}

		return true;

	}

	public function checkLaws()
	{

		$count = $this->countQuery(
			'SELECT COUNT(*) FROM laws WHERE edition_id = :edition_id');

		if ($count == 0)
		{
			$this->report('FAIL', 'Laws', 'no laws in the database for this edition');
		}
		else
		{
			$this->report('OK', 'Laws', number_format($count) . ' laws');
		}

		return $count;

	}

	public function checkStructures()
	{

		$count = $this->countQuery(
			'SELECT COUNT(*) FROM structure WHERE edition_id = :edition_id');

		if ($count == 0)
		{
			$this->report('FAIL', 'Structures',
				'no structural units (titles, chapters, etc.) for this edition');
		}
		else
		{
			$this->report('OK', 'Structures',
				number_format($count) . ' structural units');
		}

		return $count;

	}

	/*
	 * Every law must belong to a structural unit that exists.
	 */
	public function checkOrphanedLaws()
	{

		$orphans = $this->countQuery(
			'SELECT COUNT(*) FROM laws
			LEFT JOIN structure
				ON structure.id = laws.structure_id
			WHERE laws.edition_id = :edition_id
			AND structure.id IS NULL');

		if ($orphans > 0)
		{
			$this->report('FAIL', 'Law structure',
				number_format($orphans) . ' laws belong to a structural unit that does not exist');
		}
		else
		{
			$this->report('OK', 'Law structure',
				'every law belongs to an existing structural unit');
		}

	}

	/*
	 * Every law must have at least one text record.
	 */
	public function checkText($law_count)
	{

		$textless = $this->countQuery(
			'SELECT COUNT(*) FROM laws
			LEFT JOIN text
				ON text.law_id = laws.id
			WHERE laws.edition_id = :edition_id
			AND text.id IS NULL');

		if ($textless > 0)
		{
			$this->report('FAIL', 'Law text',
				number_format($textless) . ' of ' . number_format($law_count)
				. ' laws have no text');
		}
		else
		{
			$text_count = $this->countQuery(
				'SELECT COUNT(*) FROM text WHERE edition_id = :edition_id');
			$this->report('OK', 'Law text',
				'all ' . number_format($law_count) . ' laws have text ('
				. number_format($text_count) . ' records)');
		}

	}

	/*
	 * Cross-references between laws. The import deletes unresolved
	 * references when it finishes, so any reference whose target no
	 * longer exists indicates an incomplete import.
	 */
	public function checkReferences()
	{

		$count = $this->countQuery(
			'SELECT COUNT(*) FROM laws_references WHERE edition_id = :edition_id');

		if ($count == 0)
		{
			$this->report('WARN', 'Cross-references',
				'no cross-references were indexed — unusual, unless laws in this '
				. 'code genuinely never cite each other');
			return;
		}

		$dangling = $this->countQuery(
			'SELECT COUNT(*) FROM laws_references
			LEFT JOIN laws
				ON laws.id = laws_references.target_law_id
			WHERE laws_references.edition_id = :edition_id
			AND laws.id IS NULL');

		if ($dangling > 0)
		{
			$this->report('WARN', 'Cross-references',
				number_format($count) . ' indexed, but ' . number_format($dangling)
				. ' point to laws that do not exist');
		}
		else
		{
			$this->report('OK', 'Cross-references',
				number_format($count) . ' indexed, all pointing to existing laws');
		}

	}

	/*
	 * Every law and every structural unit must have exactly one
	 * preferred permalink — that is how their pages resolve.
	 */
	public function checkPermalinks($object_type, $object_count)
	{

		$table = ($object_type == 'law') ? 'laws' : 'structure';
		$label = 'Permalinks (' . $table . ')';

		$sql = 'SELECT COUNT(*) FROM ' . $table . '
			LEFT JOIN permalinks
				ON permalinks.relational_id = ' . $table . '.id
				AND permalinks.object_type = "' . $object_type . '"
				AND permalinks.preferred = 1
				AND permalinks.edition_id = ' . $table . '.edition_id
			WHERE ' . $table . '.edition_id = :edition_id
			AND permalinks.id IS NULL';

		$missing = $this->countQuery($sql);

		if ($missing > 0)
		{
			$this->report('FAIL', $label,
				number_format($missing) . ' of ' . number_format($object_count)
				. ' have no preferred permalink — their pages cannot resolve');
		}
		else
		{
			$this->report('OK', $label,
				'all ' . number_format($object_count) . ' have a preferred permalink');
		}

	}

	public function checkDictionary()
	{

		$count = $this->countQuery(
			'SELECT COUNT(*) FROM dictionary WHERE edition_id = :edition_id');

		if ($count == 0)
		{
			$this->report('WARN', 'Dictionary',
				'no defined terms — expected only if this code contains no definitions');
		}
		else
		{
			$this->report('OK', 'Dictionary', number_format($count) . ' defined terms');
		}

	}

	/*
	 * Bulk exports on disk. Which formats exist depends on which
	 * plugins are registered, so rather than demand specific formats,
	 * verify that whatever was exported is plausibly complete: every
	 * format includes at least one file per law, and every zip is
	 * non-empty.
	 */
	public function checkExports($law_count, $structure_count)
	{

		$downloads_dir = WEB_ROOT . '/downloads/' . $this->edition->slug . '/';

		if (!is_dir($downloads_dir))
		{
			$this->report('FAIL', 'Exports',
				'no export directory at ' . $downloads_dir . ' — the export step did not run');
			return;
		}

		$formats = glob($downloads_dir . '*', GLOB_ONLYDIR);

		if (count($formats) == 0)
		{
			$this->report('FAIL', 'Exports',
				$downloads_dir . ' exists but contains no format directories');
			return;
		}

		foreach ($formats as $format_dir)
		{

			$format = basename($format_dir);
			$file_count = count(glob($format_dir . '/*'));

			/*
			 * Some formats (HTML, text) also export one file per
			 * structural unit, so the expected count is a range.
			 */
			if ($file_count >= $law_count)
			{
				$this->report('OK', 'Exports',
					$format . ': ' . number_format($file_count) . ' files');
			}
			else
			{
				$this->report('FAIL', 'Exports',
					$format . ': only ' . number_format($file_count) . ' files for '
					. number_format($law_count) . ' laws');
			}

		}

		foreach (glob($downloads_dir . '*.zip') as $zip)
		{
			if (filesize($zip) == 0)
			{
				$this->report('FAIL', 'Exports', basename($zip) . ' is empty');
			}
		}

		/*
		 * The "current" symlink is how /downloads/current/ URLs resolve.
		 */
		if ($this->edition->current == 1)
		{
			$current_link = WEB_ROOT . '/downloads/current';
			if (realpath($current_link) !== realpath($downloads_dir))
			{
				$this->report('WARN', 'Exports',
					'downloads/current does not point to this edition’s exports');
			}
		}

	}

	/*
	 * The sitemap is only generated for the current edition, and only
	 * lists the first 50,000 laws.
	 */
	public function checkSitemap($law_count)
	{

		if ($this->edition->current != 1)
		{
			$this->report('SKIP', 'Sitemap',
				'only generated for the current edition');
			return;
		}

		$sitemap_file = WEB_ROOT . '/sitemap.xml';

		if (!file_exists($sitemap_file))
		{
			$this->report('FAIL', 'Sitemap', $sitemap_file . ' does not exist');
			return;
		}

		$url_count = substr_count(file_get_contents($sitemap_file), '<url>');
		$expected = min($law_count, 50000);

		if ($url_count == $expected)
		{
			$this->report('OK', 'Sitemap',
				'lists all ' . number_format($url_count) . ' laws');
		}
		else
		{
			$this->report('WARN', 'Sitemap',
				'lists ' . number_format($url_count) . ' URLs, expected '
				. number_format($expected) . ' — possibly stale');
		}

	}

	/*
	 * write_api_key() runs at the end of every import. Keys are not
	 * edition-scoped, so this only confirms one exists at all.
	 */
	public function checkApiKey()
	{

		$statement = $this->db->prepare('SELECT COUNT(*) FROM api_keys');
		$statement->execute();
		$count = (int) $statement->fetchColumn();

		if ($count == 0)
		{
			$this->report('WARN', 'API key',
				'no API keys registered — the API will reject all requests');
		}
		else
		{
			$this->report('OK', 'API key',
				$count . ' key' . ($count == 1 ? '' : 's') . ' registered');
		}

	}

	/*
	 * Run a single-value COUNT query scoped to this edition.
	 */
	public function countQuery($sql)
	{

		$statement = $this->db->prepare($sql);
		$statement->execute([':edition_id' => $this->edition->id]);
		return (int) $statement->fetchColumn();

	}

	/*
	 * Print one check result, and remember failures and warnings for
	 * the summary.
	 */
	public function report($status, $label, $detail)
	{

		print str_pad('[' . $status . ']', 7) . $label . ': ' . $detail . "\n";

		if ($status == 'FAIL')
		{
			$this->failures[] = $label;
		}
		elseif ($status == 'WARN')
		{
			$this->warnings[] = $label;
		}

	}

	/*
	 * Set the exit code and, if anything failed, say so on STDERR, so
	 * that failures surface even when STDOUT is redirected to a log.
	 */
	public function summarize()
	{

		if (count($this->failures) > 0)
		{
			$this->result = 1;
			fwrite(STDERR, 'checkup: ' . count($this->failures) . ' check'
				. (count($this->failures) == 1 ? '' : 's') . ' failed ('
				. implode(', ', array_unique($this->failures)) . ")\n");
			return "\nEdition “" . $this->edition->slug . '” has problems.';
		}

		if (count($this->warnings) > 0)
		{
			return "\nEdition “" . $this->edition->slug . '” looks imported, with '
				. count($this->warnings) . ' warning'
				. (count($this->warnings) == 1 ? '' : 's') . '.';
		}

		return "\nEdition “" . $this->edition->slug . '” checks out.';

	}

	public static function getHelp($args = [])
	{
		return <<<EOS
statedecoded : checkup

This action verifies that an edition was imported correctly, by
inspecting the artifacts the import leaves behind: laws, structures,
law texts, cross-references, permalinks, dictionary terms, bulk
exports, the sitemap, and the API key.

Exits 0 if all checks pass (warnings allowed), 1 if any check fails.
Failures are summarized on STDERR.

Usage:

  statedecoded checkup [--edition=slug]

Available options:

  --edition=slug
      Which edition to check.  Defaults to the current edition.

EOS;

	}
}
