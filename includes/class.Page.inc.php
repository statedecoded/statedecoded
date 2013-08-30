<?php

/**
 * The Page class, for rendering HTML and delivering it to the browser
 *
 * PHP version 5
 *
 * @author		Waldo Jaquith <waldo at jaquith.org>
 * @copyright	2010-2013 Waldo Jaquith
 * @license		http://www.gnu.org/licenses/gpl.html GPL 3
 * @version		0.8
 * @link		http://www.statedecoded.com/
 * @since		0.1
 *
 * Since all rendering occurs in-class, you can override the rendering method
 * in your theme's Page class to use a totally different rendering engine (Twig,
 * Smarty, or straight PHP) if you'd like!
 *
 */

/**
 * Turn the variables provided by each page into a rendered page.
 */
class Page
{
	public $html;
	public $page = 'default';
	public $template_file = '';
	public $theme_name = '';
	public $theme_dir = '';


	public function __construct($page=null)
	{
		if(strlen($this->theme_name) === 0)
		{
			$this->theme_name = THEME_NAME;
		}
		if(strlen($this->theme_dir) === 0)
		{
			$this->theme_dir = THEME_DIR;
		}

		if (isset($page))
		{
			$this->page = $page;
		}
		$this->template_file = $this->theme_dir . $this->page . '.inc.php';

		$this->html = $this->load_template($this->template_file);
	}

	/**
	 * Get our template data
	 */
	function load_template($template_file)
	{
		/*
		 * Save the contents of the template file to a variable. First check APC and see if it's
		 * stored there.
		 */
		$storage_name = 'template-'.$this->theme_name.'-'.$this->page;
		if ( APC_RUNNING === TRUE)
		{
			$html = apc_fetch($storage_name);
			if ($html === FALSE)
			{


				if (check_file_available($template_file))
				{
					$html = file_get_contents($template_file);
				}

				apc_store($storage_name, $html);
			}
		}
		else
		{
			$html = file_get_contents($template_file);
		}

		return $html;
	}


	/**
	 * A shortcut for all steps necessary to turn variables into an output page.
	 */
	public function parse($content)
	{
		return $this->display($this->render($content));
	}


	/**
	 * Combine the populated variables with the template.
	 */
	public function render($content)
	{
		/*
		 * Make a copy of the template here, so we can re-render as often
		 * as we like with new content.
		 */
		$template = $this->html;

		$this->before_render($template, $content);

		/*
		 * Replace all of our in-page tokens with our defined variables.
		 */
		foreach ($content->get() as $field=>$value)
		{
			$template = str_replace('{{' . $field . '}}', $value, $template);
		}

		$this->after_render($template, $content);

		return $template;
	}


	/**
	 * Pre-rendering.
	 */
	public function before_render(&$template, &$content)
	{

	}


	/**
	 * Post-rendering.
	 */
	public function after_render(&$template, &$content)
	{
		/*
		 * Erase any unpopulated tokens that remain in our template.
		 */
		$template = preg_replace('/{{[0-9a-z_]+}}/', '', $template);

	}


	/**
	 * Send the page to the browser.
	 */
	function display($content)
	{

		if (!isset($content))
		{
			return FALSE;
		}

		echo $content;
		return TRUE;

	}
}
