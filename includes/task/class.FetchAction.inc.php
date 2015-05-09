<?php

require_once 'class.CliAction.inc.php';

class FetchAction extends CliAction
{
	static public $name = 'fetch';
	static public $summary = "Fetch data from a remote host.";

	public $default_options = array(
		'tmp' => '/tmp/statedecoded/',
		'ftype' => 'xml',
		'extra-args' => '-nd'
	);

	public function __construct($args)
	{
		/*
		 * Note: PHP can't use constants as class defaults,
		 * so we cannot set these in $default_options above.
		 */
		if(defined('IMPORT_DATA_DIR'))
		{
			$this->default_options['d'] = IMPORT_DATA_DIR;
		}
		if(defined('DATA_REMOTE_USER'))
		{
			$this->default_options['u'] = DATA_REMOTE_USER;
		}
		if(defined('DATA_REMOTE_PASSWORD'))
		{
			$this->default_options['p'] = DATA_REMOTE_PASSWORD;
		}
		if(defined('DATA_REMOTE_HOST'))
		{
			$this->default_options['h'] = DATA_REMOTE_HOST;
		}
		if(defined('DATA_REMOTE_PATH'))
		{
			$this->default_options['P'] = DATA_REMOTE_PATH;
		}

		parent::__construct($args);

		if(!file_exists($this->options['d']))
		{
			throw new Exception('Cannot find destination directory "' .
				$this->options['d'] . '"', E_USER_ERROR);
		}
	}

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

var_dump($this->options['extra-args']);
			$cmd = 'wget -r ' . $this->options['extra-args'] .
				' ftp://' . $this->options['u'] . ':' .
				$this->options['p'] . '@' . $this->options['h'] .
				':' . $this->options['P'] . ' -P ' . $this->options['tmp'] .
				' -A ' . $this->options['ftype'];

			print "Downloading files.\n";
			print $cmd;
			passthru($cmd, $result);

			if($result !== 0)
			{
				throw new Exception('Could not download files.', E_ERROR);
			}

			print "Downloading complete.\n";

			print "Removing old data files from \"" .
				$this->options['d'] . "\"\n";

			exec('rm -R ' . $this->options['d'] . '*');

			print "Moving data to \"" .
				$this->options['d'] . "\"\n";

			exec('mv ' . $this->options['tmp'] . '* ' .
				$this->options['d']);

			print "Done.\n\n";

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

  -P=directory
    Specify a remote path.  Defaults to DATA_REMOTE_PATH

  -d=directory
    Specify a local path to save files to.  Files are always save
    to the temp directory first and then are moved to this location
    upon success.  This should probably match whatever is set for
    IMPORT_DATA_DIR in your config file.
    Defaults to IMPORT_DATA_DIR

  --tmp=directory
    Specify a temporary directory to store files in.  Defaults
    to /tmp/statedecoded/

  --ftype=filetype
    Only download files of the matching type.  Defaults to xml
    See wget -A documentation for details.

  --extra-args
    Extra arguments to pass to wget.  Defaults to '-nd'
    (no directories).  You may prefer to use '-nH' and/or
    '--cut-dirs=#' to remove other directories.

EOS;

	}

}
