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
 * @version		0.8
 * @link		http://www.statedecoded.com/
 * @since		0.1
 *
 */

/*
 * Include the PHP declarations that drive this page.
 */
require $_SERVER['DOCUMENT_ROOT'] . '/../includes/page-head.inc.php';

/*
 * Fire up our templating engine.
 */
$template = new Page;

$template->field->browser_title = SITE_TITLE . ': The ' . LAWS_NAME . ', for Humans.';

/*
 * Initialize the body variable.
 */
$body = '';

/*
 * Provide an introduction on the sidebar.
 */
$sidebar = '<section id="intro">
				<h1>Welcome</h1>
				<p>' . SITE_TITLE . ' provides the ' . LAWS_NAME .' on one friendly website. A
				modern API, bulk downloads, inline definitions, cross-references, a responsive
				design, and all of the niceties of a modern website. It’s like the expensive
				software that lawyers use, but free and much better.</p>';

/*
 * If Disqus-based comments are enabled, display the most recent X comments.
 */
if (defined('DISQUS_SHORTNAME') === TRUE)
{

	/*
	 * Show these many comments.
	 */
	$comments = 3;

	$sidebar .= '<section id="recent-comments">
				<h1>Recent Comments</h1>
				<div id="recentcomments" class="dsq-widget">
					<script src="http://' . DISQUS_SHORTNAME . '.disqus.com/recent_comments_widget.js?num_items=' . $comments . '&amp;hide_avatars=1&amp;avatar_size=32&amp;excerpt_length=200"></script>
				</div>
			</section>';

}


/*
 * Get an object containing a listing of the fundamental units of the code.
 */
$struct = new Structure();
$structures = $struct->list_children();

$body .= '
	<article>
	<h1>' . ucwords($structures->{0}->label) . 's of the ' . LAWS_NAME . '</h1>

	<p>These are the fundamental units of the ' . LAWS_NAME . '.</p>';

/*
 * Row classes and row counter
 */
$row_classes = array('odd', 'even');
$counter = 0;

if ( !empty($structures) )
{
	$body .= '<dl class="title-list">';
	foreach ($structures as $structure)
	{
		/*
		 * The remainder of the count divided by the number of classes
		 * yields the proper index for the row class.
		 */
		$class_index = $counter % count($row_classes);
		$row_class = $row_classes[$class_index];

		$body .= '	<dt class="' . $row_class . '"><a href="' . $structure->url . '">' . $structure->identifier . '</a></dt>
					<dd class="' . $row_class . '"><a href="' . $structure->url . '">' . $structure->name . '</a></dd>';

		$counter++;
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
 * Add the custom classes to the body.
 */
$template->field->body_class = 'inside';
$template->field->content_class = 'nest narrow';

/*
 * Parse the template, which is a shortcut for a few steps that culminate in sending the content
 * to the browser.
 */
$template->parse();
