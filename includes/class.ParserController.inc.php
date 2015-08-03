<?php

/**
 * Parser Controller
 *
 * PHP version 5
 *
 * @license		http://www.gnu.org/licenses/gpl.html GPL 3
 * @version		0.9
 * @link		http://www.statedecoded.com/
 * @since		0.7
*/

require_once(INCLUDE_PATH . 'task/class.MigrateAction.inc.php');

class ParserController
{
	public $db;
	public $logger;
	public $permalink_obj;
	public $import_data_dir;

	/*
	 * Temporary variables
	 */
	public $edition_id;
	public $previous_edition_id;

	public $downloads_url;
	public $downloads_dir;


	public function __construct($args)
	{

		/*
		 * Set our defaults
		 */
		foreach($args as $key=>$value)
		{
			$this->$key = $value;
		}

		/*
		 * Setup a logger.
		 */
		$this->init_logger();

		/*
		 * Prior to PHP v5.3.6, the PDO does not pass along to MySQL the DSN charset configuration
		 * option, and it must be done manually.
		 */
		if (version_compare(PHP_VERSION, '5.3.6', '<'))
		{
			$this->db->exec("SET NAMES utf8");
		}

		/*
		 * Set our default execution limits.
		 */
		$this->set_execution_limits();

		/*
		 * Set the default import location;
		 */
		if(!isset($this->import_data_dir))
		{
			$this->import_data_dir = IMPORT_DATA_DIR;
		}

		/*
		 * Set our objects
		 */
		$this->permalink_obj = new Permalink(array('db' => $this->db));

		/*
		 * Setup downloads directory.
		 */
		if(!isset($this->downloads_dir))
		{
			/*
			 * Define the location of the downloads directory.
			 */
			$this->downloads_dir = WEB_ROOT . '/downloads/';
		}
		if(!isset($this->downloads_url))
		{
			$this->downloads_url = '/downloads/';
		}
	}

    // {{{ init_logger()

	/*
	 * Let this script run for as long as is necessary to finish.
     * @access public
     * @static
     * @since Method available since Release 0.7
     */
	public function init_logger()
	{

		if (!isset($this->logger))
		{
			$this->logger = new Logger();
		}

	}

	public function set_execution_limits()
	{

		/*
		 * Let this script run for as long as is necessary to finish.
		 */
		set_time_limit(0);

		/*
		 * Give PHP lots of RAM.
		 */
		ini_set('memory_limit', IMPORT_MEMORY_LIMIT);

	}

	/**
	 * Check if we need to populate the db.
	 */
	public function check_db_populated()
	{
		/*
		 * To see if the database tables exist, just issue a query to the laws table.
		 */
		try
		{
			$sql = 'SELECT 1
					FROM laws
					LIMIT 1';

			$statement = $this->db->prepare($sql);
			$result = $statement->execute();
		} catch (Exception $except)
		{
			return FALSE;
		}
		return TRUE;
	}

	/**
	 * Populate the database
	 */
	public function populate_db()
	{

		if(!$this->check_db_populated())
		{
			/*
			 * We expect an exception here.  If there's not one,
			 * the table exists and we can go on our merry way.
			 */

			$this->logger->message('Creating the database tables', 5);

			/*
			 * The database tables do not exist, so see if the MySQL import file can be found.
			 */
			if (file_exists(WEB_ROOT . '/admin/statedecoded.sql') === FALSE)
			{
				$this->logger->message('Could not read find ' . WEB_ROOT . '/admin/statedecoded.sql to '
					. 'populate the database. Database tables could not be created.', 10);
				return FALSE;
			}

			/*
			 * Load the MySQL import file into MySQL. We don't prepare this query because PDO_MySQL
			 * didn't support multiple queries until PHP 5.3.
			 */
			$sql = file_get_contents(WEB_ROOT . '/admin/statedecoded.sql');
			$result = $this->db->exec($sql);
			if ($result === FALSE)
			{
				return FALSE;
			}
		}

		return TRUE;

	}

	/**
	 * Check for any outstanding migrations that need running.
	 */
	public function check_migrations()
	{
		$migrate_action = new MigrateAction(array(
			'db' => $this->db
		));

		$migrations = $migrate_action->getUndoneMigrations();

		return $migrations;
	}

	public function run_migrations()
	{
		$migrate_action = new MigrateAction(array(
			'db' => $this->db
		));

		ob_start();
		$result = $migrate_action->doMigrations();
		$result = nl2br(ob_get_contents()) . $result;
		ob_end_clean();

		return $result;
	}

	/**
	 * If the "editions" table lacks any entries, create a stock one within the database and within
	 * the configuration file.
	 */
	public function populate_editions()
	{

		$this->logger->message('Checking for an existing edition in the database', 5);

		/*
		 * If we have an entries in the editions table, then return true, indicating that the
		 * editions table has been populated.
		 */
		$sql = 'SELECT *
				FROM editions
				LIMIT 1';
		$statement = $this->db->prepare($sql);
		$result = $statement->execute();
		if ($result !== FALSE && $statement->rowCount() > 0)
		{
			return TRUE;
		}

		$this->logger->message('Adding an inaugural “editions” record to the database', 5);

		/*
		 * Add a record to editions, setting the label to today's date.
		 */
		$sql = 'INSERT INTO
				editions
				SET year = :date, date_created=now()';
		$sql_args = array(
			':date' => date('Y-M-d')
		);
		$statement = $this->db->prepare($sql);
		$result = $statement->execute($sql_args);

		/*
		 * If we could not add the record to the database.
		 */
		if ($result === FALSE)
		{
			$this->logger->message('Could not add an “editions” record to the database', 5);
			return FALSE;
		}

		return TRUE;

	}

	/*
	 * Get a list of all editions
	 */
	public function get_editions()
	{

		$sql = 'SELECT *
				FROM editions
				ORDER BY order_by';
		$statement = $this->db->prepare($sql);
		$result = $statement->execute();

		if ($result === FALSE || $statement->rowCount() == 0)
		{
			return FALSE;
		}
		else
		{
			$editions = array();
			$editions = $statement->fetchAll(PDO::FETCH_OBJ);
		}
		return $editions;

	}

	public function get_current_edition()
	{
		$sql = 'SELECT *
				FROM editions
				WHERE current = :current
				ORDER BY order_by';
		$sql_args[':current'] = 1;
		$statement = $this->db->prepare($sql);
		$result = $statement->execute($sql_args);

		if ($result === FALSE || $statement->rowCount() == 0)
		{
			return FALSE;
		}
		else
		{
			$edition = $statement->fetch(PDO::FETCH_OBJ);
		}
		return $edition;
	}

