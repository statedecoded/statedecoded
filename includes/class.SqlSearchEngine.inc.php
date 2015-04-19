<?php

/*
 * A barebones search client using the existing database.
 * Feel free to use this as a base to create new search adapters!
 */

require_once(INCLUDE_PATH . 'class.SearchEngineInterface.inc.php');

class SqlSearchEngine extends SearchEngineInterface
{
	/*
	 * Solr Config.
	 */
	protected $config;

	/*
	 * Our database client.
	 */
	protected $db;

	/*
	 *The current transaction.
	 */
	protected $transaction;

	/*
	 * The type of transaction.
	 */
	protected $transaction_type;

	/*
	 * The documents to put into our current transaction.
	 */
	public $documents = array();

	/*
	 * Number of documents to store before automatically flushing.
	 */
	public $batch_size = 100;

	/*
	 * Hang on to our last result.
	 */
	protected $last_result;

	/*
	 * Allow tokenized matches (more expensive).
	 */
	public $use_token_match = true;

	public function __construct($args = array())
	{
		parent::__construct($args);

		if(!isset($this->db))
		{
			global $db;
			$this->db = $db;
		}
	}

	/*
	 * This engine uses the existing index, so it doesn't update via these
	 * methods.
	 */
	public function start_update()
	{
		return TRUE;
	}

	public function add_document($record)
	{
		return TRUE;
	}

	public function commit()
	{
		return TRUE;
	}

	public function debug()
	{

	}

	public function search($query = array())
	{
		/*
		 * Do our count first
		 */
		list($count_query, $count_args) = $this->build_search_query($query, true);
		$count_statement = $this->db->prepare($count_query);
		$count_statement->execute($count_args);
		$count_obj = $count_statement->fetch(PDO::FETCH_OBJ);

		/*
		 * Then get our results.
		 */
		list($sql_query, $sql_args) = $this->build_search_query($query);
		$statement = $this->db->prepare($sql_query);
		$statement->execute($sql_args);
		$results = $statement->fetchAll(PDO::FETCH_OBJ);

		/*
		 * Build our results.
		 */
		$result_object = new SqlSearchResults($query, $results);
		$result_object->count = $count_obj->count;

		return $result_object;
	}

	public function build_search_query($query, $count_query = false)
	{
		/*
		 * Set up our query.
		 */
		$fields = array();
		$fields[] = 'laws.id AS law_id';
		$fields[] = 'laws.catch_line';
		$where = array();
		$order = array();

		$query_args = array();

		if(isset($query['term']))
		{
			/*
			 * If we have a search term, we first look for the term as an
			 * individual word (using word boundaries and REGEXP). This is
			 * weighted the highest for search results - first in the title,
			 * then in the text.
			 */

			$where_or = array();

			$query_args[':term'] = '[[:<:]]' . $query['term'] . '[[:>:]]';

			// Add our query
			$where_or[] = 'catch_line REGEXP :term';
			$where_or[] = 'text REGEXP :term';

			// Add some fields to use in the order
			$fields[] = 'catch_line REGEXP :term AS title_exect_match';
			$fields[] = 'text REGEXP :term AS text_exect_match';

			// Order by
			$order[] = 'title_exect_match DESC';
			$order[] = 'text_exect_match DESC';

			/*
			 * If we have tokenized matches turned on, break the words up into
			 * tokens and search for each.  This is more resource-intensive!
			 * This is weighted lower, but still first in the title, then in the
			 * text.
			 */
			if($this->use_token_match && strpos($query['term'], ' ') !== FALSE)
			{


				$title_search = SqlSearchEngine::build_keyword_search(
					'catch_line', $query['term']);
				$where_or[] = join(' OR ', $title_search);

				$fields[] = '( ' .
					join(' + ', array_map('SqlSearchEngine::ifify', $title_search))
					. ' ) AS title_match';
				$order[] = 'title_match DESC';

				$text_search = SqlSearchEngine::build_keyword_search(
					'text', $query['term']);
				$where_or[] = join(' OR ', $text_search);
				$fields[] = '( ' .
					join(' + ', array_map('SqlSearchEngine::ifify', $text_search))
					. ' ) AS text_match';
				$order[] = 'text_match DESC';
			}

			if(count($where_or))
			{
				$where[] = '(' . join(' OR ', $where_or) . ')';
			}
		}

		/*
		 * Handle editions.
		 */
		if(isset($query['edition_id']))
		{
			$where[] = 'laws.edition_id = :edition_id';
			$query_args[':edition_id'] = $query['edition_id'];
		}

		/*
		 * Specify which page we want, and how many results.
		 */
		if(isset($query['per_page']))
		{
			$limit = $query['per_page'];

			if(isset($query['page']))
			{
				$offset = ($query['page'] - 1) * $query['per_page'];
			}
		}

		/*
		 * If this is a count query, just override our settings.
		 */
		if($count_query)
		{
			$fields = array('count(*) AS count');
			unset($order, $offset, $limit);
		}

		/*
		 * Assemble our final query.
		 */
		$sql_query = 'SELECT ' . join(', ', $fields) . ' FROM laws ';
		if(count($where))
		{
			$sql_query .= 'WHERE ' . join(' AND ', $where) . ' ';
		}
		if(count($order))
		{
			$sql_query .= 'ORDER BY ' . join(', ', $order) . ' ';
		}
		if(isset($limit))
		{
			$sql_query .= 'LIMIT ';
			if(isset($offset))
			{
				$sql_query .= $offset . ', ';
			}
			$sql_query .= $limit . ' ';
		}

		return array($sql_query, $query_args);
	}

