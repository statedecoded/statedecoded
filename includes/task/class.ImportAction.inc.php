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

		$started = microtime(true);
		$completed = false;

		/*
		 * The edition is not resolved until the database has been set up, several blocks down,
		 * but the completion message at the end of this method needs its name. Keep it in a
		 * variable that exists from the outset, so that the message does not depend on how far
		 * the import got.
		 */
		$edition_name = 'unknown';

		try {
			$parser = new ParserController(
				[
					'logger' => $this->logger,
					'db' => &$this->db,
					'import_data_dir' => $this->options['d']
				]
			);

			/*
			 * Step through each parser method.
			 */
			if ($parser->test_environment() !== false)
			{
				$this->logger->message('Environment test succeeded', 10);

				/*
				 * Create the tables and bring the schema up to date before anything reads from
				 * the database. On a new installation there is nothing to read from yet.
				 */
				if ($parser->populate_db() !== false)
				{

					$pending_migrations = $parser->check_migrations();
					if (count($pending_migrations) > 0)
					{
						$this->logger->message('Running ' . count($pending_migrations)
							. ' pending database migration(s): '
							. implode(', ', $pending_migrations), 5);
						$parser->run_migrations();
					}

					$edition_args = $this->buildEditionArgs($parser);
					$edition_name = $edition_args['edition_name'];

					$this->logger->message('Using edition ' . $edition_name, 10);

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

							/*
							 * The laws are in the database at this point, so the remaining steps
							 * still run and the import is allowed to finish. But a failed export
							 * leaves the bulk downloads incomplete, so the import must not
							 * report success.
							 */
							$export_ok = $parser->export();

							$parser->generate_sitemap();
							$parser->index_laws();
							$parser->structural_stats_generate();
							$parser->prune_views();
							$parser->finish_import();

							/*
							 * Likewise for a failed .htaccess write: the laws are imported, but
							 * EDITION_ID still names the previous edition, so parts of the site
							 * go on serving that one. Report it rather than claiming success.
							 */
							$edition_id_ok = ($parser->edition_id_export_error === true);

							$completed = ($export_ok !== false) && $edition_id_ok;
						}
						else
						{
							$this->logger->message('Parsing failed; no data was imported.', 10);
						}

					}

				}

			}

			/*
			 * Attempt to purge Varnish's cache. (Fails silently if Varnish isn't installed or running.)
			 */
			$varnish = new Varnish;
			$varnish->purge();

			/*
			 * Say plainly whether the import finished, and how long it took. An import runs for
			 * hours, so a log needs an unambiguous record of completion at the end of it -- and
			 * this point is reached whether or not the import actually did anything, so the
			 * message must distinguish the two.
			 */
			if ($completed === true)
			{
				$this->logger->message('Import complete. Imported ' .
					number_format($this->countLaws()) . ' laws into edition “' .
					$edition_name . '” in ' .
					$parser->format_duration(microtime(true) - $started) . '.', 10);
			}
			elseif (isset($export_ok) && $export_ok === false)
			{
				/*
				 * The laws are in the database, but the bulk downloads are not. Say which,
				 * rather than implying that nothing was imported.
				 */
				$this->result = 1;

				$message = 'Import finished, but the export failed: the bulk downloads for this '
					. 'edition are incomplete. The laws themselves were imported.';

				$this->logger->message($message, 10);
				fwrite(STDERR, $message . "\n");
			}
			elseif (isset($edition_id_ok) && $edition_id_ok === false)
			{
				/*
				 * The laws are imported and this edition is current in the database, but
				 * .htaccess still names the old one. Say so: the symptom -- parts of the site
				 * serving the previous edition -- is otherwise hard to trace back to here.
				 */
				$this->result = 1;

				$message = 'Import finished, but the edition ID could not be stored: '
					. $parser->edition_id_export_error;

				$this->logger->message($message, 10);
				fwrite(STDERR, $message . "\n");
			}
			else
			{
				$this->result = 1;
				$this->logger->message('Import did not complete.', 10);
				fwrite(STDERR, "Import did not complete.\n");
			}

		}
		/*
		 * Catch Throwable, not just Exception: a PHP Error (an undefined constant, a call to a
		 * method that does not exist) is not an Exception, and would otherwise escape this
		 * handler and end the import with a success exit code.
		 */
		catch(Throwable $e) {
			fwrite(STDERR, 'Import failed: ' . $e->getMessage() . "\n");
			fwrite(STDERR, $e->getFile() . ':' . $e->getLine() . "\n");
			exit(1);
		}

	}

	/*
	 * How many laws are in the database now. Reported at the end of an import, as a rough
	 * confirmation that it did what it set out to do.
	 */
	public function countLaws()
	{

		try
		{
			$statement = $this->db->prepare('SELECT COUNT(*) FROM laws');
			$statement->execute();
			return (int) $statement->fetchColumn();
		}
		catch (Throwable $e)
		{
			return 0;
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
		/*
		 * By default, show phase-level messages (level 5 and up), so that a
		 * long-running import narrates what it is doing. -v shows everything.
		 */
		$level = 5;
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
      Show verbose output.  ## is an optional value of 1 (default
      when -v is given: all messages) to 10 (only the most important
      messages).  Without -v, messages of level 5 and up are shown.

  -d=directory
      Directory to import data from.  Defaults to IMPORT_DATA_DIR

  --edition=slug
      Which edition to import into.  Defaults to the current edition.

  --current
      Make the selected edition current.

EOS;

	}
}
