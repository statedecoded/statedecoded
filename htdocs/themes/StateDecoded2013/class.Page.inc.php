<?php

/**
 * The 2013 State Decoded default theme
 *
 * PHP version 5
 *
 * @author		Bill Hunt <bill at krues8dr.com>
 * @copyright	2010-2013 Waldo Jaquith
 * @license		http://www.gnu.org/licenses/gpl.html GPL 3
 * @version		0.8
 * @link		http://www.statedecoded.com/
 * @since		0.8
 *
 */

class StateDecoded2013__Page extends Page
{
	public $theme_name = 'StateDecoded2013';

	/*
	 * We want to set a lot of defaults!
	 */
	public function before_render(&$template, &$content)
	{
		parent::before_render($template, $content);

		/*
		 * Create the browser title.
		 */
 		if (strlen($content->get('browser_title')) === 0)
		{
			if (strlen($content->get('page_title')) > 0)
			{
				$content->set('browser_title', $content->get('page_title'));
				$content->append('browser_title', '-' . SITE_TITLE);
			}
			else
			{
				$content->set('browser_title', SITE_TITLE);
			}
		}
		else
		{
			$content->append('browser_title', '—' . SITE_TITLE);
		}

		/*
		 * Include the place name (e.g., "Washington," "Texas," "United States").
		 */
		$content->set('place_name', PLACE_NAME);

		/*
		 * If a Google Analytics Web Property ID has been provided, insert the tracking code.
		 */
		if (defined('GOOGLE_ANALYTICS_ID'))
		{

			$content->set('google_analytics',
				  "var _gaq = _gaq || [];
				  _gaq.push(['_setAccount', '" . GOOGLE_ANALYTICS_ID . "']);
				  _gaq.push(['_trackPageview']);
				  (function() {
					var ga = document.createElement('script'); ga.type = 'text/javascript'; ga.async = true;
					ga.src = ('https:' == document.location.protocol ? 'https://ssl' : 'http://www') + '.google-analytics.com/ga.js';
					var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(ga, s);
				  })();");

		}

		/*
		 * If a Typekit ID has been provided, insert the JavaScript.
		 */
		if (defined('TYPEKIT_ID'))
		{
			$content->set('typekit',
				'<script src="//use.typekit.net/' .  TYPEKIT_ID . '.js"></script>
				<script >try{Typekit.load();}catch(e){}</script>');
		}
	}

	public function after_render(&$template, &$content)
	{
		parent::after_render($template, $content);

		/*
		 * Erase selected containers, if they're empty.
		 */
		$template = preg_replace('/<aside id="sidebar" class="secondary-content">(\s*)<\/aside>/m', '', $template);
		$template = preg_replace('/<nav id="intercode">(\s*)<\/nav>/m', '', $template);
		$template = preg_replace('/<nav id="breadcrumbs">(\s*)<\/nav>/m', '', $template);
	}
}