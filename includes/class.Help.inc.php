<?php

/**
 * Help text and infrastructure
 *
 * All of the help text that drives the pop-up explanations throughout the website, and the methods
 * that convert and display that text.
 *
 * PHP version 5
 *
 * @license		http://www.gnu.org/licenses/gpl.html GPL 3
 * @version		0.8
 * @link		http://www.statedecoded.com/
 * @since		0.7
 *
 */

class Help extends ContentData
{

	/*
	 * Specify the help content to include
	 */
	function __construct()
	{
		parent::__construct('help');
	}

}
