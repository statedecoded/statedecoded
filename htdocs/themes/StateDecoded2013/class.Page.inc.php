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

	public $assets = array(
		'font_awesome_css' => array(
			'path' => '//netdna.bootstrapcdn.com/font-awesome/3.1.1/css/font-awesome.css',
			'type' => 'css'
		),
		'main_css' => array(
			'path' => '/css/application.css',
			'type' => 'css',
			'requires' => array('font_awesome_css')
		),
		'jquery_ui_css' => array(
			'path' => '//code.jquery.com/ui/1.10.3/themes/smoothness/jquery-ui.css',
			'type' => 'css',
			'requires' => array('jquery_ui')
		),
		'jquery' => array(
			'path' => '//ajax.googleapis.com/ajax/libs/jquery/1.8.3/jquery.min.js',
			'type' => 'javascript'
		),
		'jquery_ui' => array(
			'path' => '//ajax.googleapis.com/ajax/libs/jqueryui/1.8.2/jquery-ui.min.js',
			'type' => 'javascript',
			'requires' => array('jquery')
		),
		'jquery_qtip' => array(
			'path' => '//cdnjs.cloudflare.com/ajax/libs/qtip2/2.1.1/jquery.qtip.min.js',
			'type' => 'javascript',
			'requires' => array('jquery')
		),
		'modernizr' => array(
			'path' => '/js/vendor/modernizr.min.js',
			'type' => 'javascript'
		),
		'jquery_slideto' => array(
			'path' => '/js/vendor/jquery.slideto.min.js',
			'type' => 'javascript',
			'requires' => array('jquery')
		),
		'jquery_color' => array(
			'path' => '/js/vendor/jquery.color-2.1.1.min.js',
			'type' => 'javascript',
			'requires' => array('jquery')
		),
		'mousetrap' => array(
			'path' => '/js/vendor/mousetrap.min.js',
			'type' => 'javascript'
		),
		'jquery_zclip' => array(
			'path' => '/js/vendor/jquery.zclip.min.js',
			'type' => 'javascript',
			'requires' => array('jquery')
		),
		'polyfiller' => array(
			'path' => '/js/vendor/js-webshim/minified/polyfiller.js',
			'type' => 'javascript'
		),
		'masonry_js' => array(
			'path' => '/js/vendor/masonry.pkgd.min.js',
			'type' => 'javascript'
		),
		'main_js' => array(
			'path' => '/js/vendor/functions.js',
			'type' => 'javascript',
			'requires' => array('jquery', 'jquery_zclip', 'mousetrap', 'jquery_qtip')
		)
	);

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

			$content->append('javascript',
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
			$this->add_asset('typekit_js',
				array(
					'path' => '//use.typekit.net/' .  TYPEKIT_ID . '.js',
					'type' => 'javascript'
				)
			);

			$content->append('javascript',
				'try{Typekit.load();}catch(e){};');
		}

		$content->append('javascript',
			'var zclip_swf_file = "' . THEME_WEB_PATH . 'static/js/vendor/ZeroClipboard.swf";');

		/*
		 * Setup assets
		 */
		$this->render_assets($template, $content);
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

		/*
		 * Erase any unpopulated tokens that remain in our template.
		 */
		$template = preg_replace('/{{[0-9a-z_]+}}/', '', $template);
	}


	public function render_assets(&$template, &$content)
	{

		/*
		 * Setup assets
		 */
		$assets = $this->parse_assets();

		/*
		 * First, javascript includes.
		 */
		$javascripts = array();
		foreach($assets['javascript'] as $asset)
		{
			$javascripts[] = '<script src="' . $asset . '"></script>';
		}
		$content->set('javascript_files', join("\n", $javascripts));

		/*
		 * Second, css includes.
		 */
		$stylesheets = array();
		foreach($assets['css'] as $asset)
		{
			$stylesheets[] = '<link rel="stylesheet" href="'.$asset.'" />';
		}

		$content->set('css', join("\n", $stylesheets));
	}
}