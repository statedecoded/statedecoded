<?php

/*
 * State Decoded Task Runner
 */

class TaskRunner
{
	public static function parse_action_info($action)
	{
		$obj = str_replace(' ', '', ucwords(str_replace('-', ' ', $action))). 'Action';
		$file = INCLUDE_PATH . 'task/class.' . $obj . '.inc.php';

		if(file_exists($file))
		{
			return array($obj, $file);
		}
		return false;
	}

	public static function filename_to_classname($filename)
	{
		$file = basename($filename);
		if(preg_match('/^class.([A-Za-z0-9]+).inc.php$/', $file, $matches))
		{
			return $matches[1];
		}
		return false;
	}

	public function execute($exec_args)
	{
		/*
		 * The first item is always the filename.
		 */
		array_shift($exec_args);

		// This will do for now, but really this should be wrapped in a class
		// and all actions should register themselves with that class.
		if(isset($exec_args) && count($exec_args) > 0)
		{
			$action = array_shift($exec_args);
			$args = $exec_args;

			if($action_info = TaskRunner::parse_action_info($action))
			{
				list($obj, $file) = $action_info;
			}

		}

		if(!isset($obj))
		{
			$action = 'help';

			if($action_info = TaskRunner::parse_action_info($action))
			{
				list($obj, $file) = $action_info;
			}

			$args = array();
		}

		require_once($file);

		$action = new $obj();

		return $action->execute($args);
	}
}
