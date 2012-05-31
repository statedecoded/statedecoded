<?php

# Include the PHP declarations that drive this page.
require $_SERVER['DOCUMENT_ROOT'].'/../includes/page-head.inc.php';
require $_SERVER['DOCUMENT_ROOT'].'/../includes/sphinxapi.php';

# Fire up our templating engine.
$template = new Page;

# Clean up our query parameters and localize them.
if (isset($_GET['q']))
{
	$q = filter_input(INPUT_GET, 'q', FILTER_SANITIZE_STRING);
}
if (isset($_GET['title']))
{
	$title = filter_input(INPUT_GET, 'title', FILTER_SANITIZE_STRING);
}
if (isset($_GET['chapter']))
{
	$chapter = filter_input(INPUT_GET, 'chapter', FILTER_SANITIZE_STRING);
}

# Deal with our page indicator.
if (isset($_GET['p']))
{
	$page = filter_input(INPUT_GET, 'p', FILTER_SANITIZE_STRING);
}
else
{
	$page = 1;
}
$per_page = 10;
$offset = ($page * $per_page) - $per_page;

# Define some page elements.
$template->field->browser_title = 'Search';
$template->field->page_title = 'Search: “'.$q.'”';

# Include search result CSS.
$template->field->css = '<link rel="stylesheet" href="/css/search.css" type="text/css" media="screen">';

# Connect to Sphinx and issue a query.
$cl = new SphinxClient();
$cl->SetServer("localhost", 9312);
$cl->SetLimits($offset, $per_page);
if (isset($title) || isset($chapter))
{
	$cl->setMatchMode('SPH_MATCH_EXTENDED');
	if (empty($chapter))
	{
		$q_formatted = $q.' @title_number '.$title;
		//$cl->SetFilter('title_number', $title);
	}
	if (!empty($title) && !empty($chapter))
	{
		$q_formatted = $q.' @title_number '.$title.' @chapter_number '.$chapter;
		//$cl->SetFilter('title_number', $title);
		//$cl->SetFilter('chapter_number', $chapter);
	}
	echo $q_formatted;
}
else
{
	$cl->setMatchMode('SPH_MATCH_ALL');
	$q_formatted = $q;
}
$result = $cl->Query($q_formatted, 'laws');

# If there's an error, return a warning and bail.
if ($result === false)
{
	$body = '<p>Warning: '.$cl->GetLastWarning().'</p>
			<p>Zero results found.</p>';
}

# If everything is A-OK, then list the results.
else
{
	
	/*$sidebar = '
		<section>
		<h1>Tips</h1>

		<p>To exclude a word, precede it with a minus sign (e.g., “<code>theft
		-felony</code>”.)</p>

		<p>The vertical pipe (“|”) means “or” (e.g., “<code>felony | misdemeanor theft</code>”.)</p>
		
		<p>Subqueries can be grouped in parentheses (e.g., “<code>(felony -misdemeanor) | (theft
		-larceny)</code>”.)</p></p>
		</section>';*/
	
	$body = '<p>'.number_format($result['total_found']).' results found.</p>';
	
	$body .= '
		<form action="/search/"> 
			<input type="search" size="60" name="q" value="'.$q.'" /> 
			<input type="submit" value="Search" />
		</form>';
	
	# If somebody is searching for a section of the code, then alert them to a direct match.
	if (preg_match(SECTION_PCRE, $q))
	{
		$body .= '
			<aside id="alert">
				<h2>Looking for '.$q.'?</h2>
				<p>If you’re not searching for references to §&nbsp;'.$q.', but actually want to
				read that specific section of the code, then <a href="/'.$q.'/">here it is!</a>
			</aside>';
	}
	
	# Iterate through the results and build up a list of IDs.
	foreach ($result['matches'] as $law_id => $details)
	{
		$ids[] = $law_id;
	}
	
	# Feed the resulting list of IDs to the function that will retrieve them.
	$laws = new Law;
	$law_list = new stdClass();
	$i=0;
	$documents = array();
	foreach ($ids as $id)
	{
		$laws->law_id = $id;
		$tmp = $laws->get_law();
		$law_list->$i = $tmp;
		$documents[] = strip_tags($tmp->full_text);
		$i++;
	}
	
	# Define the options that we'll use for our excerption query.
	$options = array
	(
		'before_match'		=> '<strong>',
		'after_match'		=> '</strong>',
		'chunk_separator'	=> ' .&thinsp;.&thinsp;. ',
		'limit'				=> 250,
		'around'			=> 20,
	);
	
	# Ask Sphinx to provide us with excerpts for each of these results.
	$excerpts = $cl->BuildExcerpts($documents, 'laws', $q, $options);
	
	$i=0;
	foreach ($law_list as $search_result)
	{
		$body .= '<h2><a href="'.$search_result->url.'">§&nbsp;'.$search_result->section_number
			.': '.$search_result->catch_line.'</a></h2>';
		$body .= '<p class="excerpt">'.$excerpts[$i].'</p>';
		$body .= '<p class="location"><a href="'.$search_result->title->url.'">'
			.$search_result->title->name.'</a> → <a href="'.$search_result->chapter->url.'">'
			.$search_result->chapter->name.'</a></p>';
		$i++;
	}
}

# If we have more results than the number of results that we show per page.
if ($result['total_found'] > $per_page)
{
	$body .= '<ul class="paging">';
	for ($i=0; ((($i+1)*$per_page) <= ceil($result['total_found']/10)*10); $i++)
	{
		
		# If it's the current page.
		if (($i+1) == $page)
		{
			$body .= '<li><strong>'.($i+1).'</strong></li>';
		}
		# Othewise, if it's not the current page.
		else
		{
			$body .= '<li><a href="/search/?q='.urlencode($q).'&amp;p='.($i+1).'">'.($i+1).'</a></li>';
		}
	}
	$body .= '</ul>';
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