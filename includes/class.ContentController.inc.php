<?php 

/**
 * ContentController class
 *
 * Handles mostly static content
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

class ContentController extends BaseController
{

	/**
	 * ContentController::default()
	 *
	 * The 404 page.
	 */
	public function notFound($args)
	{
	
		$body = '<h1>Content Not Found</h1>
			<p>We were unable to find the content you requested.</p>';
	
		$this->setContent('body', $body);
		
		return $this->renderContent();
		
	}
	
	/**
	 * ContentController::index()
	 *
	 * The Home page
	 */
	public function index($args)
	{
	
		$this->setContent('browser_title', SITE_TITLE.': The '.LAWS_NAME.', for Humans.');

		/*
		 * Initialize the sidebar variable.
		 */
		$sidebar = '
			<section>
			<p>Powered by <a href="http://www.statedecoded.com/">The State Decoded</a>.</p>
			</section>';
		/*
		 * Put the shorthand $sidebar variable into its proper place.
		 */
		$this->setContent('sidebar', $sidebar);
		unset($sidebar);

		/*
		 * Get an object containing a listing of the fundamental units of the code.
		 */
		$struct = new Structure();
		$structures = $struct->list_children();

		/*
		 * Initialize the body variable.
		 */
		$body .= '
			<article>
			<h1>'.ucwords($structures->{0}->label).'s of the '.LAWS_NAME.'</h1>
			<p>These are the fundamental units of the '.LAWS_NAME.'.</p>';
		if ( !empty($structures) )
		{
			$body .= '<dl class="level-1">';
			foreach ($structures as $structure)
			{
				$body .= '	<dt><a href="'.$structure->url.'">'.$structure->identifier.'</a></dt>
							<dd><a href="'.$structure->url.'">'.$structure->name.'</a></dd>';
			}
			$body .= '</dl>';
		}
		$body .= '</article>';

		/*
		 * Put the shorthand $body variable into its proper place.
		 */
		$this->setContent('body', $body);
		unset($body);
		
		return $this->renderContent();
		
	} /* index() */
	
	/**
	 * ContentController::about()
	 *
	 * The Home page
	 */
	public function about($args)
	{
	
		$this->setContent('browser_title', 'About');
		$this->setContent('page_title', 'About');
		$this->setContent('body', '');
		$this->setContent('sidebar', '');
		
		return $this->renderContent();
		
	}
	
}
