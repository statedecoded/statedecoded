<?php

/**
 * The 404 page.
 * This file is only meant to be included within other files. As a result, it lacks the preamble
 * of includes, etc., since those will have already been done in the files within which this is
 * invoked.
 *
 * PHP version 5
 *
 * @license		http://www.gnu.org/licenses/gpl.html GPL 3
 * @version		0.8
 * @link		http://www.statedecoded.com/
 * @since		0.1
 *
 */

/*
 * Create a container for our content.
 */
$content = new Content();

/*
 * Define some page elements.
 */
$content->set('browser_title', '404, Not Found');
$content->set('page_title', '404, Not Found');

/*
 * Send a 404 header to the browser.
 */
header('HTTP/1.1 404 Not Found');

$body = '
<p>The page that you’re looking for is nowhere to be found. Sorry! Here are a few potential
solutions to the problem:</p>

<ul>
	<li>Check the website address at the top of your browser. Do you see any obvious errors?
	Fix ‘em!</li>
	<li>Try using the search box, at the top of the page, to search for what you’re looking for.</li>
	<li>Start over again <a href="/">at the home page</a> and try to browse your way to what you’re
	trying to find.</li>
</ul>

<p>This problem has been noted in our records, and we’ll look into it!</p>';

/*
 * Put the shorthand $body variable into its proper place.
 */
$content->set('body', $body);
unset($body);

/*
 * Add the custom classes to the body.
 */
$content->set('body_class', 'law inside');

/*
 * Fire up our templating engine.
 */
$template = Template::create();

/*
 * Parse the template, which is a shortcut for a few steps that culminate in sending the content
 * to the browser.
 */
$template->parse($content);
