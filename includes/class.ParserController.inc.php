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
			$this->db->exec("SET NAMES utf8");
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
		 * Load the MySQL import file into MySQL. We don't prepare this query because PDO_MySQL
		 * didn't support multiple queries until PHP 5.3.
		 */
		$sql = file_get_contents(WEB_ROOT . '/admin/statedecoded.sql');
		$result = $this->db->exec($sql);
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

		if(isset($this->edition_id))
		{
			/*
			 * Get the edition from the database and store a copy locally.
			 */
			$edition_query = 'SELECT * FROM editions WHERE id = :edition_id';
			$edition_args = array(':edition_id' => $this->edition_id);

			$edition_statement = $this->db->prepare($edition_query);
			$edition_result = $edition_statement->execute($edition_args);

			if ($edition_result !== FALSE && $edition_statement->rowCount() > 0)
			{
				$this->edition = $edition_statement->fetch(PDO::FETCH_ASSOC);
			}
			else
			{
				$errors[] = 'The edition could not be found.';
			}
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
				order_by=:order_by,
				date_created=NOW(),
				date_modified=NOW()';
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

			/*
			 * Note that we *cannot* prepare the table name as an argument here.
			 * PDO doesn't work that way.
			 */
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
					'db' => $this->db,

					/*
					 * Set the edition
					 */
					 'edition_id' => $this->edition_id
				)
			);

			if(method_exists($parser, 'pre_parse'))
			{
				$parser->pre_parse();
			}

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

			if(method_exists($parser, 'post_parse'))
			{
				$parser->post_parse();
			}

			/*
			 * If any files contained invalid XML, bring that list into the local scope.
			 */
			if (isset($parser->invalid_xml))
			{
				$this->invalid_xml = $parser->invalid_xml;
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

		/*
		 * There's nothing here that we can actually prepare. Column names aren't allowed.
		 */
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

		if(!isset($this->edition) || !isset($this->edition['slug']))
		{
			$this->logger->message('Edition is missing!  Cannot write new files.', 10);
			throw new Exception('Edition is missing');
		}

		/*
		 * Blow away our old directory completely
		 */
		$this->logger->message('Removing old downloads folder.', 5);
		exec('cd ' . WEB_ROOT . '/downloads/; rm -R ' . $this->edition['slug']);

		/*
		 * If we cannot write files to the downloads directory, then we can't export anything.
		 */
		if (is_writable($downloads_dir) === FALSE)
		{
			$this->logger->message('Error: ' . $downloads_dir . ' could not be written to, so bulk
				download files could not be exported.', 10);
			return FALSE;
		}

		/*
		 * Add the proper structure for editions.
		 */
		$downloads_dir .= $this->edition['slug'] . '/';

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

		/*
		 * Zip up all of the XML into a single file.
		 */
		if ($write_text === TRUE)
		{
			$this->logger->message('Creating code XML ZIP file', 3);
			$output = array();
			exec('cd ' . $downloads_dir . '; zip -9rq code.xml.zip code-xml');
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

		$this->logger->message('Creating symlinks', 4);

		if($this->edition['current'] == '1')
		{
			exec('cd ' . WEB_ROOT . '/downloads/; rm current ; ln -s ' . $this->edition['slug'] . ' current');
		}

		$this->logger->message('Done generating exports', 5);

	}

	/**
	 * Export a single structure
	 */
	function export_structure($parent_id)
	{
		/*
		 * Define the location of the downloads directory.
		 */
		$downloads_dir = WEB_ROOT . '/downloads/';
		$downloads_dir .= $this->edition['slug'] . '/';


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
			 * This is slightly different from how we handle permalinks
			 * since we don't want to overwrite files if current has changed.
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
								AND edition_id = :edition_id
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
								AND edition_id = :edition_id
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
				$json_dir = $downloads_dir . 'code-json' . $url;

				/*
				 * If the JSON directory doesn't exist, create it.
				 */
				if (!file_exists($json_dir))
				{
					/*
					 * Build our directories recursively.
					 * Don't worry about the mode, as our server's umask should handle
					 * that for us.
					 */
					mkdir($json_dir, 0777, true);
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
				$text_dir = $downloads_dir . 'code-text' . $url;

				/*
				 * If the text directory doesn't exist, create it.
				 */
				if (!file_exists($text_dir))
				{
					mkdir($text_dir, 0777, true);
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
				 * Establish the path of our code XML storage directory.
				 */
				$xml_dir = $downloads_dir . 'code-xml' . $url;

				/*
				 * If the XML directory doesn't exist, create it.
				 */
				if (!file_exists($xml_dir))
				{
					mkdir($xml_dir, 0777, true);
				}

				/*
				 * If we cannot write to the text directory, log an error.
				 */
				if (!is_writable($xml_dir))
				{
					$this->logger->message('Cannot open ' . $xml_dir . ' to export files.', 10);
					break;
				}

				/*
				 * Set a flag telling us that we may write XML.
				 */
				$write_xml = TRUE;

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
				 * Iterate through every section number, to pass to the Laws class.
				 */
				while ($section = $laws_statement->fetch(PDO::FETCH_OBJ))
				{

					$this->logger->message('Writing '.$section->section_number, 3);

					/*
					 * Pass the requested section number to Law.
					 */
					$laws->section_number = $section->section_number;
					$laws->edition_id = $this->edition_id;

					unset($law, $section);

					/*
					 * Get a list of all of the basic information that we have about this section.
					 */
					$law = $laws->get_law();

					if($law)
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
								$this->logger->message('Could not write law JSON files', 9);
								break;
							}

						}

						/*
						 * Store the XML file.
						 */
						if ($write_xml === TRUE)
						{

							$xml = new SimpleXMLElement('<law />');
							object_to_xml($law, $xml);
							$dom = dom_import_simplexml($xml)->ownerDocument;
							$dom->formatOutput = true;

							$success = file_put_contents($xml_dir . $filename . '.xml', $xml->asXML());
							if ($success === FALSE)
							{
								$this->logger->message('Could not write law XML files', 9);
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
								$this->logger->message('Could not write law text files', 9);
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
			exec('which tidy', $result, $status);
			if ($status != 0)
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
			$this->logger->message('Solr must be installed, configured in config.inc.php, and running.', 10);
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
			$this->logger->message('XML output directory ' . $path . ' does not exist—could not '
				. 'index laws with Solr.', 10);
			return FALSE;
		}

		/*
		 * Create an array, $files, with a list of every XML file.
		 *
		 * We don't bother to check whether each file is readable because a) these files were just
		 * created by the exporter and b) it's really too slow on the order of tens or hundreds of
		 * thousands of files.
		 */
		$files = array();
		while (FALSE !== ($filename = $directory->read()))
		{

			$file_path = $path . $filename;
			if (substr($filename, 0, 1) !== '.')
			{
				$files[] = $file_path;
			}

		}

		if (count($files) == 0)
		{
			$this->logger->message('No files were found in ' . $path . '—could not index laws with Solr.', 10);
			return FALSE;
		}

		/*
		 * If we have a list of files with XML problems, then remove those from the list of files
		 * to import. If a single file in a batch has XML errors, then the entire batch is rejected,
		 * so it's better to omit a file than to risk that.
		 */
		if (isset($this->invalid_xml))
		{
			foreach ($this->invalid_xml as $entry)
			{
				$key = array_search($entry, $files);
				echo 'Removed ' . $files[$key] . ' for invalid XML.<br />';
				unset($files[$key]);
			}
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
			$solr_parameters = array(
				'wt' => 'json',
				'tr' => 'stateDecodedXml.xsl');

			$numFiles = 0;
			$url = $solr_update_url . '?' . http_build_query($solr_parameters);
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
			curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: multipart/form; charset=US-ASCII') );
			$params = array();
			foreach ($file_slice as $key=>$filename)
			{
				$params[$filename] = '@' . realpath($filename) . ';type=application/xml';
				++$numFiles;
			}
			curl_setopt($ch, CURLOPT_POST, TRUE);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $params);

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
					. 'error code, ' . curl_errno($ch) . ', from cURL. Could not index laws.', 10);
				return FALSE;
			}

			if ( (FALSE === $response_json) || !is_string($response_json) )
			{
				$this->logger->message('Could not connect to Solr.', 10);
				return FALSE;
			}

			$response = json_decode($response_json);

			if ( ($response === FALSE) || empty($response) )
			{
				$this->logger->message('Solr returned invalid JSON.', 8);
				return FALSE;
			}

			if (isset($response->error))
			{
				$this->logger->message('Solr error: ' . $response->error, 8);
				return FALSE;
			}

		} // end for() loop

		/*
		 * Files aren't searchable until Solr is told to commit them.
		 */
		$url = $solr_update_url . '?commit=true';

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $solr_update_url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		$results = curl_exec($ch);

		/*
		 * If cURL returned an error.
		 */
		if (curl_errno($ch) > 0)
		{
			$this->logger->message('The attempt to commit files to Solr via cURL returned an '
				. 'error code, ' . curl_errno($ch) . ', from cURL. Could not index laws.', 10);
			return FALSE;
		}

		$this->logger->message('Laws indexed with Solr successfully.', 7);

		return TRUE;

	}

}
