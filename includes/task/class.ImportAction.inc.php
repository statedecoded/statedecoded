<?php

require_once 'class.CliAction.inc.php';
require_once CUSTOM_FUNCTIONS;

global $db;

class ImportAction extends CliAction
{
	static public $name = 'import';
	static public $summary = 'Imports new data.';

	public function __construct()
	{
		global $db;
		$db = new Database( PDO_DSN, PDO_USERNAME, PDO_PASSWORD );
		$this->db = $db;

		$this->logger = new Logger();
	}

	public function execute($args = array())
	{
		$this->logger->message('Starting import.', 10);
		try {
			$this->handleVerbosity();

			$parser = new ParserController(
				array(
					'logger' => $this->logger,
					'db' => &$this->db
				)
			);

			if (!$this->options['no-delete'])
			{
				$parser->clear_db();
				$parser->clear_index();
			}

			// TODO: Do edition stuff
			$edition_args = array();
			$edition_args['edition_option'] = 'existing';
			$edition = $parser->get_current_edition();
			$edition_args['edition'] = $edition->id;


			/*
			 * Step through each parser method.
			 */
			if ($parser->test_environment() !== FALSE)
			{
				echo 'Environment test succeeded<br />';

				if ($parser->populate_db() !== FALSE)
				{

					$edition_errors = $parser->handle_editions($edition_args);

					if (count($edition_errors) > 0)
					{
						throw new Exception(join("\n", $edition_errors), E_ERROR);
					}

					else
					{
						$parser->clear_apc();

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
		var_dump($this->options);
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

  statedecoded import [--no-delete] [-v[=#]]

Available options:

  --no-delete
      Do not empty the database and search index before importing.

  -v, -v=##
      Show verbose output.  ## is an optional value of 1 (default,
      all messages) to 10 (only important messages).

EOS;

	}
}
