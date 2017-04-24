<?php

/**
 * The "About" page, explaining this State Decoded website.
 *
 * PHP version 5
 *
 * @license		http://www.gnu.org/licenses/gpl.html GPL 3
 * @version		1.0
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
$content->set('browser_title', 'About');
$content->set('page_title', 'About');

$body = '
<p>Legal codes are wretched. Seriously, look at a few: <a href="http://www.leginfo.ca.gov/calaw.html">California’s</a>, <a href="http://public.leginfo.state.ny.us/menugetf.cgi?COMMONQUERY=LAWS">New York’s</a>, <a href="http://www.ilga.gov/legislation/ilcs/ilcs.asp">Illinois’</a>, and <a href="http://www.statutes.legis.state.tx.us/">Texas’</a> are all good examples of how stunningly difficult that it is to understand legal codes. They don’t have APIs. Virtually none have bulk downloads. You’re stuck with their crude offerings.</p>
<p>The State Decoded is a platform that displays legal codes, court decisions, and information from legislative tracking services to make it all more understandable to normal humans. With beautiful typography, embedded definitions of legal terms, and a robust API, this project aims to make our laws a centerpiece of media coverage.</p>
<p>In development since June 2010, The State Decoded is in open beta testing now, with <a href="https://www.statedecoded.com/places/">a growing network of sites running the software</a>. This work is made possible by <a href="http://www.knightfoundation.org/grants/20110158/">a generous grant from the Knight Foundation</a>.</p>
<p><a href="https://github.com/waldoj/statedecoded">The LAMP-based code is GPLd and on Github</a>. The goal is to get an organization in every state in the union (and D.C., Guam, and Puerto Rico!) to install this software and create a website for their own state’s code. Interested in stepping up to the plate for your fellow citizens? <a href="https://www.statedecoded.com/contact/">E-mail us!</a></p>
';

$sidebar = '';

/*
 * Put the shorthand $body variable into its proper place.
 */
$content->set('body', $body);
unset($body);

/*
 * Put the shorthand $sidebar variable into its proper place.
 */
$content->set('sidebar', $sidebar);
unset($sidebar);

/*
 * Add the custom classes to the body.
 */
$content->set('body_class', 'inside');
$content->set('content_class', 'nest narrow');


/*
 * Fire up our templating engine.
 */
$template = Template::create();

/*
 * Parse the template, which is a shortcut for a few steps that culminate in sending the content
 * to the browser.
 */
$template->parse($content);
