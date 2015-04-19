<?php

interface SearchResultsInterface
{
	public function __construct($query, $results);
	public function get_results();
	public function get_fixed_spelling();
	public function get_count();
}
