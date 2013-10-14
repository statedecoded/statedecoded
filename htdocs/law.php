<?php

/**
 * The page that displays an individual law.
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

/*
 * Create a new instance of Law.
 */
$laws = new Law();

/*
 * Use the ID passed to look up the law.
 */
if ( isset($args['relational_id']) )
{

	$laws->law_id = filter_var($args['relational_id'], FILTER_SANITIZE_STRING);

	/*
	 * Retrieve a copy of the law.
	 */
	$law = $laws->get_law();

}

if (!isset($law) || $law === FALSE)
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
	 * Send the text, which is already formatted properly.
	 */
	echo $law->plain_text;

	/*
	 * End processing and exit.
	 */
	exit;
}

/*
 * Create a container for our content.
 */
$content = new Content();

/*
 * Make some section information available globally to JavaScript.
 */
$content->set('javascript', "var section_number = '" . $law->section_number . "';");
$content->append('javascript', "var section_id = '" . $law->section_id . "';");
$content->append('javascript', "var api_key = '" . API_KEY . "';");

/*
 * Define the browser title.
 */
$content->set('browser_title', $law->catch_line . ' (' . SECTION_SYMBOL . ' '
	. $law->section_number . ')—' . SITE_TITLE);

/*
 * Define the page title.
 */
$content->set('page_title', '<h1>' . SECTION_SYMBOL . '&nbsp;' . $law->section_number . '</h1>');
$content->append('page_title', '<h2>' . $law->catch_line . '</h2>');

/*
 * If we have Dublin Core metadata, display it.
 */
if (is_object($law->dublin_core))
{
	$content->set('meta_tags', '');
	foreach ($law->dublin_core AS $name => $value)
	{
		$content->append('meta_tags', '<meta name="DC.' . $name . '" content="' . $value . '" />');
	}
}

/*
 * Define the breadcrumb trail text.
 */
$content->set('breadcrumbs', '');
foreach (array_reverse((array) $law->ancestry) as $ancestor)
{
	$content->append('breadcrumbs', '<li><a href="'.$ancestor->url.'">'.$ancestor->identifier.' '
		.$ancestor->name.'</a></li>');
}
$content->append('breadcrumbs', '<li class="active"><a href="/'.$law->section_number.'/">§&nbsp;' .
	 $law->section_number.' '.$law->catch_line.'</a></li>');

$content->prepend('breadcrumbs', '<nav class="breadcrumbs"><ul class="steps-nav">');
$content->append('breadcrumbs', '</ul></nav>');

/*
 * If there is a prior section in this structural unit, provide a back arrow.
 */
if (isset($law->previous_section))
{
	$content->set('prev_next', '<li><a href="' . $law->previous_section->url .
		'" class="prev" title="Previous section"><span>&larr; Previous</span>' .
		$law->previous_section->section_number . ' ' .
		$law->previous_section->catch_line . '</a></li>');
	$content->append('link_rel', '<link rel="prev" title="Previous" href="' .
		$law->previous_section->url . '" />');
}
else
{
	$content->set('prev_next', '<li></li>');
}

/*
 * If there is a next section in this chapter, provide a forward arrow.
 */
if (isset($law->next_section))
{
	$content->append('prev_next', '<li><a href="' . $law->next_section->url .
		'" class="next" title="Next section"><span>Next &rarr;</span>' .
		$law->next_section->section_number . ' ' .
		$law->next_section->catch_line . '</a></li>');
	$content->append('link_rel', '<link rel="next" title="Next" href="' . $law->next_section->url . '" />');
}
else
{
	$content->append('prev_next', '<li></li>');
}

$content->set('heading', '<nav class="prevnext" role="navigation"><ul>' .
	$content->get('prev_next') . '</ul></nav><nav class="breadcrumbs" role="navigation">' .
	$content->get('breadcrumbs') . '</nav>');

/*
 * Store the URL for the containing structural unit.
 */
$content->append('link_rel', '<link rel="up" title="Up" href="' . $law->ancestry->{1}->url . '" />');

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
        <ul class="nav nav-tabs">
          <li class="active"><a href="#">Translated</a></li>
          <li class="active"><a href="#">Original</a></li>
        </ul>
        <div class="tab-content">
          <div class="tab-pane active" id="tab1">
    				<p>'.$law->history_text.'</p>
          </div>
          <div class="tab-pane" id="tab2">
          </div>
				</section>';
}

/*
 * Representational variants are icons that link to various types of ways to read or download the law.
 */

