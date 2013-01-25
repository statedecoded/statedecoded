<?php

/**
 * The page that displays an individual law.
 *
 * PHP version 5
 *
 * @author		Waldo Jaquith <waldo at jaquith.org>
 * @copyright	2010-2012 Waldo Jaquith
 * @license		http://www.gnu.org/licenses/gpl.html GPL 3
 * @version		0.5
 * @link		http://www.statedecoded.com/
 * @since		0.1
*/

// Include the PHP declarations that drive this page.
require '../includes/page-head.inc.php';

// Create a new instance of Law.
$laws = new Law();

// Use the section number in the URL as the section number that we're looking up.
$laws->section_number = urldecode($_GET['section_number']);

// Retrieve a copy of the law.
$law = $laws->get_law();

if ($law === false)
{
	send_404();
}

// Store a record that this section was viewed.
$laws->record_view();

# Get the dictionary terms for this chapter.
$dictionary = new Dictionary();
$dictionary->structure_id = $law->structure_id;
$dictionary->section_id = $law->id;
if ($law->catch_line == 'Definitions.')
{
	$dictionary->scope = 'global';
}
$terms = $dictionary->term_list();

# Arrange our terms from longest to shortest. This is to ensure that the most specific terms are
# defined (e.g. "person of interest") rather than the broadest terms (e.g. "person").
usort($terms, 'sort_by_length');

# Store a list of the dictionary terms as an array, which is required for preg_replace_callback, the
# function that we use to insert the definitions.
$term_pcres = array();
foreach ($terms as $term)
{
	
	# Step through each character in this word.
	for ($i=0; $i<strlen($term); $i++)
	{
		# If there are any uppercase characters, then make this PCRE string case sensitive.
		if ( (ord($term{$i}) >= 65) && (ord($term{$i}) <= 90) )
		{
			$term_pcres[] = '/\b'.$term.'(s?)\b(?![^<]*>)/';
			$caps = true;
			break;
		}
	}
	
	# If we have determined that this term does not contain capitalized letters, then create a case-
	# insensitive PCRE string.
	if (!isset($caps))
	{
		$term_pcres[] = '/\b'.$term.'(s?)\b(?![^<]*>)/i';
	}
	
	# Unset our flag -- we don't want to have it set the next time through.
	if (isset($caps))
	{
		unset($caps);
	}
}

// Fire up our templating engine.
$template = new Page;

// Make some section information available globally to JavaScript.
$template->field->javascript = "var section_number = '".$law->section_number."';";
$template->field->javascript .= "var section_id = '".$law->id."';";
$template->field->javascript .= "var api_key = '".API_KEY."';";

$template->field->javascript_files = '
	<script src="/js/jquery.qtip.min.js"></script>
	<script src="/js/jquery.slideto.min.js"></script>
	<script src="/js/mousetrap.min.js"></script>
	<script src="/js/functions.js"></script>';

$autolinker = new Autolinker;

# Iterate through every section to make some basic transformations.
foreach ($law->text as $section)
{
	
	# Prevent lines from wrapping in the middle of a section identifier.
	$section->text = str_replace('§ ', '§&nbsp;', $section->text);
	
	# Turn every code reference in every paragraph into a link.
	$section->text = preg_replace_callback(SECTION_PCRE, 'replace_sections', $section->text);
	
	# Use our dictionary to embed dictionary terms in the form of span titles.
	if (isset($term_pcres))
	{
		//$section->text = preg_replace_callback($term_pcres, 'replace_terms', $section->text);
		$section->text = preg_replace_callback($term_pcres, array($autolinker, 'replace_terms'), $section->text);
	}
}

// Define the browser title.
$template->field->browser_title = $law->catch_line.' ('.SECTION_SYMBOL.' '.$law->section_number.')—'.SITE_TITLE;

// Define the page title.
$template->field->page_title = SECTION_SYMBOL.'&nbsp;'.$law->section_number.' '.$law->catch_line;

