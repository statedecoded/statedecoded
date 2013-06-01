<?php

/**
 * The page that displays an individual structural unit.
 *
 * PHP version 5
 *
 * @author		Waldo Jaquith <waldo at jaquith.org>
 * @copyright	2010-2013 Waldo Jaquith
 * @license		http://www.gnu.org/licenses/gpl.html GPL 3
 * @version		0.7
 * @link		http://www.statedecoded.com/
 * @since		0.1
*/

# Include the PHP declarations that drive this page.
require '../includes/page-head.inc.php';

# Create a new instance of Structure.
$struct = new Structure();

# Use the URL to identify the requested structural unit.
$result = $struct->url_to_structure();

# If the URL doesn't represent a valid structural portion of the code, then bail.
if ( $result === FALSE)
{
	send_404();
}

# Set aside the ancestry for this structural unit, to be accessed separately.
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

# If we have any metadata about this structural unit.
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
 * Row classes and row counter
 */
$row_classes = array('odd', 'even');
$counter = 0;

# Get a listing of all the structural children of this portion of the structure.
$children = $struct->list_children();

# If we have successfully gotten a list of child structural units, display them.
if ($children !== FALSE)
{
	/* The level of this child structural unit is that of the current unit, plus one. */
	$body .= '<dl class="title-list level-'.($structure->{count($structure)-1}->level + 1).'">';
	foreach ($children as $child)
	{
		/*
		 * The remainder of the count divided by the number of classes
		 * yields the proper index for the row class.
		 */
		$class_index = $counter % count($row_classes);
		$row_class = $row_classes[$class_index];

		$body .= '	<dt class="'.$row_class.'"><a href="'.$child->url.'">'.$child->identifier.'</a></dt>
					<dd class="'.$row_class.'"><a href="'.$child->url.'">'.$child->name.'</a></dd>';

		$counter++;
	}
	$body .= '</dl>';
}


/*
 * Reset counter
 */
$counter = 0;

# Get a listing of all laws that are children of this portion of the structure.
$laws = $struct->list_laws();

# If we have successfully gotten a list of laws, display them.
if ($laws !== FALSE)
{

<<<<<<< HEAD
	$body .= '<dl class="laws">';
=======
	$body .= 'It’s comprised of the following '.count((array) $laws).' sections.</p>';
	$body .= '<dl class="title-list laws">';
>>>>>>> Fixed list views to work when identifiers are long or different lengths.

	foreach ($laws as $law)
	{
		/*
		 * The remainder of the count divided by the number of classes
		 * yields the proper index for the row class.
		 */
		$class_index = $counter % count($row_classes);
		$row_class = $row_classes[$class_index];

		$body .= '
				<dt class="'.$row_class.'"><a href="'.$law->url.'">'.SECTION_SYMBOL.'&nbsp;'.$law->section_number.'</a></dt>
				<dd class="'.$row_class.'"><a href="'.$law->url.'">'.$law->catch_line.'</a></dd>';

		$counter++;
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

/*
 * Add the custom classes to the body.
 */
$template->field->body_class = 'law inside';
$template->field->content_class = 'nest narrow';

# Parse the template, which is a shortcut for a few steps that culminate in sending the content
# to the browser.
$template->parse();