	public function handle_editions($post_data)
	{
		$previous_edition = $this->get_current_edition();
		if($previous_edition->id !== $this->edition_id)
		{
			$this->previous_edition_id = $previous_edition->id;
		}

		$errors = array();

		$create_data = array();

		if (!empty($post_data['make_current']))
		{

			if ($current = filter_var($post_data['make_current'], FILTER_VALIDATE_INT))
			{
				$create_data['current'] = (int) $current;
			}
			else
			{
				$errors[] = 'Unexpected value for “make this edition current.”';
			}

		}
		else
		{
			$create_data['current'] = 0;
		}

		if ($post_data['edition_option'] == 'new')
		{

			if ($name = filter_var($post_data['new_edition_name'], FILTER_SANITIZE_STRING))
			{
				$create_data['name'] = $name;
			}
			else
			{
				$errors[] = 'Please enter a valid edition name.';
			}

			if ($slug = filter_var($post_data['new_edition_slug'], FILTER_SANITIZE_STRING))
			{
				$create_data['slug'] = $slug;
			}
			else
			{
				$errors[] = 'Please enter a valid edition URL.';
			}

			if (count($errors) === 0)
			{

				$edition_id = $this->create_edition($create_data);

				if ($edition_id)
				{
					$this->edition_id = $edition_id;
				}
				else
				{
					$errors[] = 'Unable to create edition.';
				}

			}

		}

		elseif ($post_data['edition_option'] == 'existing')
		{
			if ($edition_id = filter_var($post_data['edition'], FILTER_VALIDATE_INT))
			{
				$this->edition_id = $edition_id;

				if($create_data['current'] > 0)
				{
					$create_data['id'] = $this->edition_id;
					$this->update_edition($create_data);
				}
			}
			else
			{
				$errors[] = 'Please select an edition to update.';
			}
		}
		else
		{
			$errors[] = 'Please select if you would like to create a new edition or ' .
				 'update an existing one.';
		}

		if(isset($this->edition_id))
		{
			/*
			 * Get the edition from the database and store a copy locally.
			 */
			$edition = new Edition();
			$edition_result = $edition->find_by_id($this->edition_id);

			if ($edition_result !== FALSE)
			{
				$this->set_edition($edition_result);

				// Write the EDITION_ID
				if($this->edition->current)
				{
					$edition->unset_current($this->edition_id);
					$this->export_edition_id($this->edition_id);
				}
			}
			else
			{
				$errors[] = 'The edition could not be found.';
			}
		}

		return $errors;
	}

	/**
	 * Set the edition.
	 */
	public function set_edition($edition)
	{
		$this->edition = $edition;
		$this->downloads_dir .= $edition->slug . '/';
		$this->downloads_url .= $edition->slug . '/';
	}


	/**
	 * Create a new edition.
	 */
	public function create_edition($edition = array())
	{

		$edition_obj = new Edition(array('db' => $this->db));

		/*
		 * Make sure we have a unique edition.
		 */
		if($edition_obj->find_by_name($edition['name']))
		{
			$this->logger->message('The name for that edition is already in use.  Please choose a different name.', 10);
			return FALSE;
		}
		if($edition_obj->find_by_slug($edition['slug']))
		{
			$this->logger->message('The slug for that edition is already in use.  Please choose a different slug.', 10);
			return FALSE;
		}

		return $edition_obj->create($edition);

	}

	/**
	 * Update an edition.
	 */
	public function update_edition($data)
	{
		if($data['id'])
		{
			$sql_args[':id'] = $data['id'];
			unset($data['id']);

			if(count($data))
			{
				$sql = 'UPDATE editions
						SET ';
				$update = array();
				foreach($data as $key => $value)
				{
					$sql_args[':' . $key] = $value;
					$update[] = $key .' = :' . $key;
				}
				$sql .= join(',', $update);
				$sql .= ' WHERE id = :id';

				$statement = $this->db->prepare($sql);
				$result = $statement->execute($sql_args);
			}
			else
			{
				trigger_error('Nothing to update on editions. Cowardly refusing.', E_USER_WARNING);
			}
		}
		else
		{
			trigger_error('Updating editions without an id! Cowardly refusing.', E_USER_WARNING);
		}
	}

	/**
	 * Store the edition id in the .htaccess file.
	 */


	public function export_edition_id($edition_id)
	{
		/*
		 * If possible, modify the .htaccess file, to store permanently the edition ID.
		 */
		if (is_writable(WEB_ROOT . '/.htaccess') == TRUE)
		{

			$htaccess = file_get_contents(WEB_ROOT . '/.htaccess');

			/*
			 * If there isn't already an edition ID in .htaccess, then write a new record.
			 * Otherwise, update the existing record.
			 */
			if (strpos($htaccess, ' EDITION_ID ') === FALSE)
			{
				$htaccess .= PHP_EOL . PHP_EOL . 'SetEnv EDITION_ID ' . $edition_id . PHP_EOL;
			}
			else
			{
				$htaccess = preg_replace('/SetEnv EDITION_ID (\d+)/', 'SetEnv EDITION_ID ' . $edition_id, $htaccess);
			}
			$result = file_put_contents(WEB_ROOT . '/.htaccess', $htaccess);

			if ($result)
			{
				$this->logger->message('Wrote edition ID to .htaccess', 5);
			}
			else
			{
				$this->logger->message('Could not write edition ID to .htaccess', 10);
			}

		}
		else
		{
			$this->logger->message('Cannot write to .htaccess', 10);
		}

		/*
		 * Store the edition ID as a constant, so that we can use it elsewhere in the import
		 * process.
		 */
		if(!defined('EDITION_ID'))
		{
			define('EDITION_ID', $edition_id);
		}
	}

	/**
	 * Clear out our database.
	 */
	public function clear_db()
	{

		$this->logger->message('Clearing out the database', 5);

		$tables = array('dictionary', 'laws', 'laws_references', 'text', 'laws_views',
			'tags', 'text_sections', 'structure', 'permalinks', 'laws_meta');
		foreach ($tables as $table)
		{

			/*
			 * Note that we *cannot* prepare the table name as an argument here.
			 * PDO doesn't work that way.
			 * We are deleting instead of truncating, to handle foreign keys.
			 */
			$sql = 'DELETE FROM ' . $table;

			$statement = $this->db->prepare($sql);
			$result = $statement->execute();

			if ($result === FALSE)
			{
				$this->logger->message('Error in SQL: ' . $sql, 10);
				die();
			}

			$this->logger->message('Deleted ' . $table, 5);

		}

		/*
		 * Delete law histories.
		 */
		$sql = 'DELETE FROM laws_meta
				WHERE meta_key = :meta_key_history
				OR meta_key = :meta_key_repealed';
		$sql_args = array(
			':meta_key_history' => 'history',
			':meta_key_repealed' => 'repealed'
		);
		$statement = $this->db->prepare($sql);
		$result = $statement->execute($sql_args);

		return TRUE;

	}

	public function clear_edition($edition_id)
	{
		$tables = array(
			'dictionary',
			'laws',
			'laws_references',
			'text',
			'text_sections',
			//'laws_views',
			'tags',
			'structure',
			'permalinks',
			'laws_meta'
		);

		foreach ($tables as $table)
		{

			/*
			 * Note that we *cannot* prepare the table name as an argument here.
			 * PDO doesn't work that way.
			 * We are deleting instead of truncating, to handle foreign keys.
			 */
			$sql = 'DELETE FROM ' . $table . ' WHERE edition_id = :edition_id';
			$sql_args = array(':edition_id' => $this->edition_id);

			$statement = $this->db->prepare($sql);
			$result = $statement->execute($sql_args);

			if ($result === FALSE)
			{
				$this->logger->message('Error in SQL: ' . $sql, 10);
				die();
			}

			$this->logger->message('Deleted ' . $table, 5);
		}

		return TRUE;
	}

	/**
	 * Remove law-view records greater than one year old.
	 */
	public function prune_views()
	{

		$sql = 'DELETE FROM
				laws_views
				WHERE DATEDIFF(now(), date) > :date_diff';
		$sql_args = array(
			':date_diff' => 365
		);
		$statement = $this->db->prepare($sql);
		$result = $statement->execute($sql_args);

		$this->logger->message('Pruned view records greater than one year old', 5);

		return TRUE;

	}

