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
 * Initialize the sidebar variable.
 */
$sidebar = '';


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
