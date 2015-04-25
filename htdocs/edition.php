<?php

/**
 * The page that displays a list of editions.
 *
 * PHP version 5
 *
 * @license		http://www.gnu.org/licenses/gpl.html GPL 3
 * @version		0.8
 * @link		http://www.statedecoded.com/
 * @since		0.1
*/


/*
 * Setup the edition object.
 */
require_once(INCLUDE_PATH . 'class.Edition.inc.php');
global $db;
$edition_obj = new Edition(array('db' => $db));

/*
 * Create a container for our content.
 */
$content = new Content();

$content->set('browser_title', 'Editions');
$content->set('page_title', '<h2>Editions</h2>');

/*
 * Get editions.
 */
$editions = $edition_obj->all();

$body = '<p>
	These are the available editions of the code.
</p>';

$body .= '<ol class="edition-list">';
foreach($editions as $edition)
{
	// TODO: lookup permalink if $_GET['from] is set.
	$url = '/' . $edition->slug . '/';
	if($edition->current)
	{
		$url = '/browse/';
	}
	$body .= '<li';
	if($edition->current)
	{
		$body .= ' class="current-edition"';
	}
	$body .= '><a href="' . $url . '">' . $edition->name;
	$body .= '</a>';
	if($edition->last_import)
	{
		$body .= ' - updated ' . date('M d, Y', strtotime($edition->last_import)) . '';
	}
	if($edition->current)
	{
		$body .= ' (Current Edition)';
	}
}

$content->set('body', $body);

/*
 * Add the custom classes to the body.
 */
$content->set('body_class', 'inside structure');
$content->set('content_class', 'nest narrow');