	/**
	 * Parse the provided legal code
	 */
	public function parse()
	{

		$this->logger->message('Beginning the import process', 5);

		/*
		 * Create a new instance of Parser.
		 * Check for obvious errors (files not present, etc);
		 */

		try
		{

			$parser = new Parser(
				array(
					/*
					 * Tell the parser what the working directory
					 * should be for the data files to import.
					 */
					'directory' => $this->import_data_dir,

					/*
					 * Set the database
					 */
					'db' => $this->db,

					/*
					 * Set the edition
					 */
					'edition_id' => $this->edition_id,

					/*
					 * Set the logger
					 */
					'logger' => $this->logger,

					/*
					 * Set the downloads directories
					 */
					'downloads_dir' => $this->downloads_dir,
					'downloads_url' => $this->downloads_url

				)
			);

			if(method_exists($parser, 'pre_parse'))
			{
				$parser->pre_parse();
			}

			/*
			 * Iterate through the files.
			 */
			$this->logger->message('Importing the law files in the import-data directory', 3);

			while ($section = $parser->iterate())
			{
				$parser->section = $section;
				$parser->parse();
				$parser->store();
			}

			if(method_exists($parser, 'post_parse'))
			{
				$parser->post_parse();
			}

		}
		catch(Exception $e)
		{
			$this->logger->message('Import error: ' . $e->getMessage(), 10);
			return false;
		}

		/*
		 * Handle references
		 */

		$this->update_laws_references();

		/*
		 * Break up law histories into their components and save those.
		 */
		$sql = 'SELECT id, history
				FROM laws';
		$statement = $this->db->prepare($sql);
		$result = $statement->execute();

		if ($result !== FALSE && $statement->rowCount() > 0)
		{

			/*
			 * Step through the history of every law that we have a record of.
			 */

			$sql = 'INSERT INTO laws_meta
					SET law_id = :law_id,
					meta_key = :meta_key,
					meta_value = :meta_value,
					date_created = now(),
					edition_id = :edition_id';
			$statement = $this->db->prepare($sql);

			while ($law = $statement->fetch(PDO::FETCH_OBJ))
			{

				/*
				 * Turn the string of text that comprises the history into an object of atomic
				 * history history data.
				 */
				$parser->history = $law->history;
				$history = $parser->extract_history();

				if ($history !== FALSE)
				{

					/*
					 * Save this object to the metadata table pair.
					 */
					$sql_args = array(
						':law_id' => $law->id,
						':meta_key' => 'history',
						':meta_value' => serialize($history),
						':edition_id' => $this->edition_id
					);
					$result = $statement->execute($sql_args);

				}

			}

			$this->logger->message('Analyzed and stored law codification histories', 3);

			$this->logger->message('Analyzed and stored law codification histories', 3);

		}

		/*
		 * If we already have a view, replace it with this new one.
		 */
		$sql = 'DROP VIEW IF EXISTS structure_unified';
		$statement = $this->db->prepare($sql);
		$result = $statement->execute();

		/*
		 * The depth of the structure is the number of entries in the structure labels,
		 * minus one for 'section'.
		 */
		$structure_depth = count($parser->get_structure_labels())-1;

		$select = array();
		$from = array();
		$order = array();
		for ($i=1; $i<=$structure_depth; $i++)
		{
			$select[] = 's'.$i.'.id AS s'.$i.'_id, s'.$i.'.name AS s'.$i.'_name,
					s'.$i.'.identifier AS s'.$i.'_identifier, s'.$i.'.label AS s'.$i.'_label';
			$from[] = 's'.$i;
			$order[] = 's'.$i.'.identifier';
		}

		/*
 		 * We want to to order from highest to lowest, so flip this array around.
		 */
		$order = array_reverse($order);

		/*
		 * First, the preamble.
		 */
		$sql = 'CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW structure_unified AS SELECT ';

		/*
		 * Then the actual SELECT statement.
		 */
		$sql .= implode(',', $select);

		/*
		 * Start the FROM statement.
		 */
		$sql .= ' FROM (structure AS ';

		/*
		 * Build up the FROM statement using the array of table names.
		 */
		$prev = '';
		foreach ($from as $table)
		{

			if ($table == 's1')
			{
				$sql .= $table;
			}
			else
			{
				$sql .= ' LEFT JOIN structure AS '.$table.' ON ('.$table.'.id = '.$prev.'.parent_id)';
			}
			$prev = $table;

		}

		/*
		 * Conclude the FROM statement.
		 */
		$sql .= ')';

		/*
		 * Finally, construct the ORDER BY statement.
		 */
		$sql .= ' ORDER BY '.implode(',', $order);

		/*
		 * There's nothing here that we can actually prepare. Column names aren't allowed.
		 */
		$statement = $this->db->prepare($sql);
		$result = $statement->execute();

		if ($result == FALSE)
		{
			$this->logger->message('Could not map the structure of the laws', 10);
		}

		if ($result == FALSE)
		{
			$this->logger->message('Could not map the structure of the laws', 10);
		}
		else {
			$this->logger->message('Mapped the structure of the laws', 5);
		}

		return TRUE;

	}

	/**
	 * Finish up.
	 */
	public function finish_import()
	{
		$edition_obj = new Edition(array('db' => $this->db));
		$edition_obj->update_last_import($this->edition_id);

		return TRUE;
	}

	/**
	 * Build the permalinks
	 */
	public function build_permalinks()
	{

		/*
		 * Create a new instance of Parser.
		 */
		$parser = new Parser(
			array(
				/*
				 * Set the database
				 */
				'db' => $this->db,

				/*
				 * Set the edition
				 */
				'edition_id' => $this->edition_id,

				/*
				 * Set the previous edition
				 */
				'previous_edition_id' => $this->previous_edition_id,

				/*
				 * Set the logger
				 */
				'logger' => $this->logger,

				/*
				 * Set the downloads directories
				 */
				'downloads_dir' => $this->downloads_dir,
				'downloads_url' => $this->downloads_url
			)
		);

		$parser->build_permalinks();

		$this->logger->message('Constructed and stored the URLs for all laws', 5);

	}

	/**
	 * Generate an API key and store it
	 *
	 * See if an API key needs to be created. If it does, create it in the database, and then write
	 * it to the config file.
	 */
	public function write_api_key()
	{

		/*
		 * If the site's internal API key is undefined in the config file, register a new key and
		 * activate it.
		 */
		if (API_KEY == '')
		{

			$api = new API();
			$api->form->email = EMAIL_ADDRESS;
			$api->form->url = 'http://' . $_SERVER['SERVER_NAME'] . '/';
			$api->suppress_activation_email = TRUE;
			$api->register_key();
			$api->activate_key();

			/*
			 * Add the API key to the config file, if it's writable. Otherwise, display it on the
			 * screen, along with instructions.
			 */
			$config_file = INCLUDE_PATH . '/config.inc.php';

			if (is_writable($config_file))
			{

				$config = file_get_contents($config_file);
				$config = str_replace("('API_KEY', '')", "('API_KEY', '" . $api->key . "')", $config);
				file_put_contents($config_file, $config);

				$this->logger->message('Created internal API key', 5);

			}
			else
			{

				$this->logger->message('Created the internal API key, but your config.inc.php file '
					. 'could not be modified to store it—please edit that file and set the value '
					. 'of API_KEY to ' . $api->key, 10);

			}

			return TRUE;

		}

		/*
		 * If the internal API key is defined in the config file, make sure it actually exists in
		 * the database.
		 */
		else
		{

			/*
			 * Check the database for the API key in the config file.
			 */
			$api = new API();
			$api->key = API_KEY;
			$registered = $api->get_key();

			/*
			 * If the key isn't in the database, create a new one. It might be trouble to try to
			 * overwrite the key already listed in config.inc.php, so the safe thing to do is to
			 * report the new key to the user, and let her sort it out.
			 */
			if ($registered === FALSE)
			{

				$api->form->email = EMAIL_ADDRESS;
				$api->form->url = 'http://' . $_SERVER['SERVER_NAME'] . '/';
				$api->suppress_activation_email = TRUE;
				$api->register_key();
				$api->activate_key();

				$this->logger->message('The API key in <code>config.inc.php</code> does not exist '
					. 'in the database; a new key (' . $api->key . ') has been registered, but you '
					. 'must add it to config.inc.php manually.', 5);

				return TRUE;

			}

			$this->logger->message('Using the existing API key', 3);

		}
	}


