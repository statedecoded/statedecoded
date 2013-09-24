<?php

/**
 * The Search page, handling search requests.
 *
 * PHP version 5
 *
 * @author		Waldo Jaquith <waldo at jaquith.org>
 * @copyright	2010-2013 Waldo Jaquith
 * @license		http://www.gnu.org/licenses/gpl.html GPL 3
 * @version		0.8
 * @link		http://www.statedecoded.com/
 * @since		0.8
 *
 */

/*
 * Intialize Solarium.
 */
$client = new Solarium_Client($config);

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
	 * Set up our query.
	 */
	$query = $client=>createSelect();
	$query->setQuery($q);
	
	/*
	 * We want the most useful bits highlighted as search results snippets.
	 */
// create search CSS that styles <em></em> to highlight matches
	$hl = $query->getHighlighting();
	$hl->setFields('catch_line, text');
	$hl->setSimplePrefix('<span>');
	$hl->setSimplePostfix('</span>');
	
	/*
	 * Specify which page we want, and how many results.
	 */
	$query->setStart(($page - 1) * $per_page)->setRows($per_page);
	
	/*
	 * Execute the query.
	 */
	$results = $client->select($query);
	
	/*
	 * Display highlighted uses of the search terms in a preview of the result.
	 */
	$highlighted = $results->getHighlighting();
	
	/*
	 * If there are no results.
	 */
	if (count($results) == 0)
	{
		$body .= '<p>No results found.';
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
			<div id="search-results">
			<p>' . number_format($total_results) . ' results found.</p>
			<ul>';
		
		/*
		 * Iterate through the results.
		 */
		foreach ($results as $result)
		{
			
			$body .= '<li><div class="result">';
			$body .= '<h1>' . $result->catch_line . ' (' . SECTION_SYMBOL . '&nbsp;'
				. $result->section . ')</h1>';
			
			/*
			 * Attempt to display a snippet of the indexed law, highlighting the use of the search
			 * terms within that text.
			 */
			$snippet = $highlighted->getResult($result->id);
			if ($snippet != FALSE)
			{
				foreach ($snippet as $field => $highlight)
				{
					$body .= '<p>' . implode(' [.&thinsp;.&thinsp;.] ', $highlight) . '</p>';
				}
			}
			
			/*
			 * If we can't get a highlighted snippet, just show the first few lines of the law.
			 */
			else
			{
				$body .= '<p>' . substr($result->text, 250) . ' .&thinsp;.&thinsp;.</p>';
			}
			$body .= '</div></li>';
// include breadcrumbs (class "breadcrumb")
		
		}
		
		/*
		 * Display paging.
		 */
// Create CSS to style paging.
		$body .= '<ul id="paging">';
		
		/*
		 * How many pages are there in all?
		 */
		$total_pages = ceil($total_results / $per_page);
		
		/*
		 * Figure out the window for search results. That is, if there are more than 10 pages, then
		 * we need to start someplace 
		 */
		if ( ($total_pages > 10) && ($page > 5) )
		{
			$first_page = $page - 4;
		}
		else
		{
			$first_page = 0;
		}
		
		/*
		 * Iterate through each page of results.
		 */
		for ($i = $first_page; $i < $total_pages; $i+=$per_page)
		{
			
			/*
			 * Assemble the URL for this result.
			 */
			$url = '?q=' . $q;
			
			/*
			 * Embed a page number in the URL for every page after the first one.
			 */
			if ($i > 0)
			{
				$url .= '&amp;p=' . $i + 1;
			}
			
			/*
			 * And if the number of results per page is something other than the default of 10, then
			 * include that in the URL, too.
			 */
			if ($per_page <> 10)
			{
				$url .= '&amp;per_page=' . $per_page;
			}
			
			/*
			 * If this is not the current page, display a linked number.
			 */
			if ( ($i + 1) != $page)
			{
				$body .= '<li><a href="' . $url . '">' . ($i + 1) . '</a></li>';
			}
			
			/*
			 * If this is the page that we're on right now, display an unlinked number.
			 */
			else
			{
				$body .= '<li>' .  ($i + 1) . '</li>';
			}
			
		}
		
		/*
		 * Close the #paging DIV.
		 */
		$body .= '</ul>';
		
		/*
		 * Close the #search-results div.
		 */
		$body .= '</div>';
	
	}
	
}

/*
 * If a search isn't being submitted, but the page is simply being loaded fresh.
 */
else
{

	

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
