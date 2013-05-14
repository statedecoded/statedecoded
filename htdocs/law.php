<?php

/**
 * The page that displays an individual law.
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

/*
 * Include the PHP declarations that drive this page.
 */
require '../includes/page-head.inc.php';

/*
 * Create a new instance of Law.
 */
$laws = new Law();

/*
 * Use the section number in the URL as the section number that we're looking up.
 */
$laws->section_number = urldecode($_GET['section_number']);

/*
 * Retrieve a copy of the law.
 */
$law = $laws->get_law();

if ($law === FALSE)
{
	send_404();
}

/*
 * Store a record that this section was viewed.
 */
$laws->record_view();

/*
 * If this is a request for a plain text version of this law, simply display that and exit.
 */
if (isset($_GET['plain_text']))
{
	
	/*
	 * Instruct the browser that this is plain text.
	 */
	header("Content-Type: text/plain");
	
	/*
	 * Provide a document header.
	 */
	echo str_repeat(' ', (round(((81 - strlen(LAWS_NAME)) / 2))))
		.strtoupper(LAWS_NAME)."\n\n";
	echo wordwrap(strtoupper($law->catch_line).' ('.SECTION_SYMBOL.' '.$law->section_number.')'
		."\n\n", 80, "\n", true);
		
	/*
	 * Send the text itself, which is already formatted properly.
	 */
	echo $law->plain_text;
	
	/*
	 * Include the history.
	 */
	echo wordwrap('HISTORY: '.$law->history, 80, "\n", true);
	
	/*
	 * End processing and exit.
	 */
	exit;
}

/*
 * Fire up our templating engine.
 */
$template = new Page;

/*
 * Make some section information available globally to JavaScript.
 */
$template->field->javascript = "var section_number = '".$law->section_number."';";
$template->field->javascript .= "var section_id = '".$law->section_id."';";
$template->field->javascript .= "var api_key = '".API_KEY."';";

$template->field->javascript_files = '
	<script src="/js/jquery.qtip.min.js"></script>
	<script src="/js/jquery.slideto.min.js"></script>
	<script src="/js/jquery.color-2.1.1.min.js"></script>
	<script src="/js/mousetrap.min.js"></script>
	<script src="/js/jquery.zclip.min.js"></script>
	<script src="/js/functions.js"></script>';

/*
 * Define the browser title.
 */
$template->field->browser_title = $law->catch_line.' ('.SECTION_SYMBOL.' '.$law->section_number.')—'.SITE_TITLE;

/*
 * Define the page title.
 */
$template->field->page_title = SECTION_SYMBOL.'&nbsp;'.$law->section_number.' '.$law->catch_line;

/*
 * If we have Dublin Core metadata, then display it.
 */
if (is_object($law->dublin_core))
{
	$template->field->meta_tags = '';
	foreach ($law->dublin_core AS $name => $value)
	{
		$template->field->meta_tags .= '<meta name="DC.'.$name.'" content="' . $value . '" />';
	}
}

/*
 * Define the breadcrumb trail text.
 */
$template->field->breadcrumbs = '';
foreach (array_reverse((array) $law->ancestry) as $ancestor)
{
	$template->field->breadcrumbs .= '<a href="'.$ancestor->url.'">'.$ancestor->identifier.' '
		.$ancestor->name.'</a> → ';
}
$template->field->breadcrumbs .= '<a href="/'.$law->section_number.'/">§&nbsp;'.$law->section_number
	.' '.$law->catch_line.'</a>';

/*
 * If there is a prior section in this structural unit, provide a back arrow.
 */
if (isset($law->previous_section))
{
	$template->field->breadcrumbs = '<a href="'.$law->previous_section->url.'" class="prev"
		title="Previous section">←</a>&nbsp;'.$template->field->breadcrumbs;
	$template->field->link_rel .= '<link rel="prev" title="Previous" href="'.$law->previous_section->url.'" />';
}

/*
 * If there is a next section in this chapter, provide a forward arrow.
 */
if (isset($law->next_section))
{
	$template->field->breadcrumbs .= '&nbsp;<a href="'.$law->next_section->url.'" class="next"
		title="Next section">→</a>';
	$template->field->link_rel .= '<link rel="next" title="Next" href="'.$law->next_section->url.'" />';
}

/*
 * Store the URL for the containing structural unit.
 */
$template->field->link_rel .= '<link rel="up" title="Up" href="'.$law->ancestry->{1}->url.'" />';

/*
 * Start assembling the body of this page by indicating the beginning of the text of the section.
 */
$body = '<article id="law">';

/*
 * Display the rendered HTML of this law.
 */
$body .= $law->html;

/*
 * If we have stored history for this section, display it.
 */
if (isset($law->history_text))
{
	$body .= '<section id="history">
				<h2>History</h2>
				<p>'.$law->history_text.'</p>
				</section>';
}

/*
 * Indicate the conclusion of the "section" article, which is the container for the text of a
 * section of the code.
 */