	/**
	 * Create exports
	 *
	 * There are a handful of bulk downloads that are created. This gathers up the data and creates
	 * those files. It also creates the downloads directory, if it doesn't exist.
	 */
	public function export()
	{

		$this->logger->message('Exporting bulk download files', 5);

		$this->setup_directories();

		/*
		 * Begin the process of exporting each section.
		 * We start at the top, with no parents.
		 */
		$this->export_structure();

		/*
		 * Zip up all of the JSON files into a single file. We do this via exec(), rather than
		 * PHP's ZIP extension, because doing it within PHP requires far too much memory. Using
		 * exec() is faster and more efficient.
		 */

		/*
		 * Set a flag telling us that we may write files.
		 */
		$write_json = TRUE;
		$write_text = TRUE;
		$write_xml = TRUE;


		if ($write_json === TRUE)
		{

			$output = array();
			exec('cd ' . $this->downloads_dir . '; zip -9rq code.json.zip code-json');
			$this->logger->message('Created a ZIP file of the laws as JSON', 3);

		}

		/*
		 * Zip up all of the text into a single file.
		 */
		if ($write_text === TRUE)
		{

			$output = array();
			exec('cd ' . $this->downloads_dir . '; zip -9rq code.txt.zip code-text');
			$this->logger->message('Created a ZIP file of the laws as plain text', 3);

		}

		/*
		 * Zip up all of the XML into a single file.
		 */
		if ($write_xml === TRUE)
		{

			$output = array();
			exec('cd ' . $this->downloads_dir . '; zip -9rq code.xml.zip code-xml');
			$this->logger->message('Created a ZIP file of the laws as XML', 3);

		}

		/*
		 * Save dictionary as JSON.
		 */
		$sql = 'SELECT laws.section, dictionary.term, dictionary.definition, dictionary.scope
				FROM dictionary
				LEFT JOIN laws
					ON dictionary.law_id = laws.id
				ORDER BY dictionary.term ASC, laws.order_by ASC';
		$statement = $this->db->prepare($sql);
		$result = $statement->execute();

		if ($result !== FALSE && $statement->rowCount() > 0)
		{

			/*
			 * Retrieve the entire dictionary as a single object.
			 */
			$dictionary = $statement->fetchAll(PDO::FETCH_OBJ);

			/*
			 * Define the filename for our dictionary.
			 */
			$filename = $this->downloads_dir . 'dictionary.json.zip';

			/*
			 * Create a new ZIP file object.
			 */
			$zip = new ZipArchive();

			if (file_exists($filename))
			{
				unlink($filename);
			}

			/*
			 * If we cannot create a new ZIP file, bail.
			 */
			if ($zip->open($filename, ZIPARCHIVE::CREATE) !== TRUE)
			{
				$this->logger->message('Cannot open ' . $filename . ' to create a new ZIP file.', 10);
			}

			else
			{

				/*
				 * Add this law to our ZIP archive.
				 */
				$zip->addFromString('dictionary.json', json_encode($dictionary));

				/*
				 * Close out our ZIP file.
				 */
				$zip->close();

			}

			$this->logger->message('Created a ZIP file of all dictionary terms as JSON', 3);

		}

		$this->logger->message('Creating symlinks', 4);

		if ($this->edition->current == '1')
		{

			$result = exec('cd ' . WEB_ROOT . '/downloads/; rm current; ln -s ' .
				$this->edition->slug . ' current');

			if ($result != 0)
			{
				$this->logger->message('Could not create “current” symlink in /downloads/—it must '
					. 'be created manually', 10);
			}

			$this->logger->message('Created downloads “current” symlink', 4);

			$this->logger->message('Created downloads “current” symlink', 4);

		}

		$this->logger->message('All bulk download files were exported', 5);

	}

