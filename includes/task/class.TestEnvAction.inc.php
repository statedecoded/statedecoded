<?php

require_once 'class.CliAction.inc.php';
require_once INCLUDE_PATH . 'class.ParserController.inc.php';


class TestEnvAction extends CliAction
{
	static public $name = 'test-env';
	static public $summary = 'Tests the local environment for requirements to run.';

	public function __construct($args = array())
	{
		parent::__construct($args);

		if(!isset($this->db))
		{
			$this->db = new Database( PDO_DSN, PDO_USERNAME, PDO_PASSWORD );
		}
	}

	public function execute($args = array())
	{
		$return_data = $this->runEnvironmentTests();

		return $this->formatOutput($return_data);
	}

	public function runEnvironmentTests()
	{
		$logger_args = array(
			'html' => false
		);
		$logger = new Logger($logger_args);
		$parser = new ParserController(array(
			'db' => &$this->db,
			'logger' => $logger,
			'import_data_dir' => IMPORT_DATA_DIR
		));
		if($parser->test_environment()) {
			return 'Environment test succeeded.';
		}
		else {
			$this->result = 1;
		}
	}

	public static function getHelp()
	{
		return <<<EOS
statedecoded : test-env

This command tests the environment to make sure that The State Decoded can run.
EOS;
	}
}
