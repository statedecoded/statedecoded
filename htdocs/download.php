<?php

/**
 * The "Download" page, providing links to download the soruce or use the API.
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

# Include the PHP declarations that drive this page.
require $_SERVER['DOCUMENT_ROOT'].'/../includes/page-head.inc.php';

# Fire up our templating engine.
$template = new Page;

# Define some page elements.
$template->field->browser_title = 'Download';
$template->field->page_title = 'Download';

$body = '';

$sidebar = '';

# Put the shorthand $body variable into its proper place.
$template->field->body = $body;
unset($body);

# Put the shorthand $sidebar variable into its proper place.
$template->field->sidebar = $sidebar;
unset($sidebar);

/*
 * Add the custom classes to the body.
 */
$template->field->body_class = 'law inside';

# Parse the template, which is a shortcut for a few steps that culminate in sending the content
# to the browser.
$template->parse();
