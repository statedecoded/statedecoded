<?php

/**
 * The page that displays a list of editions.
 *
 * PHP version 5
 *
 * @license		http://www.gnu.org/licenses/gpl.html GPL 3
 * @version		0.9
 * @link		http://www.statedecoded.com/
 * @since		0.1
*/


/*
 * Setup the edition object.
 */
require_once(INCLUDE_PATH . 'class.Edition.inc.php');
global $db;
$edition_obj = new Edition(array('db' => $db));
$permalink_obj = new Permalink(array('db' => $db));

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
	// If we have a passed url, use it.
	if($_GET['from'])
	{
		$from_permalink = $permalink_obj->translate_permalink($_GET['from'], $edition->id);
	}

	// Translate our url into a shiny new permalink.
	$browse_permalink = $permalink_obj->translate_permalink('/browse/', $edition->id);


	$body .= '<li>';
	if($edition->current)
	{
		$body .= '<span class="current-edition">' . $edition->name . '</span>';
	}
	else {
		$body .= $edition->name;
	}

	if($edition->last_import)
	{
		$body .= ' - updated ' . date('M d, Y', strtotime($edition->last_import)) . '';
	}
	if($edition->current)
	{
		$body .= ' (Current Edition)';
	}

	if(isset($from_permalink) && $from_permalink !== FALSE && $from_permalink->url !== $browse_permalink->url)
	{
		$body .= '<br/><a href="' . $from_permalink->url . '">View ' . $from_permalink->title .'</a>';
	}
	if(isset($browse_permalink) && $browse_permalink !== FALSE)
	{
		$body .= '<br/><a href="' . $browse_permalink->url . '">Browse Edition</a>';
	}

	$body .= '</li>';
}
$body .= '</ol>';

$content->set('body', $body);

/*
 * Add the custom classes to the body.
 */
$content->set('body_class', 'inside structure');
$content->set('content_class', 'nest narrow');
