<?php

/**
 * The Search class, for all search-related functionality
 *
 * PHP version 5
 *
 * @license		http://www.gnu.org/licenses/gpl.html GPL 3
 * @version		0.8
 * @link		http://www.statedecoded.com/
 * @since		0.8
 *
 */

class Search
{

	/**
	 * Display the complete search form. (As opposed to the abbreviated form, which is included in
	 * the template HTML.)
	 *
	 * @returns the HTML of the form
	 */
	function display_form()
	{
		
		$this->form = '
			<div class="ui-widget">
				<form method="get" action="/search/">
					<input type="text" name="q" id="q" ';
		if (!empty($this->query))
		{
			$this->form .= 'value="' . $this->query . '"';
		}
		$this->form .= ' />';
		
		/*
		 * If we've specified the number of results that we want to display per page, instead of
		 * the default, include it here.
		 */
		if (isset($this->per_page))
		{
			$this->form .= '<input type="hidden" name="num" value="' . $this->per_page . '" />';
		}
		
		$this->form .= '<input type="submit" value="Search" />
				</form>
			</div>';
			
		return $this->form;
		
	}
	
	/**
	 * Display the links to each page of search results.
	 *
	 * @returns the HTML of the paging links
	 */
	function display_paging()
	{
		
		/*
		 * Require these properties to be set.
		 */
		if ( empty($this->total_results) || empty($this->per_page)  || empty($this->query) )
		{
			return FALSE;
		}
		
		/*
		 * Start our list of pages.
		 */
		$this->paging = '<ul id="paging">';
		
		/*
		 * How many pages are there in all?
		 */
		$total_pages = ceil($this->total_results / $this->per_page);
		
		/*
		 * Figure out the window for search results. That is, if there are more than 12 pages, then
		 * we need to start someplace other than at the first page.
		 */
		if ( ($total_pages > 12) && ($this->page > 6) )
		{
			$first_page = $this->page - 6;
		}
		else
		{
			$first_page = 0;
		}
		
		/*
		 * Iterate through each page of results.
		 */
		$j=0;
		for ($i = $first_page; $i < $total_pages; $i++)
		{
			
			/*
			 * Assemble the URL for this page.
			 */
			$url = '?q=' . $this->query;
			
			/*
			 * Embed a page number in the URL for every page after the first one.
			 */
			if ($i > 0)
			{
				$url .= '&amp;p=' . ($i + 1);
			}
			
			/*
			 * And if the number of results per page is something other than the default of 10, then
			 * include that in the URL, too.
			 */
			if ($this->per_page <> 10)
			{
				$url .= '&amp;num=' . $this->per_page;
			}
			
			/*
			 * If this is not the current page, display a linked number.
			 */
			if ( ($i + 1) != $this->page)
			{
				$this->paging .= '<li><a href="' . $url . '">' . ($i + 1) . '</a></li>';
			}
			
			/*
			 * If this is the page that we're on right now, display an unlinked number.
			 */
			else
			{
				$this->paging .= '<li>' .  ($i + 1) . '</li>';
			}
			
			/*
			 * Increment our page counter.
			 */
			$j++;
			
			/*
			 * Once we reach eleven pages, stop.
			 */
			if ($j == 11)
			{
				break;
			}
			
		}
		
		/*
		 * Close the #paging DIV.
		 */
		$this->paging .= '</ul>';
		
		return $this->paging;
		
	}
	
}
