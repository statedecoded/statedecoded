<?php

/**
 * The 2013 State Decoded default theme
 *
 * PHP version 8
 *
 * @license		http://www.gnu.org/licenses/gpl.html GPL 3
 * @version		1.0
 * @link		https://www.statedecoded.com/
 * @since		0.8
 *
 */

class StateDecoded2013__Page extends Page
{
	public $theme_name = 'StateDecoded2013';

	public $assets = [
		'jquery_qtip_css' => [
			'path' => '/css/jquery.qtip.min.css',
			'type' => 'css',
			'requires' => ['jquery_qtip']
		],
		'main_css' => [
			'path' => '/css/application.css',
			'type' => 'css'
		],
		'jquery_ui_css' => [
			'path' => '/css/jquery-ui.css',
			'type' => 'css',
			'requires' => ['jquery_ui']
		],
		'jquery' => [
			'path' => '/js/vendor/jquery.min.js',
			'type' => 'javascript'
		],
		'jquery_ui' => [
			'path' => '/js/vendor/jquery-ui.min.js',
			'type' => 'javascript',
			'requires' => ['jquery']
		],
		'jquery_qtip' => [
			'path' => '/js/vendor/jquery.qtip.min.js',
			'type' => 'javascript',
			'requires' => ['jquery']
		],
		'mousetrap' => [
			'path' => '/js/vendor/mousetrap.min.js',
			'type' => 'javascript'
		],
		'tabs' => [
			'path' => '/js/vendor/tab.js',
			'type' => 'javascript',
			'requires' => ['jquery']
		],
		'favlaws' => [
			'path' => '/js/vendor/fav-laws.js',
			'type' => 'javascript',
			'requires' => ['jquery']
		],
		'main_js' => [
			'path' => '/js/vendor/functions.js',
			'type' => 'javascript',
			'requires' => ['jquery', 'mousetrap', 'jquery_qtip', 'jquery_qtip_css']
		]
	];

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
				$content->append('browser_title', ' - ' . SITE_TITLE);
			}
			else
			{
				$content->set('browser_title', SITE_TITLE);
			}
		}
		else
		{
			$content->append('browser_title', '&#8202;-&#8202;' . SITE_TITLE);
		}

		/*
		 * Include the place name (e.g., "Washington," "Texas," "United States").
		 */
		$content->set('place_name', PLACE_NAME);

		/*
		 * Get the edition data
		 */
		$search = new Search();

		// Since we don't have any conditions in our template, we have to build
		// html here.
		if(!$content->is_set('current_edition') && defined('EDITION_ID'))
		{
			$content->set('current_edition', EDITION_ID);
		}
		$content->set('edition_select',
			$search->build_edition( $content->get('current_edition') )
		);

		/*
		 * Set our search terms.
		 */
		$query = '';
		if(isset($_GET['q'])) {
			$query = $_GET['q'];
		}
		$content->set('search_terms', $query);

		/*
		 * If a Google Analytics Web Property ID has been provided, insert the tracking code.
		 */
		if (defined('GOOGLE_ANALYTICS_ID'))
		{

			$content->prepend('javascript',
				"(function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){
				(i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),
				m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)
				})(window,document,'script','//www.google-analytics.com/analytics.js','ga');

				ga('create', '" . GOOGLE_ANALYTICS_ID . "', 'auto');
				ga('send', 'pageview');");

		}

		/*
		 * If a Typekit ID has been provided, insert the JavaScript.
		 */
		if (defined('TYPEKIT_ID'))
		{
			$this->add_asset('typekit_js',
				[
					'path' => '//use.typekit.net/' .  TYPEKIT_ID . '.js',
					'type' => 'javascript'
				]
			);

			$content->append('javascript',
				'try{Typekit.load();}catch(e){};');
		}

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
		$javascripts = [];
		foreach($assets['javascript'] as $asset)
		{
			$javascripts[] = '<script src="' . $asset . '"></script>';
		}
		$content->set('javascript_files', implode("\n", $javascripts));

		/*
		 * Second, css includes.
		 */
		$stylesheets = [];
		foreach($assets['css'] as $asset)
		{
			$stylesheets[] = '<link rel="stylesheet" href="'.$asset.'" />';
		}

		$content->set('css', implode("\n", $stylesheets));
	}

}