// Define the breadcrumb trail text.
$template->field->breadcrumbs = '';
foreach (array_reverse((array) $law->ancestry) as $ancestor)
{
	$template->field->breadcrumbs .= '<a href="'.$ancestor->url.'">'.$ancestor->number.' '
		.$ancestor->name.'</a> → ';
}
$template->field->breadcrumbs .= '<a href="/'.$law->section_number.'/">§&nbsp;'.$law->section_number
	.' '.$law->catch_line.'</a>';

// If there is a prior section in this structural unit, provide a back arrow.
if (isset($law->previous_section))
{
	$template->field->breadcrumbs = '<a href="'.$law->previous_section->url.'" class="prev"
		title="Previous section">←</a>&nbsp;'.$template->field->breadcrumbs;
	$template->field->link_rel .= '<link rel="prev" title="Previous" href="'.$law->previous_section->url.'" />';
}

// If there is a next section in this chapter, provide a forward arrow.
if (isset($law->next_section))
{
	$template->field->breadcrumbs .= '&nbsp;<a href="'.$law->next_section->url.'" class="next"
		title="Next section">→</a>';
	$template->field->link_rel .= '<link rel="next" title="Next" href="'.$law->next_section->url.'" />';
}

// Start assembling the body of this page by indicating the beginning of the text of the section.
$body = '<article id="law">';

# Iterate through each section of text to display it.
$i=0;
$num_paragraphs = count((array) $law->text);
foreach ($law->text as $paragraph)
{

	# Identify the prior and next sections, by storing their prefixes.
	if ($i > 0)
	{
		$paragraph->prior_prefix = $law->text->{$i-1}->entire_prefix;
	}
	if ($i < $num_paragraphs)
	{
		$paragraph->next_prefix = $law->text->{$i+1}->entire_prefix;
	}

	# If this paragraph's prefix hierarchy is different than that of the prior prefix, than indicate
	# that this is a new section.
	if ($paragraph->entire_prefix != $paragraph->prior_prefix)
	{
		$body .= '
			<section';
		if (!empty($paragraph->prefix_anchor))
		{
			$body .= ' id="'.$paragraph->prefix_anchor.'"';
		}
		
		# If this is a subsection, indent it.
		if ($paragraph->level > 1)
		{
			$body .= ' class="indent-'.($paragraph->level-1);
			$body .= '"';
		}
		$body .= '>';
	}
	
	# Start a paragraph of the appropriate type.
	$body .= '<';
	if ($paragraph->type == 'section')
	{
		$body .= 'p';
	}
	elseif ($paragraph->type == 'table')
	{
		$body .= 'pre class="table"';
	}
	$body .= '>';
	
	# If we've got a section prefix, and it's not the same as the last one, then display it.
	if ($paragraph->entire_prefix != $paragraph->prior_prefix)
	{
		
		$body .= $paragraph->prefix;
		
		# We could use a regular expression to determine if we need to append a period, but
		# that would be slower.
		if ( (substr($paragraph->prefix, -1) != ')') && (substr($paragraph->prefix, -1) != '.') )
		{
			$body .= '.';
		}
		$body .= ' ';
	}
	
	# Display this section of text.
	$body .= $paragraph->text;
	
	# If we've got a section prefix, append a paragraph link to the end of this section.
	if (!empty($paragraph->prefix))
	{
		$body .= ' <a class="section-permalink" href="#'.$paragraph->prefix_anchor.'">¶</a>';
	}
	if ($paragraph->type == 'section')
	{
		$body .= '</p>';
	}
	elseif ($paragraph->type == 'table')
	{
		$body .= '</pre>';
	}
	
	# If our next prefix is different than the current prefix, than terminate this section.
	if ( !isset($paragraph->next_prefix) || ($paragraph->entire_prefix != $paragraph->next_prefix) )
	{
		$body .= '</section>';
	}
	
	$i++;
}

// If we have stored history for this section, display it.
if (isset($law->history_text))
{
	$body .= '<section id="history">
				<h2>History</h2>
				<p>'.$law->history_text.'</p>';
}

$body .= '</section>';

// Indicate the conclusion of the "section" article, which is the container for the text of a
// section of the code.
$body .= '</article>';


