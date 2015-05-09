<?php

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
		if ($this->spelling->getCorrectlySpelled() == FALSE)
		{
			/*
			 * Step through each term that appears to be misspelled, and create a modified query string.
			 */
			$clean_spelling = $this->query['term'];
			foreach($this->spelling as $suggestion)
			{
				$str_start = $suggestion->getStartOffset();
				$str_end = $suggestion->getEndOffset();
				$original_string = substr($this->query['term'], $str_start, $str_end);
				$clean_spelling = str_replace($original_string, $suggestion->getWord(), $clean_spelling);
			}
			return $clean_spelling;
		}
		else
		{
			return FALSE;
		}
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
			// Add highlighting
			$temp_result->highlight = $highlighted->getResult($result->id);

			$fixed_results[] = $temp_result;
		}

		return $fixed_results;
	}

}
