<?php

require_once 'class.CliAction.inc.php';
require_once CUSTOM_FUNCTIONS;

class ExportAction extends CliAction
{
	static public $name = 'export';
	static public $summary = 'Regenerates the bulk download files (JSON, XML, etc.) for an edition.';

	public function __construct($args = [])
	{
		parent::__construct($args);

		global $db;
		$db = new Database( PDO_DSN, PDO_USERNAME, PDO_PASSWORD );
		$this->db = $db;

		$this->logger = new Logger();

		$this->handleVerbosity();
	}

	public function execute($args = [])
	{

		$this->logger->message('Starting export.', 10);

		try
		{
			$parser = new ParserController(
				[
					'logger' => $this->logger,
					'db' => &$this->db
				]
			);

			/*
			 * Figure out which edition to export. Unlike an import, this never
			 * creates or modifies an edition -- it only reads an existing one and
			 * rebuilds its download files.
			 */
			if (isset($this->options['edition']))
			{
				$edition_obj = new Edition(['db' => $this->db]);
				$edition = $edition_obj->find_by_slug($this->options['edition']);

				if (!$edition)
				{
					$this->result = 1;
					return 'Unable to find edition "' . $this->options['edition'] . '".';
				}
			}
			else
			{
				$edition = $parser->get_current_edition();

				if ($edition === false)
				{
					$this->result = 1;
					return 'No current edition exists. Specify one with --edition=slug.';
				}
			}

			$this->logger->message('Exporting edition "' . $edition->slug . '"', 10);

			/*
			 * Point the parser at the edition we're exporting. set_edition() also
			 * appends the edition slug to the downloads directory, which is where
			 * export() writes its files.
			 */
			$parser->edition_id = $edition->id;
			$parser->set_edition($edition);

			/*
			 * Rebuild every bulk download file for this edition. This deletes the
			 * existing downloads/<slug>/ directory and regenerates it from scratch.
			 */
			$parser->export();

			$this->logger->message('Done.', 10);
		}
		catch (Exception $e)
		{
			$this->result = 1;
			return 'Export failed: ' . $e->getMessage();
		}

		return '';
	}

	public function handleVerbosity()
	{
		$level = 10;
		if (isset($this->options['v']))
		{
			if ($this->options['v'] === true)
			{
				$level = 1;
			}
			else
			{
				$level = $this->options['v'];
			}
		}

		$this->logger->level = $level;
	}

	public static function getHelp($args = []) {
		return <<<EOS
statedecoded : export

Regenerates the bulk download files (one file per law, per format, plus the
zipped archives) for an edition, without re-importing any data. Use this to
rebuild the contents of the /downloads/ directory after an import failed
partway through or the files were lost.

The export's memory ceiling is governed by the IMPORT_MEMORY_LIMIT constant in
config.inc.php, not by the -d php.ini switch (the parser resets memory_limit at
startup). If a large edition exhausts memory partway through, raise
IMPORT_MEMORY_LIMIT and re-run.

Usage:

  statedecoded export [-v[=#]] [--edition=slug]

Available options:

  -v, -v=##
      Show verbose output. ## is an optional value of 1 (default, all
      messages, including one line per file written) to 10 (only important
      messages).

  --edition=slug
      Which edition to export. Defaults to the current edition.

EOS;

	}
}
