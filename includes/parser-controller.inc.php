<?php

require_once INCLUDE_PATH . '/logger.inc.php';

class ParserController {
	public function __construct($args) {
		/**
		 * Set our defaults
		 */
		foreach($args as $key=>$value) {
			$this->$key = $value;
		}

		/**
		 * Setup a logger.
		 */
		$this->init_logger();

		/*
		 * Connect to the database.
		 */
		$this->db =& MDB2::connect(MYSQL_DSN);
		if (PEAR::isError($this->db))
		{
			die('Could not connect to the database.');
		}

		/*
		 * We must, must, must always connect with UTF-8.
		 */
		$this->db->setCharset('utf8');

		/**
		 * Set our default execution limits
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
	public function init_logger() {
		if(!$this->logger) {
			$this->logger = new Logger();
		}
	}

	public function set_execution_limits() {
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
	 * Clear out our
	 */
	public function clear_db() {
		$tables = array('dictionary', 'laws', 'laws_references', 'text', 'laws_views', 'text_sections');
		foreach ($tables as $table)
		{
			$sql = 'TRUNCATE '.$table;
			# Execute the query.
			$result =& $this->db->exec($sql);
			if (PEAR::isError($result))
			{
				$this->logger->message("Error in SQL: $sql", 10);
				die($result->getMessage());
			}
			$this->logger->message('Deleted '.$table, 5);
		}

		# Reset the auto-increment counter, to avoid unreasonably large numbers.
		$sql = 'ALTER TABLE structure
				AUTO_INCREMENT=1';
		$result =& $this->db->exec($sql);

		/*
		 * Delete law histories.
		 */
		$sql = 'DELETE FROM laws_meta
				WHERE meta_key = "history"';
		$result =& $this->db->exec($sql);
	}

	public function parse() {
		$this->logger->message('Parsing', 5);

		/*
		 * Create a new instance of Parser.
		 */
		$parser = new Parser(
			array(
				# Tell the parser what the working directory
				# should be for the XML files.
				'directory' => WEB_ROOT . '/xml/',

				# Set the database
				'db' => $this->db
			)
		);

		/*
		 * Iterate through the files.
		 */
		$this->logger->message('Parsing XML', 3);

		while ($section = $parser->iterate($starttime))
		{
			$parser->section = $section;
			$parser->parse();
			$parser->store();
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
				WHERE target_law_id = 0';
		$this->db->exec($sql);

		/*
		 * Any unresolved target section numbers are spurious (strings that happen to match our section
		 * PCRE), and can be deleted.
		 */
		$this->logger->message('Deleting unresolved laws_references', 3);

		$sql = 'DELETE FROM laws_references
				WHERE target_law_id = 0';
		$this->db->exec($sql);

		/*
		 * Break up law histories into their components and save those.
		 */
		$this->logger->message('Breaking up law histories', 3);

		$sql = 'SELECT id, history
				FROM laws';
		$result =& $this->db->query($sql);
		if ($result->numRows() > 0)
		{

			/*
			 * Step through the history of every law that we have a record of.
			 */

			while ($law = $result->fetchRow(MDB2_FETCHMODE_OBJECT))
			{
				/*
				 * Turn the string of text that comprises the history into an object of atomic history
				 * history data.
				 */
				$parser->history = $law->history;
				$history = $parser->extract_history();

				/*
				 * Save this object to the metadata table pair.
				 */
				$sql = 'INSERT INTO laws_meta
						SET law_id='.$this->db->escape($law->id).',
						meta_key="history", meta_value="'.$this->db->escape(serialize($history)).'",
						date_created=now();';
				$this->db->exec($sql);
			}

		}

		/*
		 * If we already have a view, replace it with this new one.
		 */
		$this->logger->message('Replace old view', 3);

		$sql = 'DROP VIEW IF EXISTS structure_unified';
		$this->db->exec($sql);

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
					s'.$i.'.number AS s'.$i.'_number, s'.$i.'.label AS s'.$i.'_label';
			$from[] = 's'.$i;
			$order[] = 's'.$i.'.number';
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

		$this->db->exec($sql);

		$this->logger->message('Done', 5);
	}

	public function write_api_key() {

		$this->logger->message('Writing API key', 5);

		/**
		 * If the site's internal API key is undefined, register a new key and activate it.
		 */
		if (API_KEY == '')
		{
			// I have no idea why __autoload() isn't loading this automatically, but it's not.
			include '../../includes/class.API.inc.php';
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
					automatically. Please edit that file and set the value of <code>API_KEY</code> to
					'.$api->key, 10);
			}


			$this->logger->message('Done', 5);
		}
		else
		{
			$this->logger->message('Nothing to do', 5);
		}
	}

	public function export() {

		$this->logger->message('Preparing zip exports', 5);

		/*
		 * Prepare exports
		 */

		# Define the location of the downloads directory.
		$downloads_dir = WEB_ROOT.'/downloads/';

		if (is_writable($downloads_dir) === false)
		{
			$this->logger->message('Error: '.$downloads_dir.' could not be written to, so bulk download files could
				not be exported.', 10);
		}

		else
		{
			/*
			 * Get a listing of all laws, to be exported as JSON.
			 */
			$this->logger->message('Querying laws', 3);

			$sql = 'SELECT laws.section, laws.catch_line, laws.catch_line, laws.text, laws.history,
					structure_unified.*
					FROM laws
					LEFT JOIN structure_unified
						ON laws.structure_id=structure_unified.s1_id
					WHERE edition_id='.EDITION_ID.'
					ORDER BY order_by ASC';
			$result =& $this->db->query($sql);
			if ($result->numRows() > 0)
			{

				# Create a new ZIP file object.
				$zip = new ZipArchive();
				$filename = $downloads_dir.'code.json.zip';

				if (file_exists($filename))
				{
					unlink($filename);
				}

				# If we cannot create a new ZIP file, bail.
				if ($zip->open($filename, ZIPARCHIVE::CREATE) !== TRUE)
				{
					$this->logger->message('Cannot open '.$filename.' to create a new ZIP file.', 10);
				}
				else
				{
					# Establish the depth of this code's structure. Though this constant includes the laws
					# themselves, we don't subtract 1 from the tally because the structural labels start at 1.
					$structure_depth = count(explode(',', STRUCTURE));

					# Iterate through every law.
					while ($law = $result->fetchRow(MDB2_FETCHMODE_OBJECT))
					{

						# We don't need either of these fields.
						unset($law->s1_id);
						unset($law->s2_id);

						# Rename the structural fields.
						for ($i=1; $i<$structure_depth; $i++)
						{
							# Assign these variables to new locations.
							$law->structure->{$i-1}->label = $law->{'s'.$i.'_label'};
							$law->structure->{$i-1}->name = $law->{'s'.$i.'_name'};
							$law->structure->{$i-1}->number = $law->{'s'.$i.'_number'};

							# Unset the old variables.
							unset($law->{'s'.$i.'_label'});
							unset($law->{'s'.$i.'_name'});
							unset($law->{'s'.$i.'_number'});
						}

						# Reverse the order of the structure, from broadest to most narrow.
						$law->structure = array_reverse((array) $law->structure);

						# Renumber the structure. To avoid duplicates, we must do this awkwardly.
						$tmp = $law->structure;
						unset($law->structure);
						$i=0;
						foreach ($tmp as $structure)
						{
							$law->structure->$i = $structure;
							$i++;
						}

						# Add this law to our ZIP archive, creating a pseudofile to do so. Eliminate colons
						# from section numbers, since Windows can't handle colons in filenames.
						$zip->addFromString(str_replace(':', '_', $law->section).'.json', json_encode($law));
					}

					# Close out our ZIP file.
					$zip->close();
				}
			}


			/*
			 * Save dictionary as JSON.
			 */
			$this->logger->message('Building dictionary', 3);

			$sql = 'SELECT laws.section, dictionary.term, dictionary.definition, dictionary.scope
					FROM dictionary
					LEFT JOIN laws ON dictionary.law_id = laws.id
					ORDER BY dictionary.term ASC , laws.order_by ASC';
			$result =& $this->db->query($sql);
			if ($result->numRows() > 0)
			{
				# Retrieve the entire dictionary as a single object.
				$dictionary = $result->fetchAll(MDB2_FETCHMODE_OBJECT);

				# Define the filename for our dictionary.
				$filename = $downloads_dir.'dictionary.json.zip';

				# Create a new ZIP file object.
				$zip = new ZipArchive();

				if (file_exists($filename))
				{
					unlink($filename);
				}

				# If we cannot create a new ZIP file, bail.
				if ($zip->open($filename, ZIPARCHIVE::CREATE) !== TRUE)
				{
					$this->logger->message('Cannot open '.$filename.' to create a new ZIP file.', 10);
				}
				else
				{

					# Add this law to our ZIP archive.
					$zip->addFromString('dictionary.json', json_encode($dictionary));

					# Close out our ZIP file.
					$zip->close();
				}
			}

		}

		$this->logger->message('Done', 5);
	}

	public function clear_apc() {
		# If APC exists on this server, clear everything in the user space. That consists of information
		# that the State Decoded has stored in APC, which is now suspect, as a result of having reloaded
		# the laws.
		if (extension_loaded('apc') && ini_get('apc.enabled') == 1)
		{
			$this->logger->message('Clearing APC cache', 5);

			apc_clear_cache('user');

			$this->logger->message('Done', 5);
		}
	}
}

?>