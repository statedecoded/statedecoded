<?php

require_once 'class.CliAction.inc.php';

class FetchAction extends CliAction
{
	static public $name = 'fetch';
	static public $summary = "Fetch data from a remote host.";

	public $default_options = array(
		'P' => '/downloads/last-import/',
		'tmp' => '/tmp/statedecoded/',
		'ftype' => 'xml'
	);

	public function execute($args = array())
	{
		try
		{
			if(!file_exists($this->options['tmp']))
			{
				print 'Making temporary directory "' .
					$this->options['tmp'] . "\"\n";
				mkdir($this->options['tmp'], 0755);
			}


			print 'Removing old tmp directory contents from "' .
				$this->options['tmp'] . "\"\n";

			exec('rm -R ' . $this->options['tmp'] . '*');


			$cmd = 'wget -r -nd ftp://' . DATA_REMOTE_USER . ':' .
				DATA_REMOTE_PASSWORD . '@' . DATA_REMOTE_HOST .
				':' . DATA_REMOTE_PATH . ' -P ' . $this->options['tmp'] .
				' -A ' . $this->options['ftype'];

			print "Downloading files.\n\n";
			//print $cmd;
			passthru($cmd);
		}
		catch(Exception $e)
		{
			print 'Error: ' . $e->getMessage();
		}
	}

	public static function getHelp($args = array()) {
		return <<<EOS
statedecoded : fetch

This action fetches data from a remote server, using the info
specified in the config file (DATA_REMOTE_USER, DATA_REMOTE_PASSWORD,
DATA_REMOTE_HOST, DATA_REMOTE_PATH)

This action requires wget to work.

Usage:

  statedecoded fetch [-u=username] [-p=password] [-h=hostname]
    [-d=/remote/path]

Available options:

  -u=username
    Specify a username.  Defaults to DATA_REMOTE_USER

  -p=password
    Specify a password.  Defaults to DATA_REMOTE_PASSWORD

  -h=hostname
    Specify a host.  Defaults to DATA_REMOTE_HOST

  -d=directory
    Specify a remote path.  Default sto DATA_REMOTE_PATH

  -P=directory
  	Specify a local path to save files to, relative to WEB_ROOT.
  	Files are always save to the temp directory first and then
  	are moved to this location upon success.  This should probably
  	match whatever is set for IMPORT_DATA_DIR in your config file.
  	Defaults to downloads/last-import/

  --tmp=directory
  	Specify a temporary directory to store files in.  Defaults
  	to /tmp/statedecoded/

  --ftype=filetype
  	Only download files of the matching type.  Defaults to xml
  	See wget -A documentation for details.

EOS;

	}

}
