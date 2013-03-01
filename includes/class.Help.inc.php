<?php

/**
 * Help text and infrastructure
 *
 * All of the help text that drives the pop-up explanations throughout the website, and the methods
 * that convert and display that text.
 * 
 * PHP version 5
 *
 * @author		Waldo Jaquith <waldo at jaquith.org>
 * @copyright	2013 Waldo Jaquith
 * @license		http://www.gnu.org/licenses/gpl.html GPL 3
 * @version		0.7
 * @link		http://www.statedecoded.com/
 * @since		0.7
 *
 */

class Help
{
	
	function __construct()
	{
		
		/*
		 * Store all help text in $this->help.
		 */
		$this->help = new stdClass();
		
		
		/*
		 * Define the containers that will hold each category of help text.
		 */
		$this->help->sitewide = new stdClass();
		$this->help->law = new stdClass();
		$this->help->structure = new stdClass();
		$this->help->search = new stdClass();
		
		/*
		 * Sitewide help text.
		 */
		$this->help->sitewide->test = 'This is a test of help text';
		 
		 
		/*
		 * Law-specific help text.
		 */
		$this->help->law->test = 'This is a test of help text';
		
		
		/*
		 * Structure-specific help text.
		 */
		$this->help->structure->test = 'This is a test of help text';
		
		
		/*
		 * Search-specific help text.
		 */
		$this->help->search->test = 'This is a test of help text';
		
	}
	
	/**
	 * Retrieve all text relevant to a given 
	 */
	function get_text()
	{
	
		if (!isset($this->type))
		{
			return false;
		}
		
		/*
		 * A valid type of help text must be specified.
		 */
		if (
			($this->type != 'sitewide')
			&& ($this->type != 'law')
			&& ($this->type != 'structure')
		 	&& ($this->type != 'search')
		)
		{
			return false;
		}
		
		/*
		 * If a format has not been specified, return JSON.
		 */
		if (!isset($this->format))
		{
			$this->format = 'json';
		}
		
		/*
		 * Extract the requested type of help text.
		 */
		$this->text = $this->{$this->type};
		
		/*
		 * If JSON has been requested, then encode the data.
		 */
		if ($this->format == 'json')
		{
			$this->text = json_encode($this->text);
		}
		
		return true;
		
	}

}