$body .= '</article>';

/*
 * Establish the $sidebar variable, so that we can append to it in conditionals.
 */
$sidebar = '';

/*
 * Only show the history if the law hasn't been repealed. (If it has been, then the history text
 * generally disappears along with it, meaning that the below code can behave unpredictably.)
 */
if ( (INCLUDES_REPEALED == TRUE) && (empty($law->repealed) || ($law->repealed !== true)) )
{
	$sidebar .= '
			<section id="history-description">
				<h1>History</h1>
				<p>
					This law was first passed in '.reset($law->amendment_years).'.';
	if (count((array) $law->amendment_years) > 1)
	{
		$sidebar .= ' It was updated in ';
	
		/*
		 * Iterate through every year in which this bill has been amended and list them.
		 */
		foreach ($law->amendment_years as $year)
		{
			if ($year == reset($law->amendment_years))
			{
				continue;
			}
			if ( ($year == end($law->amendment_years)) && (count((array)$law->amendment_years) > 2) )
			{
				$sidebar .= 'and ';
			}
			$sidebar .= $year;
			if ($year != end($law->amendment_years))
			{
				$sidebar .= ', ';
			}
		}
		$sidebar .= '.';
	}
	else
	{
		$sidebar .= ' It has not been changed since.';
	}
	$sidebar .= '
					</p>
				</section>';
}

/*
 * Commenting functionality.
 */
if (defined('DISQUS_SHORTNAME') === TRUE)
{
	$body .= <<<EOD
	<section id="comments">
		<h2>Comments</h2>
		<div id="disqus_thread"></div>
		<script>
			var disqus_shortname = 'vacode'; // required: replace example with your forum shortname
		
			/* * * DON'T EDIT BELOW THIS LINE * * */
			(function() {
				var dsq = document.createElement('script'); dsq.type = 'text/javascript'; dsq.async = true;
				dsq.src = 'http://' + disqus_shortname + '.disqus.com/embed.js';
				(document.getElementsByTagName('head')[0] || document.getElementsByTagName('body')[0]).appendChild(dsq);
			})();
		</script>
	</section>
EOD;
}

/*
 * If this section has been cited in any court decisions, list them.
 */
if ( isset($law->court_decisions) && ($law->court_decisions != FALSE) )
{
	$sidebar .= '<section id="court-decisions">
				<h1>Court Decisions</h1>
				<ul>';
	foreach ($law->court_decisions as $decision)
	{
		$sidebar .= '<li><a href="'.$decision->url.'"><em>'.$decision->name.'</em></a> ('
			.$decision->type_html.', '.date('m/d/y', strtotime($decision->date)).')<br />'
			.$decision->abstract.'</li>';
	}
	$sidebar .= '</ul>
			</section>';
}

/*
 * If we have a list of cross-references, list them.
 */
if ($law->references !== FALSE)
{

	$sidebar .= '
			<section id="references">
				<h1>Cross References</h1>
				<ul>';
	foreach ($law->references as $reference)
	{
		$sidebar .= '<li>'.SECTION_SYMBOL.'&nbsp;<a href="'.$reference->url.'" class="law">'
			.$reference->section_number.'</a> '.$reference->catch_line.'</li>';
	}
	$sidebar .= '</ul>
			</section>';
}

/*
 * If we have a list of related laws, list them.
 */
if (isset($law->related) && (count((array) $law->related) > 0))
{
	$sidebar .= '			  
			<section id="related-links">
				<h1>Related Laws</h1>
				<ul id="related">';
	foreach ($law->related as $related)
	{
		$sidebar .= '<li>'.SECTION_SYMBOL.'&nbsp;<a href="'.$related->url.'">'
			.$related->section_number.'</a> '.$related->catch_line.'</li>';
	}
	$sidebar .= '
				</ul>
			</section>';
}

/*
 *	If we have citation data and it's formatted properly, display it.
 */
if ( isset($law->citation) && is_object($law->citation) )
{
	
	$sidebar .= '<section id="cite-as">
				<h1>Cite As</h1>
				<ul>';
	foreach ($law->citation as $citation)
	{
		$sidebar .= '<li>'.$citation->label.': <span class="' . strtolower($citation->label) . '">'
			. $citation->text .'</span></li>';
	}
	$sidebar .= '</ul>
			</section>';
	
}

$sidebar .= '<section id="elsewhere">
				<h1>Trust, But Verify</h1>
				<p>If you’re reading this for anything important, you should double-check its
				accuracy';
if (isset($law->official_url))
{
	$sidebar .= '—<a href="'.$law->official_url.'">read '.SECTION_SYMBOL.'&nbsp;'.$law->section_number.' ';
}
$sidebar .= ' on the official '.LAWS_NAME.' website</a>.
			</section>';

$sidebar .= '<section id="keyboard-guide"><a id="keyhelp">'.$help->get_text('keyboard')->title.'</a></section>';

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
