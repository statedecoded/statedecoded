<?php

/**
 * The Search class, for all search-related functionality
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
			<form method="get" action="/search/">
				<input type="text" name="q" ';
		if (!empty($this->query))
		{
			$this->form .= 'value="' . $this->query . '"';
		}
		$this->form .= ' size="50" />';
		
		/*
		 * If we've specified the number of results that we want to display per page, instead of
		 * the default, include it here.
		 */
		if (isset($this->per_page))
		{
			$this->form .= '<input type="hidden" name="num" value="' . $this->per_page . '" />';
		}
		
		$this->form .= '<input type="submit" value="Search" />
			</form>';
			
		return $this->form;
		
	}
	
}