	/**
	 * Export a single structure
	 */
	public function export_structure($parent_id = null)
	{
		$structure_sql = '	SELECT structure_unified.*
							FROM structure
							LEFT JOIN structure_unified
								ON structure.id = structure_unified.s1_id';

		$structure_args = array();

		if (isset($parent_id))
		{
			$structure_sql .= ' WHERE parent_id = :parent_id';
			$structure_args[':parent_id'] = $parent_id;
		}
		else
		{
			$structure_sql .= ' WHERE parent_id IS NULL';
		}

		$structure_sql .= ' AND edition_id = :edition_id';
		$structure_args[':edition_id'] = $this->edition_id;

		$structure_statement = $this->db->prepare($structure_sql, array(PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY));
		$structure_result = $structure_statement->execute($structure_args);

		if ($structure_result === FALSE)
		{
			echo '<p>' . $structure_sql . '</p>';
			echo '<p>' . $structure_result->getMessage() . '</p>';
			return;
		}

		/*
		 * Get results as an array to save memory
		 */
		while ($item = $structure_statement->fetch(PDO::FETCH_ASSOC))
		{

			/*
			 * Figure out the URL for this structural unit by iterating through the "identifier"
			 * columns in this row.
			 */
			$identifier_parts = array();

			foreach ($item as $key => $value)
			{
				if (preg_match('/s[0-9]_identifier/', $key) == 1)
				{
					/*
					 * Higher-level structural elements (e.g., titles) will have blank columns in
					 * structure_unified, so we want to omit any blank values. Because a valid
					 * structural unit identifier is "0" (Virginia does this), we check the string
					 * length, rather than using empty().
					 */
					if (strlen($value) > 0)
					{
						$identifier_parts[] = urlencode($value);
					}
				}
			}
			$identifier_parts = array_reverse($identifier_parts);
			$token = implode('/', $identifier_parts);

			/*
			 * This is slightly different from how we handle permalinks since we don't want to
			 * overwrite files if current has changed.
			 */

			$url = '/';
			if(defined('LAW_LONG_URLS') && LAW_LONG_URLS === TRUE)
			{
				$url .= $token . '/';
			}
			/*
			 * Now we can use our data to build the child law identifiers
			 */
			if (INCLUDES_REPEALED !== TRUE)
			{
				$laws_sql = '	SELECT id, structure_id, section AS section_number, catch_line
								FROM laws
								WHERE structure_id = :s_id
								AND laws.edition_id = :edition_id
								ORDER BY order_by, section';
			}
			else
			{
				$laws_sql = '	SELECT laws.id, laws.structure_id, laws.section AS section_number,
								laws.catch_line
								FROM laws
								LEFT OUTER JOIN laws_meta
									ON laws_meta.law_id = laws.id AND laws_meta.meta_key = "repealed"
								WHERE structure_id = :s_id
								AND (laws_meta.meta_value = "n" OR laws_meta.meta_value IS NULL)
								AND laws.edition_id = :edition_id
								ORDER BY order_by, section';
			}
			$laws_args = array(
				':s_id' => $item['s1_id'],
				':edition_id' => $this->edition_id
			);

			$laws_statement = $this->db->prepare($laws_sql, array(PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY));
			$laws_result = $laws_statement->execute( $laws_args );

			if ($laws_result !== FALSE && $laws_statement->rowCount() > 0)
			{

				/*
				 * Establish the path of our code JSON storage directory.
				 */
				$json_dir = $this->downloads_dir . 'code-json' . $url;
				$this->mkdir($json_dir);

				/*
				 * Set a flag telling us that we may write JSON.
				 */
				$write_json = TRUE;

				/*
				 * Establish the path of our code text storage directory.
				 */
				$text_dir = $this->downloads_dir . 'code-text' . $url;
				$this->mkdir($text_dir);

				/*
				 * Set a flag telling us that we may write text.
				 */
				$write_text = TRUE;

				/*
				 * Establish the path of our code XML storage directory.
				 */
				$xml_dir = $this->downloads_dir . 'code-xml' . $url;
				$this->mkdir($xml_dir);

				/*
				 * Set a flag telling us that we may write XML.
				 */
				$write_xml = TRUE;

				/*
				 * Create a new instance of the Parser class, so that we have access to its
				 * get_structure_labels() method.
				 */
				$parser = new Parser(
					array(
						'db' => $this->db,
						'logger' => $this->logger,
						'edition_id' => $this->edition_id,
						'downloads_dir' => $this->downloads_dir,
						'downloads_url' => $this->downloads_url
					)
				);

				/*
				 * Create a new instance of the class that handles information about individual laws.
				 */
				$laws = new Law();

				/*
				 * Iterate through every section number, to pass to the Laws class.
				 */
				while ($section = $laws_statement->fetch(PDO::FETCH_OBJ))
				{

					/*
					 * Instruct the Law class on what, specifically, it should retrieve.
					 */
					$laws->config->get_text = TRUE;
					$laws->config->get_structure = TRUE;
					$laws->config->get_amendment_attempts = FALSE;
					$laws->config->get_court_decisions = TRUE;
					$laws->config->get_metadata = TRUE;
					$laws->config->get_references = TRUE;
					$laws->config->get_related_laws = TRUE;

					/*
					 * Pass the requested section number to Law.
					 */
					$laws->law_id = $section->id;
					$laws->edition_id = $this->edition_id;

					unset($law, $section);

					/*
					 * Get a list of all of the basic information that we have about this section.
					 */
					$law = $laws->get_law();

					if ($law !== FALSE)
					{

						/*
						 * Eliminate colons from section numbers, since some OSes can't handle colons in
						 * filenames.
						 */
						$filename = str_replace(':', '_', $law->section_number);

						/*
						 * Store the JSON file.
						 */
						if ($write_json === TRUE)
						{

							$success = file_put_contents($json_dir . $filename . '.json', json_encode($law));
							if ($success === FALSE)
							{
								$this->logger->message('Could not write law JSON file "' . $json_dir . $filename . '.json' . '"', 9);
								break;
							}
							else
							{
								$this->logger->message('Wrote file "'. $json_dir . $filename . '.json' .'"', 1);
							}

						}

						/*
						 * Store the text file.
						 */
						if ($write_text === TRUE)
						{

							$success = file_put_contents($text_dir . $filename . '.txt', $law->plain_text);
							if ($success === FALSE)
							{
								$this->logger->message('Could not write law text files "' . $text_dir . $filename . '.txt', $law->plain_text . '"', 9);
								break;
							}
							else
							{
								$this->logger->message('Wrote file "'. $json_dir . $filename . '.txt' .'"', 1);
							}

						}

						/*
						 * Store the XML file.
						 */
						if ($write_xml === TRUE)
						{

							/*
							 * We need to massage the $law object to match the State Decoded XML
							 * standard. The first step towards this is removing unnecessary
							 * elements.
							 */
							unset($law->plain_text);
							unset($law->structure_contents);
							unset($law->next_section);
							unset($law->previous_section);
							unset($law->amendment_years);
							unset($law->dublin_core);
							unset($law->plain_text);
							unset($law->section_id);
							unset($law->structure_id);
							unset($law->edition_id);
							unset($law->full_text);
							unset($law->formats);
							unset($law->html);
							$law->structure = $law->ancestry;
							unset($law->ancestry);
							$law->referred_to_by = $law->references;
							unset($law->references);

							/*
							 * Encode all entities as their proper Unicode characters, save for the
							 * few that are necessary in XML.
							 */
							$law = html_entity_decode_object($law);

							/*
							 * Quickly turn this into an XML string.
							 */
							$xml = new SimpleXMLElement('<law />');
							object_to_xml($law, $xml);

							$xml = $xml->asXML();

							/*
							 * Load the XML string into DOMDocument.
							 */
							$dom = new DOMDocument();
							$dom->loadXML($xml);

							/*
							 * We're going to be inserting some things before the catch line.
							 */
							$catch_lines = $dom->getElementsByTagName('catch_line');
							$catch_line = $catch_lines->item(0);

							$law_dom = $dom->getElementsByTagName('law');
							$law_dom = $law_dom->item(0);

							/*
							 * Add the main site info.
							 */
							if(defined('SITE_TITLE'))
							{
								$site_title = $dom->createElement('site_title');
								$site_title->appendChild($dom->createTextNode(SITE_TITLE));
								$law_dom->insertBefore($site_title, $catch_line);
							}

							if(defined('SITE_URL'))
							{
								$site_url = $dom->createElement('site_url');
								$site_url->appendChild($dom->createTextNode(SITE_URL));
								$law_dom->insertBefore($site_url, $catch_line);
							}

							/*
							 * Set the edition.
							 */
							$edition = $dom->createElement('edition');
							$edition->appendChild($dom->createTextNode($this->edition->name));

							$edition_url = $dom->createAttribute('url');
							$edition_url->value = '';
							if(defined('SITE_URL'))
							{
								$edition_url->value = SITE_URL;
							}
							$edition_url->value .= '/' . $this->edition->slug . '/';
							$edition->appendChild($edition_url);

							$edition_id = $dom->createAttribute('id');
							$edition_id->value = $this->edition->id;
							$edition->appendChild($edition_id);

							$edition_last_updated = $dom->createAttribute('last_updated');
							$edition_last_updated->value = date('Y-m-d', strtotime($this->edition->last_import));
							$edition->appendChild($edition_last_updated);

							$edition_current = $dom->createAttribute('current');
							$edition_current->value = $this->edition->current ? 'TRUE' : 'FALSE';
							$edition->appendChild($edition_current);

							$law_dom->insertBefore($edition, $catch_line);

							/*
							 * Simplify every reference, stripping them down to the cited sections.
							 */
							$referred_to_by = $dom->getElementsByTagName('referred_to_by');
							if ( !empty($referred_to_by) && ($referred_to_by->length > 0) )
							{
								$referred_to_by = $referred_to_by->item(0);
								$references = $referred_to_by->getElementsByTagName('unit');

								/*
								 * Iterate backwards through our elements.
								 */
								for ($i = $references->length; --$i >= 0;)
								{

									$reference = $references->item($i);

									/*
									 * Save the section number.
									 */
									$section_number = trim($reference->getElementsByTagName('section_number')->item(0)->nodeValue);

									/*
									 * Create a new element, named "reference," which contains the only
									 * the section number.
									 */
									$element = $dom->createElement('reference', $section_number);
									$reference->parentNode->insertBefore($element, $reference);

									/*
									 * Remove the "unit" node.
									 */
									$reference->parentNode->removeChild($reference);

								}

							}

							/*
							 * Simplify and reorganize every structural unit.
							 */
							$structure_elements = $dom->getElementsByTagName('structure');
							if ( !empty($structure_elements) && ($structure_elements->length > 0) )
							{
								$structure_element = $structure_elements->item(0);
								$structural_units = $structure_element->getElementsByTagName('unit');

								$law_dom->insertBefore($structure_element, $catch_line);

								/*
								 * Iterate backwards through our elements.
								 */
								for ($i = $structural_units->length; --$i >= 0;)
								{
									$structure_element->removeChild($structural_units->item($i));
								}

								/*
								 * Build up our structures.
								 * The count/get_object_vars is really fragile, and not a good way to do this.
								 * TODO: Refactor all of $law->structure to be an array, not an object.
								 */
								$level_value = 0;
								for ($i = count(get_object_vars($law->structure))+1; --$i >= 1;)
								{
									$structure = $law->structure->{$i};
									$level_value++;

									$unit = $dom->createElement('unit');

									/*
									 * Add the "level" attribute.
									 */
									$label = trim(strtolower($unit->getAttribute('label')));
									$level = $dom->createAttribute('level');
									$level->value = $level_value;

									$unit->appendChild($level);

									/*
									 * Add the "identifier" attribute.
									 */
									$identifier = $dom->createAttribute('identifier');
									$identifier->value = trim($structure->identifier);
									$unit->appendChild($identifier);

									/*
									 * Add the "url" attribute.
									 */
									$url = $dom->createAttribute('url');
									$permalink = $this->permalink_obj->get_permalink($structure->id, 'structure', $this->edition_id);
									$url->value = '';
									if(defined('SITE_URL'))
									{
										$url->value = SITE_URL;
									}
									$url->value .= $permalink->url;

									$unit->appendChild($url);

									/*
									 * Store the name of this structural unit as the contents of <unit>.
									 */
									$unit->nodeValue = trim($structure->name);

									/*
									 * Save these changes.
									 */
									$structure_element->appendChild($unit);
								}

							}

							/*
							 * Rename text units as text sections.
							 */
							$text = $dom->getElementsByTagName('text');
							if (!empty($text) && ($text->length > 0))
							{
								$text = $text->item(0);
								$text_units = $text->getElementsByTagName('unit');

								/*
								 * Iterate backwards through our elements.
								 */
								for ($i = $text_units->length; --$i >= 0;)
								{
									$text_unit = $text_units->item($i);
									renameElement($text_unit, 'section');
								}

							}

							/*
							 * Save the cleaned-up XML to the filesystem.
							 */
							$success = file_put_contents($xml_dir . $filename . '.xml', $dom->saveXML());
							if ($success === FALSE)
							{
								$this->logger->message('Could not write law XML files', 9);
								break;
							}

						}

					} // end the $law exists condition

				} // end the while() law iterator
			} // end the $laws condition

			$this->export_structure($item['s1_id']);

		} // end the while() structure iterator
	}

