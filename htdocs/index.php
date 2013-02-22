<?php

/**
 * The site home page.
 *
 * Displays a list of the top-level structural units. May be customized to display introductory
 * text, sidebar content, etc.
 * 
 * PHP version 5
 *
 * @author		Waldo Jaquith <waldo at jaquith.org>
 * @copyright	2010-2013 Waldo Jaquith
 * @license		http://www.gnu.org/licenses/gpl.html GPL 3
 * @version		0.6
 * @link		http://www.statedecoded.com/
 * @since		0.1
 *
 */

/*
 * Include the PHP declarations that drive this page.
 */
require $_SERVER['DOCUMENT_ROOT'].'/../includes/page-head.inc.php';

/*
 * Fire up our templating engine.
 */
$template = new Page;

$template->field->browser_title = SITE_TITLE.': The '.LAWS_NAME.', for Humans.';

/*
 * Initialize the body variable.
 */
$body = '';

/*
 * Initialize the sidebar variable.
 */
$sidebar = '
	<section>
	<p>Powered by <a href="http://www.statedecoded.com/">The State Decoded</a>.</p>
	</section>';


/*
 * Get an object containing a listing of the titles in the code.
 */
$structure = new Structure();
$titles = $structure->list_children();

$body .= '
	<article>
	<h1>Titles of the '.LAWS_NAME.'</h1>
	<p>These are the fundamental units of the '.LAWS_NAME.'.</p>';
if ( !empty($titles) )
{
	$body .= '<dl class="level-1">';
	foreach ($titles as $title)
	{
		$body .= '	<dt><a href="'.$title->url.'">'.$title->number.'</a></dt>
					<dd><a href="'.$title->url.'">'.$title->name.'</a></dd>';
	}
	$body .= '</dl>';
}
$body .= '</article>';

/*
 * Put the shorthand $body variable into its proper place.
 */
$template->field->body = $body;
unset($body);

/*
 * Put the shorthand $sidebar variable into its proper place.
 */
$template->field->sidebar = $sidebar;
unset($sidebar);

/*
 * Parse the template, which is a shortcut for a few steps that culminate in sending the content
 * to the browser.
 */
$template->parse();
