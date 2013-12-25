<?php

/**
 * The Search page, handling search requests.
 *
 * PHP version 5
 *
 * @license		http://www.gnu.org/licenses/gpl.html GPL 3
 * @version		0.8
 * @link		http://www.statedecoded.com/
 * @since		0.8
 *
 */

/*
 * Intialize Solarium and instruct it to use the correct request handler.
 */
$client = new Solarium_Client($GLOBALS['solr_config']);

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
	 * Display our search form.
	 */
	$search->query = $q;
	$body .= $search->display_form();
	
	/*
	 * Set up our query.
	 */
	$query = $client->createSelect();
	$query->setHandler('search');
	$query->setQuery($q);
	
	/*
	 * We want the most useful bits highlighted as search results snippets.
	 */
	$hl = $query->getHighlighting();
	$hl->setFields('catch_line, text');
	$hl->setSimplePrefix('<span>');
	$hl->setSimplePostfix('</span>');

	/*
	 * Check the spelling of the query and suggest alternates.
	 */
	$spellcheck = $query->getSpellcheck();
	$spellcheck->setQuery($q);
	$spellcheck->setBuild(TRUE);
	$spellcheck->setCollate(TRUE);
	$spellcheck->setExtendedResults(TRUE);
	$spellcheck->setCollateExtendedResults(TRUE);
	
	/*
	 * Specify which page we want, and how many results.
	 */
	$query->setStart(($page - 1) * $per_page)->setRows($per_page);
	
	/*
	 * Execute the query.
	 */
	$results = $client->select($query);
	
	/*
	 * Gather highlighted uses of the search terms, which we may use in display results.
	 */
	$highlighted = $results->getHighlighting();
	
	/*
	 * If any portion of this search term appears to be misspelled, propose a properly spelled
	 * version.
	 */
	$spelling = $results->getSpellcheck();
	if ($spelling->getCorrectlySpelled() == FALSE)
	{
		
		/*
		 * We're going to modify the provided query to suggest a better one, so duplicate $q.
		 */
		$suggested_q = $q;
		
		$body .= '<h1>Suggestions</h1>';
		
		/*
		 * Step through each term that appears to be misspelled, and create a modified query string.
		 */
		foreach($spelling as $suggestion)
		{
			$str_start = $suggestion->getStartOffset();
			$str_end = $suggestion->getEndOffset();
			$original_string = substr($q, $str_start, $str_end);
			$suggested_q = str_replace($original_string, $suggestion->getWord(), $suggested_q);
		}
		
		$body .= '<p>Did you mean “<a href="/search/?q=' . urlencode($suggested_q) . '">'
			. $suggested_q . '</a>”?</p>';
		
	}
	
	/*
	 * See if the search term consists of a section number.
	 */
	if (preg_match(SECTION_REGEX, $q) == TRUE)
	{
	
		/*
		 * If this is an actual section number that exists in the law, provide a link to it.
		 */
		$law = new Law();
		$law->section_number = $q;
		if ($law->exists() === TRUE)
		{
			$body .= '
			<p><a href="' . $law->url . '">Were you looking for ' . SECTION_SYMBOL . '&nbsp;'
					. $law->section_number . '?</a></p>';
		}
		
	}
	
	/*
	 * If there are no results.
	 */
	if (count($results) == FALSE)
	{
		
		$body .= '<p>No results found.</p>';
		
	}
	
	/*
	 * If there are results, display them.
	 */
	else
	{
	
		/*
		 * Store the total number of documents returned by this search.
		 */
		$total_results = $results->getNumFound();
		
		/*
		 * Start the DIV that stores all of the search results.
		 */
		$body .= '
			<div class="search-results">
			<p>' . number_format($total_results) . ' results found.</p>
			<ul>';
		
		/*
		 * Iterate through the results.
		 */
		$law = new Law;
		foreach ($results as $result)
		{
			
			$url = $law->get_url($result->section);
			
			$body .= '<li><div class="result">';
			$body .= '<h1><a href="' . $url . '">' . $result->catch_line . ' (' . SECTION_SYMBOL . '&nbsp;'
				. $result->section . ')</a></h1>';
			
			/*
			 * Display this law's structural ancestry as a breadcrumb trail.
			 */
			$body .= '<div class="breadcrumbs"><ul>';
			$ancestry = explode('/', $result->structure);
			foreach ($ancestry as $structure)
			{
				$body .= '<li><a>' . $structure . '</a></li>';
			}
			$body .= '</ul></div>';
			
			/*
			 * Attempt to display a snippet of the indexed law, highlighting the use of the search
			 * terms within that text.
			 */
			$snippet = $highlighted->getResult($result->id);
			if ($snippet != FALSE)
			{
			
				foreach ($snippet as $field => $highlight)
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
				$body .= '<p>' . substr($result->text, 250) . ' .&thinsp;.&thinsp;.</p>';
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
		if ($total_results > $per_page)
		{
			$search->total_results = $total_results;
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