	/**
	 * Create necessary folders.
	 */
	public function setup_directories()
	{

		if(!isset($this->edition) || !isset($this->edition->slug))
		{
			$this->logger->message('Edition is missing—cannot write new files', 10);
			throw new Exception('Edition is missing');
		}

		/*
		 * Delete our old downloads directory.
		 */
		$this->logger->message('Removing old downloads directory', 5);
		exec('cd ' . WEB_ROOT . '/downloads/; rm -R ' . $this->edition->slug);

		/*
		 * If we cannot write files to the downloads directory, then we can't export anything.
		 */
		if (is_writable($this->downloads_dir) === FALSE)
		{
			$this->logger->message('Error: ' . $this->downloads_dir . ' could not be written to, so bulk
				download files could not be exported', 10);
			return FALSE;
		}

		foreach (array('code-json', 'code-text', 'code-xml', 'images') as $data_dir)
		{

			$this->logger->message('Creating "' . $this->downloads_dir . $data_dir . '"', 4);

			/*
			 * If the JSON directory doesn't exist, create it.
			 */
			$this->mkdir($this->downloads_dir . $data_dir);

		}

		$this->logger->message('Created output directories for bulk download files', 5);

	}

	public function mkdir($dir)
	{

			/*
			 * If the directory doesn't exist, create it.
			 */
			if (!file_exists($dir))
			{
				/*
				 * Build our directories recursively.
				 * Don't worry about the mode, as our server's umask should handle
				 * that for us.
				 */
				if (!mkdir($dir, 0777, true))
				{
					$this->logger->message('Cannot create directory "' . $dir . '"', 10);
				}
			}

			/*
			 * If we cannot write to the JSON directory, log an error.
			 */
			if (!is_writable($dir))
			{
				$this->logger->message('Cannot write to "' . $dir . '"', 10);
			}
	}

	/**
	 * Create and save a sitemap.xml
	 *
	 * List every law in this legal code and create an XML file with an entry for every one of them.
	 */
	public function generate_sitemap()
	{

		/*
		 * The sitemap.xml file must be kept in the site root, as per the standard.
		 */
		$sitemap_file = WEB_ROOT . '/sitemap.xml';

		if (!is_writable($sitemap_file))
		{
			$this->logger->message('Do not have permissions to write to sitemap.xml', 3);
			return FALSE;
		}

		/*
		 * List the ID of every law in the current edition. We cut it off at 50,000 laws because
		 * that is the sitemap.xml limit.
		 */
		$sql = 'SELECT id
				FROM laws
				WHERE edition_id = :edition_id
				LIMIT 50000';

		$sql_args = array(
			':edition_id' => EDITION_ID
		);

		$statement = $this->db->prepare($sql);
		$result = $statement->execute($sql_args);

		if ($result === FALSE || $statement->rowCount() == 0)
		{
			$this->logger->message('No laws could be found to export to sitemap.xml', 3);
			return FALSE;
		}

		/*
		 * Create a new XML file, using the sitemap.xml schema.
		 */

		$xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1" />');

		/*
		 * Create a new instance of the class that handles information about individual laws.
		 */
		$laws = new Law();
		/*
		 * Iterate through every section ID.
		 */
		while ($section = $statement->fetch(PDO::FETCH_OBJ))
		{

			/*
			 * Instruct the Law class on what, specifically, it should retrieve. (Very little.)
			 */
			$laws->config->get_all = FALSE;
			$laws->config->get_text = FALSE;
			$laws->config->get_structure = FALSE;
			$laws->config->get_amendment_attempts = FALSE;
			$laws->config->get_court_decisions = FALSE;
			$laws->config->get_metadata = FALSE;
			$laws->config->get_references = FALSE;
			$laws->config->get_related_laws = FALSE;
			$laws->config->render_html = FALSE;

			/*
			 * Get the law in question.
			 */
			$laws->law_id = $section->id;
			$law = $laws->get_law();

			/*
			 * Add a record of this law to the XML.
			 */
			$url = $xml->addChild('url');
			$url->addchild('loc', SITE_URL . $law->url);
			$url->addchild('changefreq', 'monthly');

		}

		/*
		 * Save the resulting file.
		 */
		file_put_contents($sitemap_file, $xml->asXML());

		$this->logger->message('Created sitemap.xml', 3);

		$this->logger->message('Created sitemap.xml', 3);

		return TRUE;

	}

	/**
	 * Clear out the in-memory cache, if it exists
	 */
	public function clear_cache()
	{

		/*
		 * If an in-memory cache is in use, invalidate all cached data. Everything that The State
		 * Decoded has stored is now suspect, as a result of having reloaded the laws.
		 */
		global $cache;
		if (isset($cache))
		{

			$cache->flush();
			$this->logger->message('Cleared in-memory cache', 5);

		}

	}

	/**
	 * Generate statistics about structural units
	 *
	 * Iterate through every structure to determine how many ancestors that it has (either ancestor
	 * structural units or laws), and then store that data as serialized objects in the "structure"
	 * database table.
	 */
	public function structural_stats_generate()
	{

		/*
		 * List all of the top-level structural units.
		 */
		$struct = new Structure();
		$structures = $struct->list_children();

		/*
		 * Create an object to store structural statistics.
		 */
		$this->stats = new stdClass();

		/*
		 * Iterate through each of those units.
		 */
		foreach ($structures as $structure)
		{
			$this->depth = 0;
			$this->structure_id = $structure->id;
			$this->structural_stats_recurse();
		}

		/*
		 * Iterate through every primary structural unit.
		 */
		foreach ($this->stats as $structure_id => $structure)
		{

			/*
			 * If this is more than 1 level deep.
			 */
			if (count($structure->ancestry) > 1)
			{
				/*
				 * Iterate through all but the last ancestry element.
				 */
				for ($i=0; $i < (count($structure->ancestry) - 1); $i++)
				{
					$ancestor_id = $structure->ancestry[$i];

					if (!isset($this->stats->{$ancestor_id}->child_laws))
					{
						$this->stats->{$ancestor_id}->child_laws = 0;
					}

					if (!isset($this->stats->{$ancestor_id}->child_structures))
					{
						$this->stats->{$ancestor_id}->child_structures = 0;
					}

					if (isset($structure->child_laws))
					{
						$this->stats->{$ancestor_id}->child_laws = $this->stats->{$ancestor_id}->child_laws + $structure->child_laws;
					}

					if (isset($structure->child_structures))
					{
						$this->stats->{$ancestor_id}->child_structures = $this->stats->{$ancestor_id}->child_structures + $structure->child_structures;
					}
				}
			}

		}

		$sql = 'UPDATE structure
				SET metadata = :metadata
				WHERE id = :structure_id';
		$statement = $this->db->prepare($sql);

		foreach ($this->stats as $structure_id => $structure)
		{

			if (!isset($structure->child_laws))
			{
				$structure->child_laws = 0;
			}
			if (!isset($structure->child_structures))
			{
				$structure->child_structures = 0;
			}

			$structure_temp = new Structure();
			$structure_temp->structure_id = $structure_id;
			$structure_temp->get_current();

			if(!isset($structure_temp->metadata))
			{
				$structure_temp->metadata = new stdClass();
			}
			$structure_temp->metadata->child_laws = $structure->child_laws;
			$structure_temp->metadata->child_structures = $structure->child_structures;

			$sql_args = array(
				':metadata' => serialize($structure_temp->metadata),
				':structure_id' => $structure_id
			);
			$result = $statement->execute($sql_args);

		}

		$this->logger->message('Generated structural statistics', 3);

		$this->logger->message('Generated structural statistics', 3);

	} // end structural_stats_generate()


	/**
	 * Recurse through structural information
	 *
	 * A helper method for structural_stats_generate(); not intended to be called on its own.
	 */
	public function structural_stats_recurse()
	{

		/*
		 * Retrieve basic stats about the children of this structural unit.
		 */
		$struct = new Structure();
		$struct->id = $this->structure_id;
		$child_structures = $struct->list_children();
		$child_laws = $struct->list_laws();

		/*
		 * Append the structure's ID to the structural ancestry.
		 */
		$this->ancestry[] = $this->structure_id;

		/*
		 * Store the tallies.
		 */
		$this->stats->{$this->structure_id} = new stdClass();
		if ($child_structures !== FALSE)
		{
			$this->stats->{$this->structure_id}->child_structures = count((array) $child_structures);
		}
		if ($child_laws !== FALSE)
		{
			$this->stats->{$this->structure_id}->child_laws = count((array) $child_laws);
		}
		$this->stats->{$this->structure_id}->depth = $this->depth;
		$this->stats->{$this->structure_id}->ancestry = $this->ancestry;

		/*
		 * If this structural unit has child structural units of its own, recurse into those.
		 */
		if (isset($this->stats->{$this->structure_id}->child_structures)
			&& ($this->stats->{$this->structure_id}->child_structures > 0))
		{
			foreach ($child_structures as $child_structure)
			{
				$this->depth++;
				$this->structure_id = $child_structure->id;
				$this->structural_stats_recurse();
			}

		}

		/*
		 * Having just finished a recursion, we can remove the last member of the ancestry array
		 * and eliminate one level of the depth tracking.
		 */
		$this->ancestry = array_slice($this->ancestry, 0, -1);
		$this->depth--;

	} // end structural_stats_recurse()


	/**
	 * Test whether the server environment is configured properly.
	 *
	 * Run a series of tests to determine whether the correct software is installed, permissions
	 * are correct, and settings are enabled.
	 *
	 * @throws Exception if the environment test fails
	 */
	public function test_environment()
	{

		/*
		 * Make sure that the PHP Data Objects extension is enabled.
		 */
		if (!defined('PDO::ATTR_DRIVER_NAME'))
		{
			$this->logger->message('PHP Data Objects (PDO) must be enabled', 10);
			$error = TRUE;
		}

		/*
		 * Make sure that PDO MySQL support exists.
		 */
		if (!in_array('mysql', PDO::getAvailableDrivers()))
		{
			$this->logger->message('PHP Data Objects (PDO) must have a MySQL driver enabled', 10);
			$error = TRUE;
		}

		/*
		 * Make sure that HTML Tidy is available within PHP or, failing that, at the command line.
		 */
		if (class_exists('tidy', FALSE) == FALSE)
		{

			/*
			 * A non-zero return status from a program called via exec() indicates an error.
			 */
			exec('which tidy', $result, $status);
			if ($status != 0)
			{
				$this->logger->message('HTML Tidy must be installed', 10);
				$error = TRUE;
			}

		}

		/*
		 * Make sure that php-xml is installed.
		 */
		if (extension_loaded('xml') == FALSE)
		{
			$this->logger->message('PHP’s XML extension must be installed and enabled', 10);
			$error = TRUE;
		}

		/*
		 * Make sure that zip is installed.
		 */
		exec('which zip', $result, $status);
		if ($status != 0)
		{
			$this->logger->message('zip must be installed', 10);
			$error = TRUE;
		}

		/*
		 * Make sure that the configuration file is writable.
		 */
		if (is_writable(INCLUDE_PATH . '/config.inc.php') !== TRUE)
		{
			$this->logger->message('config.inc.php must be writable by the server', 10);
			$error = TRUE;
		}

		/*
		 * Make sure that sitemap.xml is writable.
		 */
		if (is_writable(WEB_ROOT . '/sitemap.xml') !== TRUE)
		{
			$this->logger->message('sitemap.xml must be writable by the server', 3);
			$error = TRUE;
		}

		/*
		 * Make sure that .htaccess is writable.
		 */
		if (is_writable(WEB_ROOT . '/.htaccess') !== TRUE)
		{
			$this->logger->message('.htaccess must be writable by the server', 3);
			$error = TRUE;
		}

		/*
		 * If the Downloads directory exists, make sure that it's writable.
		 */
		if (is_writable(WEB_ROOT . '/downloads') !== TRUE)
		{
			$this->logger->message('The downloads directory (' . WEB_ROOT . '/downloads/'
				. ') must be writable by the server', 10);
			$error = TRUE;
		}

		// TODO: Fix me
		if(defined('SOLR_URL'))
		{
			/*
			 * Make sure that Solr is responsive.
			 */
			Solarium_Autoloader::register();
			$client = new Solarium_Client($GLOBALS['solr_config']);
			$ping = $client->createPing();
			try
			{
				$result = $client->ping($ping);
			}
			catch(Solarium_Exception $e)
			{
				$this->logger->message('Solr must be installed, configured in config.inc.php, and running', 10);
				$error = TRUE;
			}
		}

		if (isset($error))
		{
			return FALSE;
		}

		return TRUE;

	}

	/*
	 * Pass each of the laws to Solr to be indexed.
	 *
	 * This code indexes each XML file, one at a time, by POSTing them to Solr.
	 *
	 * Although all other Solr-based functionality on the site is built on the Solarium library,
	 * we do not use Solarium to index laws. That's because Solarium has no ability to post XML
	 * files to Solr <http://www.solarium-project.org/forums/topic/index-via-xml-files/>. So,
	 * instead, we do this via cURL.
	 */
	public function index_laws($args)
	{
		if(!isset($this->edition))
		{
			$edition_obj = new Edition(array('db' => $this->db));
			$this->set_edition($edition_obj->current());
		}

		if (!isset($this->edition))
		{
			throw new Exception('No edition, cannot index laws.');
		}

		if(!defined('SEARCH_CONFIG'))
		{
			$this->logger->message('Solr is not in use, skipping index', 9);
			return;
		}

		else
		{
			/*
			 * Index the laws.
			 */
			$this->logger->message('Updating search index', 5);

			$this->logger->message('Indexing laws', 6);

			$search_index = new SearchIndex(
				array(
					'config' => json_decode(SEARCH_CONFIG, TRUE)
				)
			);

			$law_obj = new Law(array('db' => $this->db));
			$result = $law_obj->get_all_laws($this->edition->id, true);

			$search_index->start_update();

			while($law = $result->fetch())
			{
				// Get the full data of the actual law.
				$document = new Law(array('db' => $this->db));
				$document->law_id = $law['id'];
				$document->config->get_all = TRUE;
				$document->get_law();
				// Bring over our edition info.
				$document->edition = $this->edition;

				try
				{
					$search_index->add_document($document);
				}
				catch (Exception $error)
				{
					$this->logger->message('Search index error "' . $error->getStatusMessage() .'"', 10);
					return FALSE;
				}
			}

			$search_index->commit();

			// $this->logger->message('Indexing structures', 6);
			### TODO: Index structures

			$this->logger->message('Laws were indexed', 5);

			return TRUE;

		}

	}

	public function clear_index($edition_id = null)
	{

		if (!defined('SOLR_URL'))
		{
			return TRUE;
		}

		if(isset($edition_id))
		{
			$sql = 'SELECT * FROM editions WHERE id = :edition_id';
			$sql_args = array(':edition_id' => 'edition');
			$statement = $this->db->prepare($sql);
			$result = $statement->execute($sql_args);
			if ($result === FALSE || $statement->rowCount() == 0)
			{
				throw new Exception('No such edition id:'. int($edition_id), E_USER_ERROR);
				return FALSE;
			}

			$edition = $statement->fetchColumn('name');

			$query = 'edition:' . $edition;

		}
		else
		{
			$query = '*:*';
		}
		$request = '<delete><query>' . $query . '</query></delete>';

		if ( !$this->handle_solr_request($request) )
		{
			return FALSE;
		}

		$request = '<optimize />';
		if ( !$this->handle_solr_request($request) )
		{
			return FALSE;
		}

		$this->logger->message('Solr cleared of all indexed laws', 5);

		$this->logger->message('Solr cleared of all indexed laws', 5);

		return TRUE;

	}

	protected function handle_solr_request($fields = array(), $multipart = false, $parameters = array())
	{

		$solr_update_url = SOLR_URL . 'update';

		/*
		 * Instruct Solr to return its response as JSON, and commit the change.
		 */

		$solr_parameters = array_merge($parameters, array(
				'wt' => 'json',
				'commit' => 'true'
				)
			);

		$url = $solr_update_url . '?' . http_build_query($solr_parameters);
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
		if($multipart)
		{
			curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: multipart/form; charset=US-ASCII') );
		}
		else
		{
			curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: text/xml; charset=US-ASCII') );
		}

		curl_setopt($ch, CURLOPT_POST, TRUE);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);

		/*
		 * Post this request to Solr via cURL, and save the response, which is provided as JSON.
		 */
		$response_json = curl_exec($ch);

		/*
		 * If cURL returned an error.
		 */
		if (curl_errno($ch) > 0)
		{
			$this->logger->message('The attempt to post files to Solr via cURL returned an '
				. 'error code, ' . curl_errno($ch) . ', from cURL—could not index laws', 10);
			return FALSE;
		}

		if ( (FALSE === $response_json) || !is_string($response_json) )
		{
			$this->logger->message('Could not connect to Solr', 10);
			return FALSE;
		}

		$response = json_decode($response_json);

		if ( ($response === FALSE) || empty($response) )
		{
			$this->logger->message('Solr returned invalid JSON', 8);
			return FALSE;
		}

		if (isset($response->error))
		{
			$this->logger->message('Solr returned the following unexpected error: '
				. print_r($response, true),  8);
			return FALSE;
		}

		return TRUE;
	}

