<?php

class Logger {
	public function message($msg) {
		print $msg."\n";
	}
}

class DebugLogger extends Logger {
	public function __construct() {
		$this->start_time = $this->get_time();
	}
	public function message($msg) {
		print $this->get_time_elapsed() . "ms ";
		print memory_get_usage() . "b : ";
		print $msg . "\n";
	}

	public function get_time() {
		return microtime(true);
	}

	public function get_time_elapsed($time) {
		if(!$time) {
			$time = $this->get_time();
		}
		return $time - $this->start_time;
	}
}

?>