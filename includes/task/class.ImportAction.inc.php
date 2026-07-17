<?php

require_once 'class.CliAction.inc.php';
require_once CUSTOM_FUNCTIONS;

class ImportAction extends CliAction
{
	static public $name = 'import';
	static public $summary = 'Imports new data.';

	public function __construct($args = [])
	{
		/*
		 * Note: PHP can't use constants as class defaults,
		 * so we cannot set this in $default_options above.
		 */
		if(defined('IMPORT_DATA_DIR'))
		{
			$this->default_options['d'] = IMPORT_DATA_DIR;
		}

		parent::__construct($args);

		global $db;
		$db = new Database( PDO_DSN, PDO_USERNAME, PDO_PASSWORD );
		$this->db = $db;

		$this->logger = new Logger();

		$this->handleVerbosity();
	}

	public function execute($args = [])
	{

		$this->logger->message('Starting import.', 10);

		try {
			$parser = new ParserController(
				[
					'logger' => $this->logger,
					'db' => &$this->db,
					'import_data_dir' => $this->options['d']
				]
			);

			$edition_args = $this->buildEditionArgs($parser);

			$this->logger->message('Using edition ' . $edition_args['edition_name'], 10);

			/*
			 * Step through each parser method.
			 */
			if ($parser->test_environment() !== false)
			{
				$this->logger->message('Environment test succeeded', 10);

				if ($parser->populate_db() !== false)
				{

					$edition_errors = $parser->handle_editions($edition_args);

					if (count($edition_errors) > 0)
					{
						throw new Exception(implode("\n", $edition_errors), E_ERROR);
					}

					else
					{
						$parser->clear_cache();
						$parser->clear_edition($parser->edition_id);

						/*
						 * We should only continue if parsing was successful.
						 */
						if ($parser->parse())
						{
							$parser->build_permalinks();
							$parser->write_api_key();
							$parser->export();
							$parser->generate_sitemap();
							$parser->index_laws();
							$parser->structural_stats_generate();
							$parser->prune_views();
							$parser->finish_import();
						}

					}

				}

			}

			/*
			 * Attempt to purge Varnish's cache. (Fails silently if Varnish isn't installed or running.)
			 */
			$varnish = new Varnish;
			$varnish->purge();

			$this->logger->message('Done.', 10);

		}
		catch(Exception $e) {
			fwrite(STDERR, 'Import failed: ' . $e->getMessage() . "\n");
			exit(1);
		}

	}

	/*
	 * Assemble the edition arguments for ParserController::handle_editions().
	 *
	 * Note that handle_editions() honors the "make_current" key, so that is
	 * what the --current option must set. (It once set a "current" key, which
	 * handle_editions() ignored, quietly breaking --current for existing
	 * editions.)
	 */
	public function buildEditionArgs($parser)
	{

		$edition_args = [];

		if(isset($this->options['edition']))
		{
			$edition_obj = new Edition(['db' => $this->db]);
			$edition = $edition_obj->find_by_slug($this->options['edition']);

			if(!$edition) {
				$this->logger->message('Unable to find edition "'. $this->options['edition'].'".', 10);
				die();
			}

			$edition_args['edition_option'] = 'existing';
			$edition_args['edition'] = $edition->id;
			$edition_args['edition_name'] = $edition->name;
		}
		else
		{
			$edition = $parser->get_current_edition();

			if($edition !== false)
			{
				$edition_args['edition_option'] = 'existing';
				$edition_args['edition'] = $edition->id;
				$edition_args['edition_name'] = $edition->name;
			}
			else
			{
				// No editions exist yet — create a default one.
				$edition_args['edition_option'] = 'new';
				$edition_args['new_edition_name'] = defined('SITE_TITLE') ? SITE_TITLE : 'Default';
				$edition_args['new_edition_slug'] = 'default';
				$edition_args['edition_name'] = $edition_args['new_edition_name'];
				$edition_args['make_current'] = 1;
			}
		}

		if(isset($this->options['current'])) {
			$edition_args['make_current'] = 1;
		}

		return $edition_args;
	}

	public function handleVerbosity()
	{
		$level = 10;
		if(isset($this->options['v'])) {
			if($this->options['v'] === true) {
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
statedecoded : import

This action imports new data.  By default, this replaces the current edition.

Usage:

  statedecoded import [-v[=#]] [--edition=slug] [--current]

Available options:

  -v, -v=##
      Show verbose output.  ## is an optional value of 1 (default,
      all messages) to 10 (only important messages).

  -d=directory
      Directory to import data from.  Defaults to IMPORT_DATA_DIR

  --edition=slug
      Which edition to import into.  Defaults to the current edition.

  --current
      Make the selected edition current.

EOS;

	}
}
