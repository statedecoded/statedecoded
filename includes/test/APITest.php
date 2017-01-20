<?php

/**
 * This suite of tests verifies API functionality.
 */

require_once './helper/class.TestDbHelper.inc.php';

class APITest extends PHPUnit_Framework_TestCase
{
	protected function setUp()
	{
		/* API uses global $db */
		global $db;

		if(!$db)
		{
			$options = array(PDO::ATTR_ERRMODE => PDO::ERRMODE_SILENT);
			$db = new Database( PDO_DSN, PDO_USERNAME, PDO_PASSWORD, $options );
		}

		$logger = new Logger();

		$parser = new ParserController(
			array(
				'logger' => $logger,
				'db' => &$db,
				'import_data_dir' => IMPORT_DATA_DIR));


		$this->dbHelper = new TestDbHelper(
			array(
				'db' => &$db,
				'logger' => $logger,
				'parser' => $parser));

		$this->dbHelper->setupDb();
	}

	protected function teardown()
	{
		$this->dbHelper->destroyDb();
	}

	/**
	 * API Class Tests
	 */

	public function testRegisterKey()
	{
		$api = new API();
		$api->suppress_activation_email = true;

		/* Tests complain undefined property unless we initialize here */
		$api->all_keys = new stdClass;

		/* Form data would be set through $_POST */
		$form = new stdClass;
		$form->name = 'John Doe';
		$form->email = 'jdoe@example.com';
		$form->url = 'www.example.com';

		$api->form = $form;

		$api->register_key();
		$api->list_all_keys();

		$this->assertEmpty((array) $api->all_keys,
			'Keys not set until activated');

		$api->activate_key();
		$api->list_all_keys();

		$this->assertNotEmpty((array) $api->all_keys,
			'Keys set once activated');
	}
}

