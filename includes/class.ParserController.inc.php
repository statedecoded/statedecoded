<?php

/**
 * Parser Controller
 *
 * PHP version 5
 *
 * @author		Bill Hunt <bill at krues8dr.com>
 * @copyright	2013 Bill Hunt
 * @license		http://www.gnu.org/licenses/gpl.html GPL 3
 * @version		0.7
 * @link		http://www.statedecoded.com/
 * @since		0.7
*/

class ParserController
{

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
		 * Connect to the database.
		 */
		$this->db = new PDO( PDO_DSN, PDO_USERNAME, PDO_PASSWORD, array(PDO::ATTR_ERRMODE => PDO::ERRMODE_SILENT) );
		if ($this->db === FALSE)
		{
			die('Could not connect to the database.');
		}

		/*
		 * Prior to PHP v5.3.6, the PDO does not pass along to MySQL the DSN charset configuration
		 * option, and it must be done manually.
		 */
		if (version_compare(PHP_VERSION, '5.3.6', '<'))
		{
			$db->exec("SET NAMES utf8");
		}

		/*
		 * Set our default execution limits.
		 */
		$this->set_execution_limits();

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
		if (!$this->logger)
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
		ini_set('memory_limit', '128M');
	}

	/**
	 * Populate the database
	 */
	public function populate_db()
	{

		/*
		 * To see if the database tables exist, just issue a query to the laws table.
		 */
		$sql = 'SELECT 1
				FROM laws
				LIMIT 1';
		$statement = $this->db->prepare($sql);
		$result = $statement->execute();

		if ($result !== FALSE)
		{
			return TRUE;
		}

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
		 * Load the MySQL import file into MySQL.
		 */
		$sql = file_get_contents(WEB_ROOT . '/admin/statedecoded.sql');

		$statement = $this->db->prepare($sql);
		$result = $statement->execute();
		if ($result === FALSE)
		{
			return FALSE;
		}

		return TRUE;

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

		$this->logger->message('Adding an inaugural editions record to the database', 5);

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
			$this->logger->message('Could not add an editions record to the database', 5);
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

	public function handle_editions($post_data)
	{
	
		$errors = array();

		if ($post_data['edition_option'] == 'new')
		{
		
			$create_data = array();

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

		return $errors;
	}
	
	
	/**
	 * Create a new edition.
	 */
	public function create_edition($edition = array())
	{
	
		if (!isset($edition['order_by']))
		{
			$sql = 'SELECT MAX(order_by) AS order_by
					FROM editions
					ORDER BY order_by';
			$statement = $this->db->prepare($sql);
			$result = $statement->execute();
			if ($result !== FALSE && $statement->rowCount() > 0)
			{
				$edition_row = $statement->fetch(PDO::FETCH_ASSOC);
				$edition['order_by'] = (int) $edition_row['order_by'] + 1;
			}
		}

		if (!isset($edition['order_by']))
		{
			$edition['order_by'] = 1;
		}

		/*
		 * If we have a new current edition, make the older ones not current.
		 */
		if ($edition['current'] == 1)
		{
			$sql = 'UPDATE editions
					SET current = 0';
			$statement = $this->db->prepare($sql);
			$result = $statement->execute();
		}

		$sql = 'INSERT INTO editions SET
				name=:name,
				slug=:slug,
				current=:current,
				order_by=:order_by';
		$statement = $this->db->prepare($sql);

		$sql_args = array(
			':name' => $edition['name'],
			':slug' => $edition['slug'],
			':current' => $edition['current'],
			':order_by' => $edition['order_by']
		);
		$result = $statement->execute($sql_args);

		if ($result !== FALSE)
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
					$htaccess .= PHP_EOL . PHP_EOL . 'SetEnv EDITION_ID ' . $this->db->lastInsertId() . PHP_EOL;
				}
				else
				{
					$htaccess = preg_replace('/SetEnv EDITION_ID (\d+)/', 'SetEnv EDITION_ID ' . $this->db->lastInsertId(), $htaccess);
				}
				$result = file_put_contents(WEB_ROOT . '/.htaccess', $htaccess);
				
			}
			
			/*
			 * Store the edition ID as a constant, so that we can use it elsewhere in the import
			 * process.
			 */
			define('EDITION_ID', $this->db->lastInsertId());
			
			return $this->db->lastInsertId();
			
		}
		else
		{
			return FALSE;
		}
		
	}

	/**
	 * Clear out our database.
	 */
	public function clear_db()
	{

		$this->logger->message('Clearing out the database', 5);

		$tables = array('dictionary', 'laws', 'laws_references', 'text', 'laws_views',
			'tags', 'text_sections', 'structure', 'permalinks');
		foreach ($tables as $table)
		{
			// Note that we *cannot* prepare the table name as an argument here.
			// PDO doesn't work that way.
			$sql = 'TRUNCATE ' . $table;

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
		 * Reset the auto-increment counter, to avoid unreasonably large numbers.
		 */
		$sql = 'ALTER TABLE structure
				AUTO_INCREMENT=1';
		$statement = $this->db->prepare($sql);
		$result = $statement->execute();

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

	/**
	 * Remove law-view records greater than one year old.
	 */
	public function prune_views()
	{

		$this->logger->message('Pruning view records greater than one year old', 5);

		$sql = 'DELETE FROM
				laws_views
				WHERE DATEDIFF(now(), date) > :date_diff';
		$sql_args = array(
			':date_diff' => 365
		);
		$statement = $this->db->prepare($sql);
		$result = $statement->execute($sql_args);

		return TRUE;

	}

	/**
	 * Parse the provided legal code
	 */
	public function parse()
	{

		$this->logger->message('Importing', 5);

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
					'directory' => IMPORT_DATA_DIR,

					/*
					 * Set the database
					 */

					'db' => $this->db
				)
			);
			$parser->edition_id = $this->edition_id;

			/*
			 * Iterate through the files.
			 */
			$this->logger->message('Parsing data files', 3);

			while ($section = $parser->iterate())
			{
				$parser->section = $section;
				$parser->parse();
				$parser->store();
			}
			
		}
		catch(Exception $e)
		{
			$this->logger->message('ERROR: ' . $e->getMessage(), 10);
			return false;
		}

		/*
		 * Crosslink laws_references. This needs to be done after the time of the creation of these
		 * references, because many of the references are at that time to not-yet-inserted sections.
		 */
		$this->logger->message('Updating laws_references', 3);

		$sql = 'UPDATE laws_references
				SET target_law_id =
					(SELECT laws.id
					FROM laws
					WHERE section = laws_references.target_section_number)
				WHERE target_law_id = :target_law_id';
		$sql_args = array(
			':target_law_id' => 0
		);
		$statement = $this->db->prepare($sql);
		$result = $statement->execute($sql_args);


		/*
		 * Any unresolved target section numbers are spurious (strings that happen to match our
		 * section PCRE), and can be deleted.
		 */
		$this->logger->message('Deleting unresolved laws_references', 3);

		$sql = 'DELETE FROM laws_references
				WHERE target_law_id = :target_law_id';
		$sql_args = array(
			':target_law_id' => 0
		);
		$statement = $this->db->prepare($sql);
		$result = $statement->execute($sql_args);


		/*
		 * Break up law histories into their components and save those.
		 */
		$this->logger->message('Breaking up law histories', 3);

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
					date_created=now()';
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
						':meta_value' => serialize($history)
					);
					$result = $statement->execute($sql_args);
				}
			}

		}

		/*
		 * If we already have a view, replace it with this new one.
		 */
		$this->logger->message('Replace old view', 3);

		$sql = 'DROP VIEW IF EXISTS structure_unified';
		$statement = $this->db->prepare($sql);
		$result = $statement->execute();

		/*
		 * The depth of the structure is the number of entries in STRUCTURE, minus one.
		 */
		$structure_depth = count(explode(',', STRUCTURE))-1;

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
		 * We want to to order from highest to lowest, so flip around this array.
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

		// Again, nothing here that we can actually prepare.
		// Column names aren't allowed.
		$statement = $this->db->prepare($sql);
		$result = $statement->execute();

		$this->logger->message('Done', 5);

		return true;
	}

	/**
	 * Build the permalinks
	 */
	public function build_permalinks()
	{

		$this->logger->message('Building Permalinks', 5);

		/*
		 * Create a new instance of Parser.
		 */
		$parser = new Parser(
			array(
				/*
				 * Set the database
				 */
				'db' => $this->db
			)
		);

		$parser->build_permalinks();

	}

	/**
	 * Generate an API key and store it
	 *
	 * See if an API key needs to be created. If it does, create it in the database, and then write
	 * it to the config file.
	 */
	public function write_api_key()
	{

		$this->logger->message('Writing API key', 5);

		/*
		 * If the site's internal API key is undefined in the config file, register a new key and
		 * activate it.
		 */
		if (API_KEY == '')
		{

			$api = new API();
			$api->form->email = EMAIL_ADDRESS;
			$api->form->url = 'http://'.$_SERVER['SERVER_NAME'].'/';
			$api->suppress_activation_email = TRUE;
			$api->register_key();
			$api->activate_key();

			/*
			 * Add the API key to the config file, if it's writable. Otherwise, display it on the
			 * screen, along with instructions.
			 */
			$config_file = INCLUDE_PATH.'/config.inc.php';

			if (is_writable($config_file))
			{
				$config = file_get_contents($config_file);
				$config = str_replace("('API_KEY', '')", "('API_KEY', '".$api->key."')", $config);
				file_put_contents($config_file, $config);
			}
			else
			{
				$this->logger->message('Your includes/config.inc.php file could not be modified
					automatically. Please edit that file and set the value of <code>API_KEY</code>
					to ' . $api->key, 10);
			}

			$this->logger->message('Done', 5);

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
				$api->form->url = 'http://'.$_SERVER['SERVER_NAME'].'/';
				$api->suppress_activation_email = TRUE;
				$api->register_key();
				$api->activate_key();

				$this->logger->message('The API key in <code>config.inc.php</code> does not exist in
					the database. A new key, <code>' . $api->key . '</code>, has been registered,
					but you must add it to <code>config.inc.php</code> manually.', 5);

				return TRUE;

			}

			$this->logger->message('Using the existing API key', 5);

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

		$this->logger->message('Preparing to export bulk downloads', 5);

		/*
		 * Define the location of the downloads directory.
		 */
		$downloads_dir = WEB_ROOT . '/downloads/';

		/*
		 * If we cannot write files to the downloads directory, then we can't export anything.
		 */
		if (is_writable($downloads_dir) === FALSE)
		{
			$this->logger->message('Error: '.$downloads_dir.' could not be written to, so bulk
				download files could not be exported.', 10);
			return FALSE;
		}

		/*
		 * Get a listing of all laws, to be exported in various formats.
		 */
		$this->logger->message('Querying laws', 3);

		/*
		 * Only get section numbers. We them pass each one to the Laws class to get each law's
		 * data.
		 */
		$sql = 'SELECT laws.section AS number
				FROM laws
				WHERE edition_id = :edition_id
				ORDER BY order_by ASC';
		$sql_args = array(
			':edition_id' => EDITION_ID
		);
		
		$statement = $this->db->prepare($sql);
		$result = $statement->execute($sql_args);

		if ($result !== FALSE && $statement->rowCount() > 0)
		{
			
			/*
			 * Establish the path of our code JSON storage directory.
			 */
			$json_dir = $downloads_dir . 'code-json/';

			/*
			 * If the JSON directory doesn't exist, create it.
			 */
			if (!file_exists($json_dir))
			{
				mkdir($json_dir);
			}

			/*
			 * If we cannot write to the JSON directory, log an error.
			 */
			if (!is_writable($json_dir))
			{
				$this->logger->message('Cannot write to ' . $json_dir . ' to export files.', 10);
				break;
			}

			/*
			 * Set a flag telling us that we may write JSON.
			 */
			$write_json = TRUE;

			/*
			 * Establish the path of our code text storage directory.
			 */
			$text_dir = $downloads_dir . 'code-text/';

			/*
			 * If the text directory doesn't exist, create it.
			 */
			if (!file_exists($text_dir))
			{
				mkdir($text_dir);
			}
			
			/*
			 * If we cannot write to the text directory, log an error.
			 */
			if (!is_writable($text_dir))
			{
				$this->logger->message('Cannot open ' . $text_dir . ' to export files.', 10);
				break;
			}

			/*
			 * Set a flag telling us that we may write text.
			 */
			$write_text = TRUE;

			/*
			 * Create a new instance of the class that handles information about individual laws.
			 */
			$laws = new Law();

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
			 * Establish the depth of this code's structure. Though this constant includes
			 * the laws themselves, we don't subtract 1 from the tally because the
			 * structural labels start at 1.
			 */
			$structure_depth = count(explode(',', STRUCTURE));

			/*
			 * Iterate through every section number, to pass to the Laws class.
			 */
			while ($section = $statement->fetch(PDO::FETCH_OBJ))
			{

				/*
				 * Pass the requested section number to Law.
				 */
				$laws->section_number = $section->number;

				unset($law);

				/*
				 * Get a list of all of the basic information that we have about this section.
				 */
				$law = $laws->get_law();

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
						$this->logger->message('Could not write code JSON files', 9);
						break;
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
						$this->logger->message('Could not write code text files', 9);
						break;
					}
				}

			} // end the while() law iterator

			/*
			 * Zip up all of the JSON files into a single file. We do this via exec(), rather than
			 * PHP's ZIP extension, because doing it within PHP requires far too much memory. Using
			 * exec() is faster and more efficient.
			 */
			if ($write_json === TRUE)
			{
				$this->logger->message('Creating code JSON ZIP file', 3);
				$output = array();
				exec('cd ' . $downloads_dir . '; zip -9rq code.json.zip code-json');
			}

			/*
			 * Zip up all of the text into a single file.
			 */
			if ($write_text === TRUE)
			{
				$this->logger->message('Creating code text ZIP file', 3);
				$output = array();
				exec('cd ' . $downloads_dir . '; zip -9rq code.txt.zip code-text');
			}

		}


		/*
		 * Save dictionary as JSON.
		 */
		$this->logger->message('Building dictionary', 3);

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
			$filename = $downloads_dir . 'dictionary.json.zip';

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
		}

		$this->logger->message('Done', 5);
	}

	/**
	 * Create and save a sitemap.xml
	 *
	 * List every law in this legal code and create an XML file with an entry for every one of them.
	 */
	function generate_sitemap()
	{

		$this->logger->message('Generating sitemap.xml', 3);

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
			$this->logger->message('No laws could be found to export to the sitemap', 3);
			return FALSE;
		}
		
		/*
		 * Create a new XML file, using the sitemap.xml schema.
		 */
		$xml = new SimpleXMLElement('<xml/>');
		$urlset = $xml->addChild('urlset');
		$urlset->addAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');
		
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
			$url = $urlset->addChild('url');
			$url->addchild('loc', $law->url);
			$url->addchild('changefreq', 'monthly');

		}
		
		/*
		 * Save the resulting file.
		 */
		file_put_contents($sitemap_file, $xml->asXML());
		
		return TRUE;

	}

	/**
	 * Clear out the APC cache, if it exists
	 */
	public function clear_apc()
	{

		/*
		 * If APC exists on this server, clear everything in the user space. That consists of
		 * information that the State Decoded has stored in APC, which is now suspect, as a result
		 * of having reloaded the laws.
		 */
		if (extension_loaded('apc') && ini_get('apc.enabled') == 1)
		{
			$this->logger->message('Clearing APC cache', 5);

			apc_clear_cache('user');

			$this->logger->message('Done', 5);
		}
	}

	/**
	 * Generate statistics about structural units
	 *
	 * Iterate through every structure to determine how many ancestors that it has (either ancestor
	 * structural units or laws), and then store that data as serialized objects in the "structure"
	 * database table.
	 */
	function structural_stats_generate()
	{

		$this->logger->message('Generating structural statistics', 3);

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

		$code_structures = explode(',', STRUCTURE);

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

			$metadata = new stdClass();
			$metadata->child_laws = $structure->child_laws;
			$metadata->child_structures = $structure->child_structures;

			$sql_args = array(
				':metadata' => serialize($metadata),
				':structure_id' => $structure_id
			);
			$result = $statement->execute($sql_args);

		}

	} // end structural_stats_generate()


	/**
	 * Recurse through structural information
	 *
	 * A helper method for structural_stats_generate(); not intended to be called on its own.
	 */
	function structural_stats_recurse()
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
	function test_environment()
	{

		/*
		 * Make sure that the PHP Data Objects extension is enabled.
		 */
		if (!defined('PDO::ATTR_DRIVER_NAME'))
		{
			$this->logger->message('PHP Data Objects (PDO) must be enabled.', 10);
			$error = TRUE;
		}

		/*
		 * Make sure that PDO MySQL support exists.
		 */
		if (!in_array('mysql', PDO::getAvailableDrivers()))
		{
			$this->logger->message('PHP Data Objects (PDO) must have a MySQL driver enabled.', 10);
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
			exec('which tidy', $result);
			if ($result != 0)
			{
				$this->logger->message('HTML Tidy must be installed.', 10);
				$error = TRUE;
			}

		}

		/*
		 * Make sure that the configuration file is writable.
		 */
		if (is_writable(INCLUDE_PATH . '/config.inc.php') !== TRUE)
		{
			$this->logger->message('config.inc.php must be writable by the server.', 10);
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
				. ') must be writable by the server.', 10);
			$error = TRUE;
		}

		/*
		 * Make sure that mod_rewrite is loaded.
		 */
		if (in_array('mod_rewrite', apache_get_modules()) !== TRUE)
		{
			if (getenv('HTTP_MOD_REWRITE') != TRUE)
			{
				$this->logger->message('The web server’s mod_rewrite module must be installed and'
					. ' enabled for this host.', 10);
				$error = TRUE;
			}
		}

		/*
		 * Make sure that cURL is installed.
		 */
		if (function_exists('curl_version') === FALSE)
		{
			$this->logger->message('PHP must have cURL support enabled.', 10);
			$error = TRUE;
		}

		/*
		 * Make sure that RewriteRules are respected.
		 *
		 * To accomplish this, we use cURL to make a request to the server, using a test URL that
		 * we allow via an .htaccesss RewriteRule. If it fails, then we know that RewriteRules are
		 * being ignored.
		 */
		if (empty($_SERVER['HTTPS']))
		{
			$protocol = 'http://';
		}
		else
		{
			$protocol = 'https://';
		}
		$domain = $_SERVER['SERVER_NAME'];
		$path = '/index.php.test';
		$url = $protocol . $domain . ':' . $path;

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_HEADER, TRUE);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_exec($ch);
		$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		if ($http_code != 200)
		{
			$this->logger->message('The web server does not permit .htaccess files to contain'
				.' RewriteRules. This can be fixed via the AllowOverrides All directive.', 10);
			$error = TRUE;
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
	function index_laws()
	{
	
		/*
		 * Define the Solr URL to which the XML files will be posted.
		 */
		$solr_update_url = SOLR_URL . 'update';
		
		/*
		 * Generate a list of all of the XML files.
		 */
		$files = array();
		$path = WEB_ROOT . '/downloads/code-xml/';
		
		if (file_exists($path) && is_dir($path))
		{
			$directory = dir($path);
		}
		else
		{
			throw new Exception('XML output directory ' . $path . ' does not exist—'.
				. 'could not index laws with Solr.');
		}
		
		/*
		 * Create an array, $files, with a list of every XML file.
		 */
		$files = array();
		while (FALSE !== ($filename = $directory->read()))
		{
// SOMEHOW WE NEED TO OMIT ANY FILES THAT TIDY COULDN'T CLEAN UP.
			$file_path = $this->directory . $filename;
			if (is_file($file_path) && is_readable($file_path) && substr($filename, 0, 1) !== '.')
			{
				$files[] = $file_path;
			}
			
		}
		
		if (count($files) == 0)
		{
			throw new Exception('No XML files were found in ' . $path . '—could not index laws '
				.'with Solr.');
		}
		
		/*
		 * Post each of the files to Solr, in batches of 10,000.
		 */
		$file_count = count($files);
		$batch_size = 10000;
		for ($i = 0; $i < $file_count; $i+=$batch_size)
		{
		
			$file_slice = array_slice($files, $i, $batch_size);
			
			/*
			 * Instruct Solr to return its response as JSON, and to apply the specified XSL
			 * transformation on the provided XML files.
			 */
// WHAT'S THE PATH FOR THE XSL FILE? WHERE WILL WE KEEP IT?
			$queryParams = array('wt' => 'json', 
								 'tr' => 'stateDecodedXml.xsl');
			
			$numFiles = 0;
			$url = $this->fullUrl($queryParams);
			curl_setopt($this->ch, CURLOPT_URL, $url);
			curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, TRUE);
			curl_setopt($this->ch, CURLOPT_FOLLOWLOCATION, TRUE);
			curl_setopt($this->ch, CURLOPT_HTTPHEADER, 'Content-Type: multipart/form; charset=US-ASCII');
			$params = array();
			foreach ($files as $key=>$filename)
			{
				$params[$filename] = '@' . realpath($filename) . ';type=application/xml';
				++$numFiles;
			}
			curl_setopt($this->ch, CURLOPT_POSTFIELDS, $params);
			
			/*
			 * Post this request to Solr via cURL, and save the response, which is provided as JSON.
			 */
			$response_json = $this->handleResponse(curl_exec($this->ch));
			
			if (!is_string($response_json))
			{
				throw new Exception('Could not connect to Solr.');
			}
			
			$response = json_decode(response_json);
			
			if (empty($response))
			{
				throw new Exception('Solr returned invalid JSON.');
			}
			
			if (isset($response->error))
			{
				throw new Exception('Solr error: ' . $response->error);
			}
			
			
		}
	
	}

}
