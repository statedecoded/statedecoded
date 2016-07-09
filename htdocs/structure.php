<?php

/**
 * The page that displays an individual structural unit.
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
$edition = new Edition(array('db' => $db));

/*
 * If no identifier has been specified, explicitly make it a null variable. This is when the request
 * is for the top level -- that is, a listing of the fundamental units of the code (e.g., titles).
 */
if ( !isset($args['relational_id']) || empty($args['relational_id']) )
{
	$structure_id = '';
}

/*
 * If an identifier has been specified (which may come in the form of multiple identifiers,
 * separated by slashes), localize that variable.
 */
else
{
	/*
	 * Localize the identifier, filtering out unsafe characters.
	 */
	$structure_id = filter_var($args['relational_id'], FILTER_SANITIZE_STRING);
}

/*
 * Create a new instance of the class that handles information about individual laws.
 */
$struct = new Structure();

if ( isset($args['edition_id']) )
{
	$struct->edition_id = $args['edition_id'];
}
else
{
	$edition_data = $edition->current();
	$struct->edition_id = $edition_data->id;
}

/*
 * Get the structure based on our identifier
 */
$struct->structure_id = $structure_id;
$struct->get_current();

/*
 * If are at the top level, struct->structure is null.
 */
$response = (isset($struct->structure) ? $struct->structure : '' );

/*
 * If the URL doesn't represent a valid structural portion of the code, then bail.
 */
if ( $response === FALSE)
{
	send_404();
}

/*
 * Set aside the ancestry for this structural unit, to be accessed separately.
 */
// Again, if we at the top level, this will return null
$structure = (isset($struct->structure) ? $struct->structure : '' );

/*
 * Get a listing of all the structural children of this portion of the structure.
 */
$children = $struct->list_children();

/*
 * Create a container for our content.
 */
$content = new Content();

/*
 * Setup the body.
 */
$body = '';

/*
 * Define the title page elements.
 */
if (strlen($structure_id) > 0)
{
	$content->set('browser_title', $struct->name);
	$content->set('page_title', '<h2>' . $struct->name . '</h2>');
}
else
{
	$content->set('browser_title', SITE_TITLE . ': The ' . LAWS_NAME . ', for Humans.');
	$content->set('page_title', '<h2>'.ucwords($children->{0}->label) . 's of the ' . LAWS_NAME.'</h2>');
}

/*
 * Make some section information available globally to JavaScript.
 */
$content->append('javascript', "var api_key = '" . API_KEY . "';");

/*
 * Define the breadcrumb trail.
 */
