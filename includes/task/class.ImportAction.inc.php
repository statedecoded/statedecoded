<?php

require_once 'class.CliAction.inc.php';
require_once CUSTOM_FUNCTIONS;

global $db;

class ImportAction extends CliAction
{
	static public $name = 'import';
	static public $summary = 'Imports new data.';

	public function __construct($args = array())
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

	public function execute($args = array())
	{

		$this->logger->message('Starting import.', 10);

		try {
			$edition_args = array();

			$parser = new ParserController(
				array(
					'logger' => $this->logger,
					'db' => &$this->db,
					'import_data_dir' => $this->options['d']
				)
			);

			/*
			 * We only use existing editions, for simplicity.
			 */
			$edition_args['edition_option'] = 'existing';

			if(isset($this->options['edition']))
			{
				$edition_obj = new Edition($this->db);
				$edition = $edition_obj->find_by_slug($this->options['edition']);

				if(!$edition) {
					$this->logger->message('Unable to find edition "'. $this->options['edition'].'".', 10);
					die();
				}

				$edition_args['edition'] = $edition->id;
			}
			else
			{
				$edition = $parser->get_current_edition();
				$edition_args['edition'] = $edition->id;
			}

			if(isset($this->options['current'])) {
				$edition_args['current'] = 1;
			}

			$this->logger->message('Using edition ' . $edition->name, 10);

			/*
			 * Step through each parser method.
			 */
			if ($parser->test_environment() !== FALSE)
			{
				$this->logger->message('Environment test succeeded', 10);

				if ($parser->populate_db() !== FALSE)
				{

					$edition_errors = $parser->handle_editions($edition_args);

					if (count($edition_errors) > 0)
					{
						throw new Exception(join("\n", $edition_errors), E_ERROR);
					}

					else
					{
						$parser->clear_cache();
						$parser->clear_edition($edition->id);

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
			exit(1);
		}

	}

	public function handleVerbosity()
	{
		$level = 10;
		if(isset($this->options['v'])) {
			if($this->options['v'] === TRUE) {
				$level = 1;
			}
			else
			{
				$level = $this->options['v'];
			}
		}

		$this->logger->level = $level;
	}

	public static function getHelp($args = array()) {
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