	public function update_laws_references()
	{
		/*
		 * Crosslink laws_references. This needs to be done after the time of
		 * the creation of these references, because many of the references are
		 * at that time to not-yet-inserted sections.
		 */
		$this->logger->message('Updating laws_references', 3);

		/*
		 * Since section numbers may be duplicated, make this a many-to-one
		 * relationship.
		 */

		/*
		 * First, get our existing references.
		 */

		$existing_sql = 'SELECT * FROM laws_references
			WHERE edition_id = :edition_id';
		$existing_args = array(':edition_id' => $this->edition_id);
		$existing_statement =  $this->db->prepare($existing_sql);
		$existing_result = $existing_statement->execute($existing_args);

		/*
		 * Let's build a few queries we'll be using later. Do this outside the
		 * loop for better memory handling.
		 */
		$law_sql = 'SELECT laws.id FROM laws WHERE section = :section_number';
		$laws_statement = $this->db->prepare($law_sql);

		$update_sql = 'UPDATE laws_references SET target_law_id = :target_law_id
			WHERE target_section_number = :target_section_number AND
			edition_id = :edition_id';
		$update_statement = $this->db->prepare($update_sql);

		$insert_sql = 'INSERT INTO laws_references (law_id, target_section_number,
			target_law_id, mentions, date_created, edition_id) VALUES (:law_id,
			:target_section_number, :target_law_id, :mentions, :date_created,
			:edition_id) ON DUPLICATE KEY UPDATE mentions=mentions';
		$insert_statement = $this->db->prepare($insert_sql);

