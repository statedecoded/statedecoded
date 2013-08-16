<?php

/**
 * The Page class, for rendering HTML and delivering it to the browser
 *
 * PHP version 5
 *
 * @author		Waldo Jaquith <waldo at jaquith.org>
 * @copyright	2010-2013 Waldo Jaquith
 * @license		http://www.gnu.org/licenses/gpl.html GPL 3
 * @version		0.7
 * @link		http://www.statedecoded.com/
 * @since		0.1
 *
 */

/**
 * Turn the variables provided by each page into a rendered page.
 */
class Page
{

	/**
	 * A shortcut for all steps necessary to turn variables into an output page.
	 */
	function parse()
	{
		Page::render();
		Page::display();
	}


	/**
	 * Combine the populated variables with the template.
	 */
	function render()
	{

		/*
		 * Save the contents of the template file to a variable. First check APC and see if it's
		 * stored there.
		 */
		if ( APC_RUNNING === TRUE)
		{
			$this->html = apc_fetch('template-'.TEMPLATE);
			if ($this->html === FALSE)
			{
				$this->html = file_get_contents(INCLUDE_PATH . '/templates/' . TEMPLATE . '.inc.php');
				apc_store('template-'.TEMPLATE, $this->html);
			}
		}
		else
		{
			$this->html = file_get_contents(INCLUDE_PATH . '/templates/' . TEMPLATE . '.inc.php');
		}

		/*
		 * Create the browser title.
		 */
		if (empty($this->field->browser_title))
		{
			if (!empty($this->field->page_title))
			{
				$this->field->browser_title .= $this->field->page_title;
				$this->field->browser_title .= '—' . SITE_TITLE;
			}
			else
			{
				$this->field->browser_title .= SITE_TITLE;
			}
		}
		else
		{
			$this->field->browser_title .= '—' . SITE_TITLE;
		}

		/*
		 * Include the place name (e.g., "Washington," "Texas," "United States").
		 */
		$this->field->place_name = PLACE_NAME;

		/*
		 * If a Google Analytics Web Property ID has been provided, insert the tracking code.
		 */
		if (defined('GOOGLE_ANALYTICS_ID'))
		{

			$this->field->google_analytics =
				  "var _gaq = _gaq || [];
				  _gaq.push(['_setAccount', '" . GOOGLE_ANALYTICS_ID . "']);
				  _gaq.push(['_trackPageview']);
				  (function() {
					var ga = document.createElement('script'); ga.type = 'text/javascript'; ga.async = true;
					ga.src = ('https:' == document.location.protocol ? 'https://ssl' : 'http://www') + '.google-analytics.com/ga.js';
					var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(ga, s);
				  })();";

		}


		/*
		 * Replace all of our in-page tokens with our defined variables.
		 */
		foreach ($this->field as $field=>$contents)
		{
			$this->html = str_replace('{{' . $field . '}}', $contents, $this->html);
		}

		/*
		 * Erase any unpopulated tokens that remain in our template.
		 */
		$this->html = preg_replace('/{{[0-9a-z_]+}}/', '', $this->html);

		/*
		 * Erase selected containers, if they're empty.
		 */
		$this->html = preg_replace('/<section id="sidebar">(\s*)<\/section>/', '', $this->html);
		$this->html = preg_replace('/<nav id="intercode">(\s*)<\/nav>/', '', $this->html);
		$this->html = preg_replace('/<nav id="breadcrumbs">(\s*)<\/nav>/', '', $this->html);
	}

	/**
	 * Send the page to the browser.
	 */
	function display()
	{

		if (!isset($this->html))
		{
			return FALSE;
		}

		echo $this->html;
		return TRUE;

	}
}
