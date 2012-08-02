<?php

# Include the PHP declarations that drive this page.
require $_SERVER['DOCUMENT_ROOT'].'/../includes/page-head.inc.php';

# Fire up our templating engine.
$template = new Page;

$template->field->browser_title = 'Virginia Decoded: The Code of Virginia, for Humans.';

# Initialize our body variable.
$body = '';

# Initialize our sidebar variable.
$sidebar = '
	<section>
	<h1>Welcome</h1>
	<p>Virginia Decoded provides the Code of Virginia on one friendly website. Court decisions,
	legislation past and present, inline definitions, a modern API, and all of the niceties of
	modern website design. It’s like the expensive software lawyers use, but free and wonderful.</p>
	
	<p>This is a public beta test of Virginia Decoded, which is to say that everything is under
	development. Things are funny looking, broken, and generally unreliable right now.</p>
	
	<p>Powered by <a href="http://www.statedecoded.com/">The State Decoded</a>.</p>
	</section>';

# Get a listing of tags and display them.
$tags = new Tags;
$tags->get();
$tag_cloud = $tags->cloud();
if (!empty($tag_cloud))
{
	$sidebar .= '
		<section><h1>Topics</h1>
			<div class="tag-cloud">'.$tag_cloud.'</div>
		</section>';
}

# Show the most recent comments.
$sidebar .= '
	<section><h1>Recent Comments</h1>
		<div id="recentcomments" class="dsq-widget">
			<script src="http://vacode.disqus.com/recent_comments_widget.js?num_items=3&amp;hide_avatars=1&amp;avatar_size=32&amp;excerpt_length=200"></script>
		</div>
	</section>';

# Get an object containing a listing of the titles in the code.
$structure = new Structure();
$titles = $structure->list_children();

$body .= '
	<article>
	<h1>Titles of the Code of Virginia</h1>
	<p>These are the fundamental units of the Code of Virginia. There are 77 titles, roughly divided
	up by topic. Each title is divided into chapters, 1,535 in all, and each chapter is divided
	into sections (a.k.a. laws), 31,663 in all.</p>
	<dl class="titles">';
foreach ($titles as $title)
{
	$body .= '	<dt><a href="'.$title->url.'">'.$title->number.'</a></dt>
				<dd><a href="'.$title->url.'">'.$title->name.'</a></dd>';
}
$body .= '</dl></article>';

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

