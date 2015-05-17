<?php

/**
 * Wrapper class for Solarium results.
 *
 * PHP version 5
 *
 * @license		http://www.gnu.org/licenses/gpl.html GPL 3
 * @version		0.9
 * @link		http://www.statedecoded.com/
 * @since		0.9
 */

class SolrSearchResults implements SearchResultsInterface
{
	public $query;
	public $results;
	public $spelling;

	public function __construct($query, $results)
	{
		$this->query = $query;
		$this->results = $results;

		$this->fixed_results = $this->fix_results($results);

		$this->spelling = $results->getSpellcheck();
	}

	public function get_results()
	{
		return $this->fixed_results;
	}

	public function get_count()
	{
		return $this->results->getNumFound();
	}

	public function get_fixed_spelling()
	{
		/*
		 * If we know something is misspelled.
		 */
		if ($this->spelling->getCorrectlySpelled() === FALSE)
		{
			/*
			 * Step through each term that appears to be misspelled, and create a modified query string.
			 */
			$clean_spelling = $this->query['q'];
			foreach($this->spelling as $suggestion)
			{
				$str_start = $suggestion->getStartOffset();
				$str_end = $suggestion->getEndOffset();
				$str_length = $str_end - $str_start;

				$clean_spelling = str_splice($clean_spelling, $str_start, $str_length, $suggestion->getWord());
			}

			/*
			 * If our result is the same as the original, we couldn't find a better suggestion.
			 * In that case, return false.  This prevents false positives.
			 */

			if($clean_spelling === $this->query['q'])
			{
				return FALSE;
			}
			else
			{
				return $clean_spelling;
			}
		}
		else
		{
			return FALSE;
		}
	}

	/*
	 * Reassemble the query string for the fixed spelling.
	 */
	public function get_fixed_query()
	{
		// Get our existing query.
		$args = $this->query;

		// Reset back to page 1.
		$args['page'] = 1;

		// Fix the query term.
		$args['q'] = $this->get_fixed_spelling();

		return http_build_query($args);
	}

	protected function fix_results($results)
	{
		$highlighted = $results->getHighlighting();
		$fixed_results = array();

		foreach($results as $result)
		{
			$temp_result = new stdClass();
			foreach($result as $key => $value)
			{
				$temp_result->$key = $value;
			}

			if($highlighted)
			{
				// Add highlighting
				$temp_result->highlight = $highlighted->getResult($result->id);
			}

			$fixed_results[] = $temp_result;
		}

		return $fixed_results;
	}

}