		/*
		 * We don't want anything weird to happen since we're messing with this
		 * table while iterating over it. So let's fetchAll for safety's sake,
		 * even if it's inefficient to keep all of this in memory.
		 */
		$laws_references = $existing_statement->fetchAll(PDO::FETCH_ASSOC);

		foreach($laws_references as $laws_reference)
		{
			$this->logger->message('Matching ' .
				$laws_reference['target_section_number'], 1);
			/*
			 * We may have many-to-one, so handle that.
			 */
			$laws_args = array(':section_number' =>
				$laws_reference['target_section_number']);
			$laws_result = $laws_statement->execute($laws_args);

			/*
			 * If we have precisely one record, we can just update in place.
			 */
			if($laws_statement->rowCount() == 1)
			{
				$law = $laws_statement->fetch(PDO::FETCH_ASSOC);

				$this->logger->message('Updating  ' .
					$laws_reference['target_section_number'] . ' with ' .
					$law['id'], 1);

				$update_args = array(
					':target_law_id' => $law['id'],
					':target_section_number' =>
						$laws_reference['target_section_number'],
					':edition_id' => $this->edition_id
				);
				$update_statement->execute($update_args);
			}

			/*
			 * If we have more than one, we must create new records.
			 */
			elseif($laws_statement->rowCount() > 1)
			{
				while($law = $laws_statement->fetch(PDO::FETCH_ASSOC))
				{
					$this->logger->message('Adding new records for ' .
						$laws_reference['target_section_number'] . ' with ' .
						$law['id'], 1);

					$insert_args = array(
						':law_id' => $laws_reference['law_id'],
						':target_section_number' =>
							$laws_reference['target_section_number'],
						':target_law_id' => $law['id'],
						':mentions' => $laws_reference['mentions'],
						':date_created' => $laws_reference['date_created'],
						':edition_id' => $this->edition_id
					);
					$insert_statement->execute($insert_args);
				}
			}

			/*
			 * Otherwise, we have no match - do nothing.
			 */
			else
			{
				$this->logger->message('No match for  ' .
					$laws_reference['target_section_number'], 1);
			}
		}

		/*
		 * Any unresolved target section numbers are spurious (strings that
		 * happen to match our section PCRE), and can be deleted.
		 */
		$this->logger->message('Deleting unresolved laws_references', 3);

		$sql = 'DELETE FROM laws_references WHERE target_law_id = :target_law_id
			AND edition_id = :edition_id';
		$sql_args = array(
			':target_law_id' => 0,
			':edition_id' => $this->edition_id

		);
		$statement = $this->db->prepare($sql);
		$result = $statement->execute($sql_args);
	}

}
