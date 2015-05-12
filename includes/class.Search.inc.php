<?php

/**
 * The Search class, for all search-related functionality
 *
 * PHP version 5
 *
 * @license		http://www.gnu.org/licenses/gpl.html GPL 3
 * @version		0.9
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
	public function display_form($current_edition)
	{

		$this->form = '
			<div class="ui-widget search">
				<form method="get" action="/search/">
					<div class="form_field">
					<input type="text" name="q" id="q" ';
		if (!empty($this->query))
		{
			$this->form .= 'value="' . $this->query . '"';
		}
		$this->form .= ' /></div>';
		$this->form .= '<div class="form_field">';
		$this->form .= $this->build_edition($current_edition);
		$this->form .= '</div>';

		/*
		 * If we've specified the number of results that we want to display per page, instead of
		 * the default, include it here.
		 */
		if (isset($this->per_page))
		{
			$this->form .= '<input type="hidden" name="num" value="' . $this->per_page . '" />';
		}

		$this->form .= '<div class="form_field">
						<input class="btn btn-success" type="submit" value="Search" />
					</div>
				</form>
			</div>';

		return $this->form;

	}

	public function build_edition($current_edition)
	{
		$output = '';
		$editions = array();

		// Since we don't have any conditions in our template, we have to build
		// html here.
		if(!isset($current_edition))
		{
			$current_edition = EDITION_ID;
		}

		try
		{
			$edition_object = new Edition();
			$editions = $edition_object->all();
		}
		catch(Exception $error)
		{
			// It's ok if we get an error here, as this happens before we have a database setup.
			$editions = array();
		}

		if($editions && count($editions) > 1)
		{

			$output = '<select name="edition_id" id="edition_id">';
			$output .= '<option value="">Search All Editions</option>';
			foreach($editions as $edition)
			{
				$output .= '<option value="' . $edition->id .'"';

				if($edition->id == $current_edition)
				{
					$output .= ' selected="selected"';
				}
				$output .= '>' . $edition->name;

				if($edition->current)
				{
					$output .= ' (current)';
				}
				$output .= '</option>';
			}
			$output .= '</select>';
		}
		// If we only have one edition, just use it.
		elseif(count($editions) == 1)
		{
			$output .= '<input type="hidden" name="edition_id" value="' .
				$editions[0]->id .'">';
		}

		return $output;
	}

	/**
	 * Display the links to each page of search results.
	 *
	 * @returns the HTML of the paging links
	 */
	public function display_paging()
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
			 * If we have next and previous pages, store those.
			 */
			if ($i == $this->page)
			{
				$this->next = $url;
			}
			if ( ($i + 2) == $this->page)
			{
				$this->prev = $url;
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