$sidebar = '<iframe src="//www.facebook.com/plugins/like.php?href='.urlencode($_SERVER['REQUEST_URI'])
			.'&amp;send=false&amp;layout=standard&amp;width=1-0&amp;show_faces=false&amp;'
			.'action=recommend&amp;colorscheme=light&amp;font&amp;height=35" scrolling="no"
			frameborder="0" style="border:none; overflow:hidden; width:100px; height:35px;"
			allowTransparency="true"></iframe>';

# Only show the history if the law hasn't been repealed. (If it has been, then the history text
# generally disappears along with it, meaning that the below code can behave unpredictably.)
if (empty($law->repealed) || ($law->repealed != 'y'))
{
	$sidebar .= '
			<section id="history-description">
				<h1>History</h1>
				<p>
					This law was first passed in '.reset($law->amendation_years).'.';
	if (count((array) $law->amendation_years) > 1)
	{
		$sidebar .= ' It was updated in ';
	
		// Iterate through every year in which this bill has been amended and list them.
		foreach ($law->amendment_years as $year)
		{
			if ($year == reset($law->amendation_years))
			{
				continue;
			}
			if ( ($year == end($law->amendation_years)) && (count((array)$law->amendation_years) > 2) )
			{
				$sidebar .= 'and ';
			}
			$sidebar .= $year;
			if ($year != end($law->amendation_years))
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

if ($law->amendment_attempts != false)
{
	# Set the variable that we'll to maintain the state of the year as we loop through the bills.
	$tmp = '';
	$sidebar .= '
			<section id="amendment-attempts">
				<h1>Amendment Attempts</h1>
				<ul>';
	foreach ($law->amendment_attempts as $attempt)
	{
		# If we're dealing with a new year.
		if ($tmp != $attempt->year)
		{
			if (!empty($tmp))
			{
				$sidebar .= '</ul></li>';
			}
			$sidebar .= '<li><span>'.$attempt->year.'</span><ul>';
			$tmp = $attempt->year;
		}
		$sidebar .= '<li class="'.$attempt->outcome.'"><a class="bill" href="'.$attempt->url.'">'
			.$attempt->number.'</a>: '.$attempt->catch_line;
		if (!empty($attempt->outcome))
		{
			$sidebar .= ' ('.$attempt->outcome.')';
		}
		$sidebar .= '</li>';
	}
	$sidebar .= '</ul>
			</section>';
}

// If this section has been cited in any court decisions, list them.
if ($law->court_decisions != false)
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

if ($law->references !== false)
{

	$sidebar .= '
			<section id="references">
				<h1>Cross References</h1>
				<ul>';
	foreach ($law->references as $reference)
	{
		$sidebar .= '<li>'.SECTION_SYMBOL.'&nbsp;<a href="'.$reference->url.'" class="law">'
			.$reference->section.'</a> '.$reference->catch_line.'</li>';
	}
	$sidebar .= '</ul>
			</section>';
}

if (isset($law->related) && (count((array) $law->related) > 0))
{
	$sidebar .= '			  
			<section id="related-links">
				<h1>Related Laws</h1>
				<ul id="related">';
	foreach ($law->related as $related)
	{
		$sidebar .= '<li>'.SECTION_SYMBOL.'&nbsp;<a href="'.$related->url.'">'
-			.$related->section_number.'</a> '.$related->catch_line.'</li>';
	}
	$sidebar .= '
				</ul>
			</section>';
}

$sidebar .= '<section id="cite-as">
				<h1>Cite As</h1>
				<p>Official: <span class="official">'.$law->citation->official.'</span><br />
				Universal: <span class="universal">'.$law->citation->universal.'</span></p>
			</section>';

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

// Put the shorthand $body variable into its proper place.
$template->field->body = $body;
unset($body);

// Put the shorthand $sidebar variable into its proper place.
$template->field->sidebar = $sidebar;
unset($sidebar);

// Parse the template, which is a shortcut for a few steps that culminate in sending the content
// to the browser.
$template->parse();

?>