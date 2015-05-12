<?php

/**
 * The Search page, handling search requests.
 *
 * PHP version 5
 *
 * @license		http://www.gnu.org/licenses/gpl.html GPL 3
 * @version		0.9
 * @link		http://www.statedecoded.com/
 * @since		0.8
 *
 */

/*
 * Intialize Solarium and instruct it to use the correct request handler.
 */
$client = new SearchIndex(
	array(
		'config' => json_decode(SEARCH_CONFIG, TRUE)
	)
);

/*
 * Create a container for our content.
 */
$content = new Content();

/*
 * Define some page elements.
 */
$content->set('browser_title', 'Search');
$content->set('page_title', 'Search');

/*
 * Initialize our two primary content variables.
 */
$body = '';
$sidebar = '';

/*
 * Set our API key as a JavaScript variable, to be used by our autocomplete JavaScript.
 */
$content->set('javascript', "var api_key = '" . API_KEY . "';");

/*
 * Create a new instance of our search class. We use this to display the search form and the result
 * page numbers.
 */
$search = new Search();

/*
 * If a search is being submitted.
 */
if (!empty($_GET['q']))
{

	/*
	 * Localize the search string, filtering out unsafe characters.
	 */
	$q = filter_input(INPUT_GET, 'q', FILTER_SANITIZE_STRING);

	/*
	 * If a page number has been specified, include that. Otherwise, it's page 1.
	 */
	if (!empty($_GET['p']))
	{
		$page = filter_input(INPUT_GET, 'p', FILTER_SANITIZE_STRING);
	}
	else
	{
		$page = 1;
	}

	/*
	 * If the number of results to display per page has been specified, include that. Otherwise,
	 * the default is 10.
	 */
	if (!empty($_GET['num']))
	{
		$per_page = filter_input(INPUT_GET, 'num', FILTER_SANITIZE_STRING);
	}
	else
	{
		$per_page = 10;
	}

	/*
	 * Filter by edition.
	 */
	$edition_id = '';
	$edition = new Edition();

	if(!empty($_GET['edition_id']))
	{
		$edition_id = filter_input(INPUT_GET, 'edition_id', FILTER_SANITIZE_STRING);
	}
	$content->set('current_edition', $edition_id);

	/*
	 * Display our search form.
	 */
	$search->query = $q;
	$body .= $search->display_form($edition_id);

	/*
	 * Execute the query.
	 */
	try
	{
		$results = $client->search(
			array(
				'q' => decode_entities($q),
				'edition_id' => $edition_id,
				'page' => $page,
				'per_page' => $per_page
			)
		);
	}
	catch (Exception $error)
	{
		$error_message = 'Search failed with the error "' . $error->getMessage() .'". ';
		$error_message .= 'Please try again later.';

		unset($results);
	}

	/*
	 * If any portion of this search term appears to be misspelled, propose a properly spelled
	 * version.
	 */
	if (isset($results) && $results->get_fixed_spelling() !== FALSE)
	{

		$body .= '<h1>Suggestions</h1>';

		$suggested_q = $results->get_fixed_spelling();
		$body .= '<p>Did you mean “<a href="/search/?' . $results->get_fixed_query() . '">'
			. $suggested_q . '</a>”?</p>';

	}

	/*
	 * If there are no results.
	 */
	if (!isset($results) || $results->get_count() < 1)
	{
		if(isset($error_message))
		{
			$body .= $error_message;
		}
		else {
			$body .= '<p>No results found.</p>';
		}

	}

	/*
	 * If there are results, display them.
	 */
	else
	{

		/*
		 * Start the DIV that stores all of the search results.
		 */
		$body .= '
			<div class="search-results">
			<p>' . number_format($results->get_count()) . ' results found.</p>
			<ul>';

		/*
		 * Iterate through the results.
		 */
		global $db;
		$law = new Law(array('db' => $db));

		foreach ($results->get_results() as $result)
		{
			$law->law_id = $result->law_id;
			$law->get_law();

			$url = $law->get_url( $result->law_id );
			$url_string = $url->url;

			if(strpos($url, '?') !== FALSE)
			{
				$url_string .= '?';
			}
			else
			{
				$url_string .= '*';
			}
			$url_string .= 'q='.urlencode($q);

			$body .= '<li><div class="result">';
			$body .= '<h1><a href="' . $url_string . '">';

			if(strlen($result->catch_line))
			{
				$body .= $result->catch_line;
			}
			else
			{
				$body .= $law->catch_line;
			}

			$body .= ' (' . SECTION_SYMBOL . '&nbsp;';
			if(strlen($result->section_number))
			{
				$body .= $result->section_number;
			}
			else
			{
				$body .= $law->section_number;
			}
			$body .= ')</a></h1>';

			/*
			 * If we're searching all editions, show what edition this law is from.
			 */
			if(!strlen($edition_id))
			{
				$law_edition = $edition->find_by_id($law->edition_id);
				$body .= '<div class="edition_heading edition">' . $law_edition->name . '</div>';
			}

			/*
			 * Display this law's structural ancestry as a breadcrumb trail.
			 */
			$body .= '<div class="breadcrumbs"><ul>';

			foreach (array_reverse((array) $law->ancestry) as $structure)
			{
				$body .= '<li><a href="' . $structure->url . '">' . $structure->identifier . ' ' .
					$structure->name . '</a></li>';
			}
			$body .= '</ul></div>';

			/*
			 * Attempt to display a snippet of the indexed law, highlighting the use of the search
			 * terms within that text.
			 */

			if (isset($result->highlight) && !empty($result->highlight))
			{

				foreach ($result->highlight as $field => $highlight)
				{
					$body .= strip_tags( implode(' .&thinsp;.&thinsp;. ', $highlight), '<span>' )
						. ' .&thinsp;.&thinsp;. ';
				}

				/*
				 * Use an appropriate closing ellipsis.
				 */
				if (substr($body, -22) == '. .&thinsp;.&thinsp;. ')
				{
					$body = substr($body, 0, -22) . '.&thinsp;.&thinsp;.&thinsp;.';
				}
				$body = trim($body);

			}

			/*
			 * If we can't get a highlighted snippet, just show the first few lines of the law.
			 */
			else
			{
				$text = isset($result->text) ? $result->text : $law->full_text;
				$body .= '<p>' . substr($text , 0, 250) . ' .&thinsp;.&thinsp;.</p>';
			}

			/*
			 * End the display of this single result.
			 */
			$body .= '</div></li>';

		}

		/*
		 * End the UL that lists the search results.
		 */
		$body .= '</ul>';

		/*
		 * Display page numbers at the bottom, if we have more than one page of results.
		 */
		if ($results->get_count() > $per_page)
		{
			$search->total_results = $results->get_count();
			$search->per_page = $per_page;
			$search->page = $page;
			$search->query = $q;
			$body .= $search->display_paging();
		}

		/*
		 * Close the #search-results div.
		 */
		$body .= '</div>';

		/*
		 * If there is a next and/or previous page of results, include that in the HTML head.
		 */
		if (isset($search->prev))
		{
			$content->append('link_rel', '<link rel="prev" title="Previous" href="' . $search->prev . '" />');
		}
		if (isset($search->next))
		{
			$content->append('link_rel', '<link rel="next" title="Next" href="' . $search->next . '" />');
		}

	}

}

