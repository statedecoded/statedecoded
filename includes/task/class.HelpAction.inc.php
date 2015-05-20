<?php

require_once 'class.CliAction.inc.php';


class HelpAction extends CliAction
{
	static public $name = 'help';
	static public $summary = 'Gives info about available commands.';

	public function execute($args = array())
	{

		if(isset($args[0]))
		{
			$return_data = $this->showHelp($args);
		}
		else
		{
			$return_data = $this->showDefaultHelp();
		}

		return $this->formatOutput($return_data);
	}

	public function showDefaultHelp()
	{
		$name_length = 0;

		$local_dir = dirname(__FILE__);
		$dh = dir($local_dir);

		while (false !== ($entry = $dh->read()))
		{
			if (is_file($local_dir . '/' . $entry))
			{
				if($class = TaskRunner::filenameToClassname($entry))
				{
					require_once($local_dir . '/' . $entry);

					$reflectClass = new ReflectionClass($class);

					if(!$reflectClass->isAbstract() &&
						!$reflectClass->isInterface())
					{

						$actions[$class::$name] = $class::$summary;
						if(strlen($class::$name) > $name_length){
							$name_length = strlen($class::$name);
						}
					}
				}
			}
		}
		$dh->close();

		$return_value = <<<EOS
statedecoded - task runner for the StateDecoded project.

Usage: php statedecoded [-c=/path/to/config/file] [-f|--format=formatname]
           <command> [arg1 arg2 ...]

Options:
  Note that all options *must* use = as a separator.

  -c
      Used to specify a config file.  By default, config.inc.php
       in the INCLUDE_PATH directory is used.

  --format
      Set the format of the returned text. Currently defaults to text.

Available commands:


EOS;

		foreach($actions as $name=>$summary)
		{
			$return_value .= "  " . str_pad($name, $name_length, ' ') . ' : '.$summary ."\n";
		}

		$return_value .= "\n\n";

		return $return_value;
	}

	public function showHelp($args = array())
	{
		if($action_info = TaskRunner::parseActionInfo($args[0]))
		{
			list($obj, $file) = $action_info;
		}
		if(isset($obj))
		{
			require_once($file);
			return $obj::getHelp();
		}

	}

	public static function getHelp()
	{
		return <<<EOS
statedecoded : help

It looks like you already know how to use help.
EOS;
	}
}
