<?php

# Include the PHP declarations that drive this page.
require $_SERVER['DOCUMENT_ROOT'].'/../includes/page-head.inc.php';

# Create a new instance of Structure.
$struct = new Structure();

# Pass the requested URL to Structure, and then get structural data from that URL.
$struct->url_to_structure();
$structure = $struct->structure;

# If the URL doesn't represent a valid structural portion of the code, then bail. We use a double
# equals here because url_to_structure() is mysteriously not returning false.
if ($structure == false)
{
	send_404();
}

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
		$template->field->breadcrumbs .= ' <a href="'.$level->url.'">'.$level->number.': '.$level->name.'</a> →';
	}
	$template->field->breadcrumbs = rtrim($template->field->breadcrumbs, '→');
	$template->field->breadcrumbs = trim($template->field->breadcrumbs);
}

# Get a listing of tags and display them.
$tags = new Tags;
$current_structure = end($structure);
$tags->structure_id = $current_structure->id;
$tags->structure_label = $current_structure->label;
$tags->get();
$tag_cloud = $tags->cloud();
if (!empty($tag_cloud))
{
	$sidebar = '
		<h1>Topics</h1>
		<div class="tag-cloud">'.$tags->cloud().'</div>';
}

# Provide a textual introduction to this section.
$body .= '<p>This is '.ucwords($struct->label).' '.$struct->number.' of the '.LAWS_NAME.', titled
		“'.$struct->name.'.”';

# If 
if (count((array) $structure) > 1)
{
	foreach ($structure as $level)
	{
		if ($level->label !== $struct->label)
		{
			$body .= ' It is part of '.ucwords($level->label).' '.$level->number.', titled “'
				.$level->name.'.”';
		}
	}
}

# Get a listing of all the structural children of this portion of the structure.
$children = $struct->list_children();

# If we have successfully gotten a list of child sections, display them.
if ($children !== false)
{
	
	$body .= '<dl class="chapters">';
	foreach ($children as $child)
	{
		$body .= '<dt><a href="'.$child->url.'">'.$child->number.'</a></dt>
				<dd><a href="'.$child->url.'">'.$child->name.'</a></dd>';
	}
	$body .= '</dl>';
}


# Get a listing of all laws that are children of this portion of the structure.
$laws = $struct->list_laws();

# If we have successfully gotten a list of laws, display them.
if ($laws !== false)
{

	$body .= ' It’s comprised of the following '.count((array) $laws).' sections.</p>';
	$body .= '<dl class="sections">';
	foreach ($laws as $law)
	{	
		$body .= '
				<dt><a href="'.$law->url.'">'.SECTION_SYMBOL.'&nbsp;'.$law->number.'</a></dt>
				<dd><a href="'.$law->url.'">'.$law->catch_line.'</a></dd>';
	}
	$body .= '</dl>';
}

# Put the shorthand $body variable into its proper place.
$template->field->body = $body;
unset($body);

# Put the shorthand $sidebar variable into its proper place.
$template->field->sidebar = $sidebar;
unset($sidebar);

# Parse the template, which is a shortcut for a few steps that culminate in sending the content
# to the browser.
$template->parse();

?>