if (count((array) $structure) > 1)
{

	foreach ($structure as $level)
	{

		$active = '';

		if ($level == end($structure))
		{
			$active = 'active';
		}

		$content->append('breadcrumbs', '<li class="' . $active . '">
				<a href="' . $level->url . '">' . $level->identifier . ': ' . $level->name . '</a>
			</li>');

		/*
		 * If this structural element is the same as the parent container, then use that knowledge
		 * to populate the link rel="up" tag.
		 */
		if ($level->id == $struct->parent_id)
		{
			$content->set('link_rel', '<link rel="up" title="Up" href="' . $level->url . '" />');
		}

	}

}

if (strlen($content->get('breadcrumbs')) > 0)
{
	$content->prepend('breadcrumbs', '<ul class="steps-nav">');
	$content->append('breadcrumbs', '</ul>');

	$content->set('heading', '<nav class="breadcrumbs" role="navigation">' .
		$content->get('breadcrumbs') . '</nav>');

}

/*
 * If this is a top-level element, there's no breadcrumb trail, but we still need to populate the
 * link rel="up" tag.
 */
else
{

	/*
	* Make the "up" link a link to the home page.
	*/
	$content->set('link_rel', '<link rel="up" title="Up" href="/browse/" />');

}

/*
 * Provide link relations for the previous and next sibling structural units.
 */
if (isset($struct->siblings))
{

	/*
	 * Locate the instant structural unit within the structure listing.
	 */
	$current_structure = end($structure);
	$i=0;

	/*
	 * When the present structure is identified, pull out the prior and next ones.
	 */
	foreach ($struct->siblings as $sibling)
	{

		if ($sibling->id === $current_structure->id)
		{

			if ($i >= 1)
			{
				prev($struct->siblings);
				$tmp = prev($struct->siblings);
				$content->append('link_rel', '<link rel="prev" title="Previous" href="' . $tmp->url . '" />');
			}

			if ( $i < (count($struct->siblings)-1) )
			{
				next($struct->siblings);
				$tmp = next($struct->siblings);
				$content->append('link_rel', '<link rel="next" title="Next" href="' . $tmp->url . '" />');
			}

			break;

		}
		$i++;

	}

}

/*
 * Provide a textual introduction to this section.
 */
if(strlen($structure_id) > 0)
{

	$body .= '<p>This is '.ucwords($struct->label).' '.$struct->identifier.' of the ' . LAWS_NAME
		. ', titled “'.$struct->name.'.”';

	if (count((array) $structure) > 1)
	{

		foreach ($structure as $level)
		{

			if ($level->label !== $struct->label && !empty($level->label))
			{
				$body .= ' It is part of ' . ucwords($level->label) . ' ' . $level->identifier . ', '
				.'titled “' . $level->name . '.”';
			}

		}

	}

}

else
{
	$body .= '
		<p>These are the fundamental units of the ' . LAWS_NAME . '.</p>';
}

/*
 * If we have any metadata about this structural unit.
 */
if (isset($struct->metadata))
{

	if (isset($struct->metadata->child_laws) && ($struct->metadata->child_laws > 0) )
	{

		$body .= ' It contains ' . number_format($struct->metadata->child_laws) . ' laws';
		if (isset($struct->metadata->child_structures) && ($struct->metadata->child_structures > 0) )
		{
			$body .= ' divided across ' . number_format($struct->metadata->child_structures)
				. ' structures.';
		}
		else
		{
			$body .= '.';
		}

	}
	elseif (isset($struct->metadata->child_structures) && ($struct->metadata->child_structures > 0) )
	{
		$body .= ' It is divided into ' . number_format($struct->metadata->child_structures)
			. ' sub-structures.';
	}

}

/*
 * Row classes and row counter.
 */
$row_classes = array('odd', 'even');
$counter = 0;

/*
 * If we have successfully gotten a list of child structural units, display them.
 */
if ($children !== FALSE)
{

	/*
	 * The level of this child structural unit is that of the current unit, plus one.
	 */
	$body .= '<dl class="title-list sections level-' . ((isset($structure->{count($structure)-1}->level) ? $structure->{count($structure)-1}->level : 0) + 1) . '">';
	foreach ($children as $child)
	{

		/*
		 * The remainder of the count divided by the number of classes
		 * yields the proper index for the row class.
		 */
		$class_index = $counter % count($row_classes);
		$row_class = $row_classes[$class_index];
		$api_url = '/api/1.0/structure/' . $child->token
			 . '/?key=' . API_KEY;

		$body .= '	<dt class="' . $row_class . '"><a href="' . $child->url . '"
				data-identifier="' . $child->token . '"
				data-api-url="' . $api_url . '"
				>' . $child->identifier . '</a></dt>
			<dd class="' . $row_class . '"><a href="' . $child->url . '"
				data-identifier="' . $child->token . '"
				data-api-url="' . $api_url . '"
				>' . $child->name . '</a></dd>';

		$counter++;

	}

	$body .= '</dl>';

}


/*
 * Reset counter
 */
$counter = 0;

/*
 * Get a listing of all laws that are children of this portion of the structure.
 */
$laws = $struct->list_laws();

/*
 * If we have successfully gotten a list of laws, display them.
 */
if ($laws !== FALSE)
{

	$body .= ' It’s comprised of the following ' . count((array) $laws) . ' sections.</p>';
	$body .= '<dl class="title-list laws">';

	foreach ($laws as $law)
	{

		/*
		 * The remainder of the count divided by the number of classes
		 * yields the proper index for the row class.
		 */
		$class_index = $counter % count($row_classes);
		$row_class = $row_classes[$class_index];

		$body .= '
				<dt class="' . $row_class.'"><a href="' . $law->url . '">'
					. SECTION_SYMBOL . '&nbsp;' . $law->section_number . '</a></dt>
				<dd class="' . $row_class.'"><a href="' . $law->url . '">'
					. $law->catch_line . '</a></dd>';

		$counter++;

	}
	$body .= '</dl>';
}

/*
 * If this isn't the canonical page, show a canonical meta tag.
 */
if(strlen($structure_id) > 0)
{
	$permalink_obj = new Permalink(array('db' => $db));
	$permalink = $permalink_obj->get_permalink($struct->structure_id, 'structure', $struct->edition_id);
	if($args['url'] !== $permalink->url)
	{
		$content->append('meta_tags',
			'<link rel="canonical" href="' . $permalink->url . '" />');
	}
}

/*
 * Put the shorthand $body variable into its proper place.
 */
$content->set('body', $body);
unset($body);

/*
 * Show edition info.
 */

$edition_data = $edition->find_by_id($struct->edition_id);
$edition_list = $edition->all();
if($edition_data && count($edition_list) > 1)
{
	$content->set('edition', '<p class="edition">This is the <strong>' . $edition_data->name . '</strong> edition of the code.  ');
	if($edition_data->current)
	{
		$content->append('edition', 'This is the current edition.  ');
	}
	else {
		$content->append('edition', 'There is <strong>not</strong> the current edition.  ');
	}
	if($edition_data->last_import)
	{
		$content->append('edition', 'It was last updated ' . date('M d, Y', strtotime($edition_data->last_import)) . '.  ');
	}
	$content->append('edition', '<a href="/editions/?from=' . $_SERVER['REQUEST_URI'] . '" class="edition-link">Browse all editions.</a></p>');
}
$content->set('current_edition', $struct->edition_id);

/*
 * Put the shorthand $sidebar variable into its proper place.
 */
if (!empty($sidebar))
{
	$content->set('sidebar', $sidebar);
	unset($sidebar);
}

/*
 * Add the custom classes to the body.
 */
$content->set('body_class', 'inside structure');
$content->set('content_class', 'nest narrow');
