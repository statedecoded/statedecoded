<?php

/**
 * BaseController class
 *
 * Base for controllers.  Abstract only.
 *
 * PHP version 5
 *
 * @license		http://www.gnu.org/licenses/gpl.html GPL 3
 * @version		1.0
 * @link		http://www.statedecoded.com/
 * @since		0.8
 */

abstract class BaseController
{
	protected $template;
	protected $local;
	protected $content;

	public function __construct($local = array())
	{
		/*
		 * Store variables that need to be globally available.
		 */
		$this->local = $local;

		/**
		 * Fire up our templating engine.
		 */
		$this->template = Template::create();

		$this->content = new Content();
	}

	public function setContent($field, $value)
	{
		return $this->content->set($field, $value);
	}

	public function renderContent()
	{
		return $this->render($this->content);
	}

	/**
	 * Render the template.
	 */
	public function render($content)
	{
		/*
		 * Put our local variables into the local scope.
		 */
		extract($this->local);
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