$body .= '<section id="rep_variant">
            <p>You can download this file as:</p>
            <ul>
              <li><a href=""><img src="/themes/StateDecoded2013/static/images/icon_doc_32.png" alt="Word Doc"></a></li>
              <li><a href=""><img src="/themes/StateDecoded2013/static/images/icon_epub_32.png" alt="ePub"></a></li>
              <li><a href=""><img src="/themes/StateDecoded2013/static/images/icon_json_32.png" alt="JSON"></a></li>
              <li><a href=""><img src="/themes/StateDecoded2013/static/images/icon_pdf_32.png" alt="PDF"></a></li>
              <li><a href=""><img src="/themes/StateDecoded2013/static/images/icon_rtf_32.png" alt="RTF (Rich Text Format)"></a></li>
              <li><a href=""><img src="/themes/StateDecoded2013/static/images/icon_txt_32.png" alt="TXT (Text File)"></a></li>
            </ul>
          </section>';

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
 * Only show the history if the law has a list of amendment years.
 */
if ( isset($law->amendment_years) )
{
	$sidebar .= '
			<section id="history-description">
				<h1>History</h1>
				<p>
					This law was first passed in ' . reset($law->amendment_years) . '.';
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
	$body .= "
	<section id=\"comments\">
		<h2>Comments</h2>
		<div id=\"disqus_thread\"></div>
		<script>
			var disqus_shortname = '" . DISQUS_SHORTNAME . "'; // required: replace example with your forum shortname

			/* * * DON'T EDIT BELOW THIS LINE * * */
			(function() {
				var dsq = document.createElement('script'); dsq.type = 'text/javascript'; dsq.async = true;
				dsq.src = 'http://' + disqus_shortname + '.disqus.com/embed.js';
				(document.getElementsByTagName('head')[0] || document.getElementsByTagName('body')[0]).appendChild(dsq);
			})();
		</script>
	</section>";
}

/*
 * General info
 */
$sidebar .= '<section class="info-box" id="elsewhere">
				<h1>Trust, But Verify</h1>
				<p>If you’re reading this for anything important, you should double-check its
				accuracy';
if (isset($law->official_url))
{
	$sidebar .= '—<a href="' . $law->official_url . '">read ' . SECTION_SYMBOL . '&nbsp;'
		. $law->section_number . ' ';
}
$sidebar .= ' on the official ' . LAWS_NAME . ' website</a>.
				</p>
			</section>';

/*
 * Start the Masonry.js wrapper
 */
$sidebar .= '<div class="grouping js-masonry"
                  data-masonry-options=\'{
                    "itemSelector": ".grid-box",
                    "columnWidth": ".grid-sizer",
                    "gutter": 10 }\'>';

/*
 * Get the help text for the requested page.
 */
$help = new Help();

// The help text is now available, as a JSON object, as $help->get_text()

$sidebar .= '<p class="keyboard"><a id="keyhelp">' . $help->get_text('keyboard')->title . '</a></p>';


/*
 * If this section has been cited in any court decisions, list them.
 */
if ( isset($law->court_decisions) && ($law->court_decisions != FALSE) )
{
	$sidebar .= '<section class="grid-box grid-sizer" id="court-decisions">
				<h1>Court Decisions</h1>
				<ul>';
	foreach ($law->court_decisions as $decision)
	{
		$sidebar .= '<li><a href="' . $decision->url . '"><em>' . $decision->name . '</em></a> ('
			. $decision->type_html . ', ' . date('m/d/y', strtotime($decision->date)) . ')<br />'
			. $decision->abstract . '</li>';
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
			<section class="related-group grid-box" id="cross_references">
				<h1>Cross References</h1>
				<ul>';
	foreach ($law->references as $reference)
	{
		$sidebar .= '<li><span class="identifier">'
			. SECTION_SYMBOL . '&nbsp;<a href="' . $reference->url . '" class="law">'
			. $reference->section_number . '</a></span>
			<span class="title">' . $reference->catch_line . '</li>';
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
			<section class="related-group grid-box" id="related-links">
				<h1>Related Laws</h1>
				<ul id="related">';
	foreach ($law->related as $related)
	{
		$sidebar .= '<li>' . SECTION_SYMBOL . '&nbsp;<a href="' . $related->url . '">'
			. $related->section_number . '</a> ' . $related->catch_line . '</li>';
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

	$sidebar .= '<section class="related-group dark grid-box" id="cite-as">
				<h1>Cite As</h1>
				<ul>';
	foreach ($law->citation as $citation)
	{
		$sidebar .= '<li>' . $citation->label . ': <span class="' . strtolower($citation->label) . '">'
			. $citation->text . '</span></li>';
	}
	$sidebar .= '</ul>
			</section>';

}

/*
 * End Masonry.js wrapper
 */
$sidebar .= '</section>';

/*
 * Put the shorthand $body variable into its proper place.
 */
$content->set('body', $body);
unset($body);

/*
 * Put the shorthand $sidebar variable into its proper place.
 */
$content->set('sidebar', $sidebar);
unset($sidebar);

/*
 * Add the custom classes to the body.
 */
$content->set('body_class', 'law inside');
$content->set('content_class', 'nest wide');

