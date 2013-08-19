<?php

/**
 * The "Downloads" page, listing all of the bulk download files.
 * 
 * PHP version 5
 *
 * @author		Waldo Jaquith <waldo at jaquith.org>
 * @copyright	2010-2013 Waldo Jaquith
 * @license		http://www.gnu.org/licenses/gpl.html GPL 3
 * @version		0.8
 * @link		http://www.statedecoded.com/
 * @since		0.8
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

/*
 * Define some page elements.
 */
$template->field->browser_title = 'Downloads';
$template->field->page_title = 'Downloads';

$body = '
	<h2>Laws as JSON</h2>
	<p><a href="code.json.zip">code.json.zip</a><br />
	This is the basic data about every law, one JSON file per law. Fields include section, catch
	line, text, history, and structural ancestry (i.e., title number/name and chapter number/name).
	Note that any sections that contain colons (e.g., § 8.01-581.12:2) have an underscore in place
	of the colon in the filename, because neither Windows nor Mac OS support colons in filenames.</p>
	
	<h2>Laws as Plain Text</h2>
	<p><a href="code.txt.zip">code.txt.zip</a><br />
	This is the basic data about every law, one plain text file per law. Note that any sections that
	contain colons (e.g., § 8.01-581.12:2) have an underscore in place of the colon in the filename,
	because neither Windows nor Mac OS support colons in filenames.</p>
	
	<h2>Dictionary as JSON</h2>
	<p><a href="dictionary.json.zip">dictionary.json.zip</a><br />
	All terms defined in the laws, with each term’s definition, the section in which it is defined,
	and the scope (section, chapter, title, global) of that definition.</p>';

$sidebar = '';

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
