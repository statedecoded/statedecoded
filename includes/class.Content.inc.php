<?php

/*
 * Reads a JSON file to load content.
 * Later, this could read from a database, if need be.
 */
class Content
{

	public $content;
	public $json;
	public $filename;
	
	/*
	 * Initialize our object
	 */
	public function __construct($type) 
	{
		/*
		 * Require something that resembles a filename.
		 */
		if (preg_match('/^([a-zA-Z0-9-_]+)$/', $type)) 
		{
			$this->filename = WEB_ROOT . '/content/' . $type . '.json';
			$this->json = file_get_contents($this->filename);
			$this->content = json_decode($this->json);
		}
	}
	
	/*
	 * Retrieve all text relevant to a given section
	 */
	public function get_text($section)
	{
		if (!$section)
		{
			return $this->content;
		}
		elseif (isset($this->content->$section))
		{
			return $this->content->$section;
		}
	}
}