/*
 * If a search isn't being submitted, but the page is simply being loaded fresh.
 */
else
{

	$body .= $search->display_form();

}

/*
 * Always display a guide to writing searches, whether or not a search is being submitted.
 */
$sidebar .= '
	<section class="info-box" id="boolean">
		<h1>Writing Searches</h1>

		<p>Generally, you can just write a few words to describe the law you’re looking for, such
		as “<code>radar detectors</code>”, “<code>insurance agents</code>”, or
		“<code>assault</code>” (leaving out the quotation marks).</p>

		<p>Also, advanced searches are supported, using the following terms:</p>

		<ul>
			<li><code>AND</code>: Requires the words or phrases before and after the
				<code>AND</code>, like <code>radar AND vehicle</code>.</li>
			<li><code>+</code>: Requires that the following word or phrase be in the law, like
				<code>insurance +agent</code>.</li>
			<li><code>NOT</code>: Requires that the following word or phrase <em>not</em> be in the
				law, like <code>assault NOT battery</code>.</li>
			<li><code>OR</code>: Requires that either word or phrase (or both words or phrases) be
				in the law, like <code>assault OR battery</code>.</li>
		</ul>
	</section>';

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


/*
 * Fire up our templating engine.
 */
$template = Template::create();

/*
 * Parse the template, which is a shortcut for a few steps that culminate in sending the content
 * to the browser.
 */
$template->parse($content);
