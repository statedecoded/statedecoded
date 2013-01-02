<?php

/**
 * This file is only meant to be included within other files. As a result, it lacks the preamble of includes,
 * etc., since those will have already been done in the files within which this is invoked.
 */

/*
 * Fire up our templating engine.
 */
$template = new Page;

/*
 * Define some page elements.
 */
$template->field->browser_title = '404, Not Found';
$template->field->page_title = '404, Not Found';

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

?>