	public static function build_keyword_search($search_field, $keywords) {
		$fields = array();

		if($keywords) {

			// The function below handles quoted items.
			if(!is_array($keywords)) {
				$keywords = SqlSearchEngine::tokenize($keywords);
			}

			foreach($keywords as $keyword) {
				// Add slash escaping
				$keyword = SqlSearchEngine::escape_regexp($keyword);

				unset($keyword_where);

				// Handle wildcards

				// At this point, *s have a slash in front of them.
				if(substr($keyword, 0, 2) == '\*') {
					$keyword = '.*'.substr($keyword, 2);
				}
				else {
					// Add front word boundary
					$keyword = '[[:<:]]'.$keyword;
				}

				if(substr($keyword, -2, 2) == '\*') {
					$keyword = substr($keyword, 0, -2).'.*';
				}
				else {
					// Add back word boundary
					$keyword .= '[[:>:]]';
				}

				// Replace remaining wildcards.
				$keyword = str_replace('\*', '.*', $keyword);

				$fields[] = $search_field.' REGEXP "'.$keyword.'"';
			}
		}


		return $fields;

	}

	/*
	 * String tokenizing function.  Handles quoted strings, as well.  Useful for
	 * search queries.
	 */

	public static function tokenize($string) {
		$buffer = '';
		$keywords = array();
		$quote_string = 0;
		for($i = 0; $i< strlen($string); $i++) {
			if($string[$i] == '"') {
				if(strlen($buffer)) {
					$keywords[] = $buffer;
					unset($buffer);
				}
				$quote_string = ! (int) $quote_string;

			}
			else {
				if($string[$i] == ' ' && !$quote_string) {
					if(strlen($buffer)) {
						$keywords[] = $buffer;
						unset($buffer);
					}
				}
				else {
					$buffer .= $string[$i];
				}
			}
		}

		if(strlen($buffer)) {
			$keywords[] = $buffer;
		}

		return $keywords;
	}

	/*
	 * We have to jump through some hoops for REGEXP escaping in SQL.
	 */
	public static function escape_regexp($string){
		$return_value = preg_replace("/([.\[\]*^\$])/", '\\\$1', $string);
		$return_value = str_replace('|', '\\\\|', $return_value);
		return $return_value;
	}

	/*
	 * Used for counting, wraps everything in binary IF clauses.
	 */
	public static function ifify($string)
	{
		return 'IF(' . $string . ', 1,0)';
	}


}
