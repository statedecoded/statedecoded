<?php

/**
 * BaseController class
 *
 * Base for controllers.  Abstract only.
 *
 * PHP version 5
 *
 * @author		Bill Hunt <bill at krues8dr dot com>
 * @copyright	2013 Bill Hunt
 * @license		http://www.gnu.org/licenses/gpl.html GPL 3
 * @version		0.8
 * @link		http://www.statedecoded.com/
 * @since		0.8
 */

abstract class BaseController
{
	protected $template;

	public function __construct()
	{
		/**
		 * Fire up our templating engine.
		 */
		$this->template = Template::create();
	}

	/**
	 * Render the template.
	 */
	public function render($content)
	{
		/*
		 * Parse the template, which is a shortcut for a few steps that culminate in sending the content
		 * to the browser.
		 */
		return $this->template->parse($content);
	}

	public function handleNotFound($content)
	{
		include ($_SERVER['DOCUMENT_ROOT'] . '/404.php');
	}
}
