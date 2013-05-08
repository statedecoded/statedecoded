<?php

/**
 * The page that displays an individual structural unit.
 *
 * PHP version 5
 *
 * @author		Waldo Jaquith <waldo at jaquith.org>
 * @copyright	2010-2013 Waldo Jaquith
 * @license		http://www.gnu.org/licenses/gpl.html GPL 3
 * @version		0.6
 * @link		http://www.statedecoded.com/
 * @since		0.1
*/

# Include the PHP declarations that drive this page.
require '../includes/page-head.inc.php';

# Create a new instance of Structure.
$struct = new Structure();

# If the URL doesn't represent a valid structural portion of the code, then bail.
if ( $struct->url_to_structure() === FALSE || empty($struct->structure) )
{
	send_404();
}

$structure = $struct->structure;

# Fire up our templating engine.
$template = new Page;

# Define the title page elements.
$template->field->browser_title = $struct->name.'—'.SITE_TITLE;
$template->field->page_title = $struct->name;

# Define the breadcrumb trail.
if (count((array) $structure) > 1)
{
	foreach ($structure as $level)
	{
		$template->field->breadcrumbs .= ' <a href="'.$level->url.'">'.$level->identifier.': '.$level->name.'</a> →';
		
		# If this structural element is the same as the parent container, then use that knowledge
		# to populate the link rel="up" tag.
		if ($level->id == $struct->parent_id)
		{
			$template->field->link_rel = '<link rel="up" title="Up" href="' . $level->url . '" />';
		}
	}
	$template->field->breadcrumbs = rtrim($template->field->breadcrumbs, '→');
	$template->field->breadcrumbs = trim($template->field->breadcrumbs);
}

# If this is a top-level element, there's no breadcrumb trail, but we still need to populate the
# link rel="up" tag.
else
{
	# Make the "up" link a link to the home page.
	$template->field->link_rel = '<link rel="up" title="Up" href="/" />';
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
				$template->field->link_rel .= '<link rel="prev" title="Previous" href="' . $tmp->url . '" />';
			}

			if ( $i < (count($struct->siblings)-1) )
			{
				next($struct->siblings);
				$tmp = next($struct->siblings);
				$template->field->link_rel .= '<link rel="next" title="Next" href="' . $tmp->url . '" />';
			}
			break;
			
		}
		$i++;
	}
}

# Provide a textual introduction to this section.
$body = '<p>This is '.ucwords($struct->label).' '.$struct->identifier.' of the '.LAWS_NAME.', titled
		“'.$struct->name.'.”';

if (count((array) $structure) > 1)
{
	foreach ($structure as $level)
	{
		if ($level->label !== $struct->label)
		{
			$body .= ' It is part of '.ucwords($level->label).' '.$level->identifier.', titled “'
				.$level->name.'.”';
		}
	}
}

# Get a listing of all the structural children of this portion of the structure.
$children = $struct->list_children();

# If we have successfully gotten a list of child structural units, display them.
if ($children !== FALSE)
{
	/* The level of this child structural unit is that of the current unit, plus one. */
	$body .= '<dl class="level-'.($structure->{count($structure)-1}->level + 1).'">';
	foreach ($children as $child)
	{
		$body .= '<dt><a href="'.$child->url.'">'.$child->identifier.'</a></dt>
				<dd><a href="'.$child->url.'">'.$child->name.'</a></dd>';
	}
	$body .= '</dl>';
}


# Get a listing of all laws that are children of this portion of the structure.
$laws = $struct->list_laws();

# If we have successfully gotten a list of laws, display them.
if ($laws !== FALSE)
{

	$body .= ' It’s comprised of the following '.count((array) $laws).' sections.</p>';
	$body .= '<dl class="laws">';

	foreach ($laws as $law)
	{	
		$body .= '
				<dt><a href="'.$law->url.'">'.SECTION_SYMBOL.'&nbsp;'.$law->section_number.'</a></dt>
				<dd><a href="'.$law->url.'">'.$law->catch_line.'</a></dd>';
	}
	$body .= '</dl>';
}

# Put the shorthand $body variable into its proper place.
$template->field->body = $body;
unset($body);

# Put the shorthand $sidebar variable into its proper place.
if (!empty($sidebar))
{
	$template->field->sidebar = $sidebar;
	unset($sidebar);
}

# Parse the template, which is a shortcut for a few steps that culminate in sending the content
# to the browser.
$template->parse();
