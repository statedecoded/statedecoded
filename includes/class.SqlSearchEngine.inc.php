<?php

/**
 * Wrapper class for searching sql engines.
 *
 * A barebones search client using the existing database.
 * Feel free to use this as a base to create new search adapters!
 *
 * PHP version 5
 *
 * @license		http://www.gnu.org/licenses/gpl.html GPL 3
 * @version		0.9
 * @link		http://www.statedecoded.com/
 * @since		0.9
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
		$law_fields = array();
		$law_fields[] = 'laws.id';
		$law_fields[] = 'laws.catch_line AS name';
		$law_fields[] = '"law" AS object_type';
		$law_where = array();

		$structure_fields = array();
		$structure_fields[] = 'structure.id';
		$structure_fields[] = 'structure.name';
		$structure_fields[] = '"structure" AS object_type';
		$structure_where = array();

		$order = array();

		$query_args = array();

		if(isset($query['q']))
		{
			/*
			 * If we have a search term, we first look for the term as an
			 * individual word (using word boundaries and REGEXP). This is
			 * weighted the highest for search results - first in the title,
			 * then in the text.
			 */

			# TODO: Search structure->metadata->text for matches. Since we're using
			# PHP's serializer, we can't do this natively in SQL at the moment.

			$law_where_or = array();
			$structure_where_or = array();

			$law_where_or[] = 'section = :term';
			$structure_where_or[] = 'name = :term';
			$query_args[':term'] = $query['q'];

			// Remove any quotes, and apply our regexp word boundaries.
			$query_args[':term_boundary'] = SqlSearchEngine::word_boundary(
				str_replace('"', '', $query['q'])
			);

			// Add our query
			$law_where_or[] = 'catch_line REGEXP :term_boundary';
			$law_where_or[] = 'text REGEXP :term_boundary';
			$structure_where_or[] = 'name REGEXP :term_boundary';

			// Add some fields to use in the order
			$law_fields[] = 'catch_line REGEXP :term_boundary AS title_exect_match';
			$law_fields[] = 'text REGEXP :term_boundary AS text_exect_match';
			$structure_fields[] = 'name REGEXP :term_boundary AS title_exact_match';
			$structure_fields[] = '0 AS text_exact_match';

			// Order.  In the end, we want 1) exact title, 2) any title, 3) exact text,
			// 4) any text.  This properly benefits structures as well as laws.
			$order[0] = 'title_exect_match DESC';
			$order[2] = 'text_exect_match DESC';

			/*
			 * If we have tokenized matches turned on, break the words up into
			 * tokens and search for each.  This is more resource-intensive!
			 * This is weighted lower, but still first in the title, then in the
			 * text.
			 */
			if($this->use_token_match && strpos($query['q'], ' ') !== FALSE)
			{
				// The function below handles quoted items.
				$keywords = SqlSearchEngine::tokenize($query['q']);

				// Only do this search if we have more than one keyword.
				// Otherwise, we still only care about an exact match.

				if(count($keywords > 1))
				{
					list($title_search, $new_args) =
						SqlSearchEngine::build_keyword_search(
						'catch_line', $keywords);
					$query_args = array_merge($query_args, $new_args);

					$law_where_or[] = join(' OR ', $title_search);
					$law_fields[] = '( ' .
						join(' + ', array_map('SqlSearchEngine::ifify', $title_search))
						. ' ) AS title_match';


					list($text_search, $new_args) =
						SqlSearchEngine::build_keyword_search(
						'text', $keywords);
					$query_args = array_merge($query_args, $new_args);

					$law_where_or[] = join(' OR ', $text_search);
					$law_fields[] = '( ' .
						join(' + ', array_map('SqlSearchEngine::ifify', $text_search))
						. ' ) AS text_match';

					list($title_search, $new_args) =
						SqlSearchEngine::build_keyword_search(
						'name', $keywords);
					$query_args = array_merge($query_args, $new_args);

					$structure_where_or[] = join(' OR ', $title_search);
					$structure_fields[] = '( ' .
						join(' + ', array_map('SqlSearchEngine::ifify', $title_search))
						. ' ) AS title_match';

					$structure_fields[] = '"" AS text_match';

					$order[1] = 'title_match DESC';
					$order[3] = 'text_match DESC';
				}
			}

			if(count($law_where_or))
			{
				$law_where[] = '(' . join(' OR ', $law_where_or) . ')';
			}
			if(count($structure_where_or))
			{
				$structure_where[] = '(' . join(' OR ', $structure_where_or) . ')';
			}
		}

		/*
		 * Handle editions.
		 */
		if(isset($query['edition_id']) && strlen($query['edition_id']))
		{
			$laws_where[] = 'laws.edition_id = :edition_id';
			$structure_where[] = 'structure.edition_id = :edition_id';
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
			$law_fields = array('count(*) AS count');
			$structure_fields = array('count(*) AS count');
			unset($order, $offset, $limit);
		}

		/*
		 * Assemble our final query.
		 */
		$sql_query = 'SELECT * FROM ';

			$sql_query .= '(SELECT ' . join(', ', $law_fields) . ' FROM laws ';
			if(count($law_where))
			{
				$sql_query .= 'WHERE ' . join(' AND ', $law_where) . ' ';
			}
			$sql_query .= 'UNION ';

			$sql_query .= 'SELECT ' . join(', ', $structure_fields) . ' FROM structure ';
			if(count($structure_where))
			{
				$sql_query .= 'WHERE ' . join(' AND ', $structure_where) . ' ';
			}

		$sql_query .= ') AS records ';

		if(count($order))
		{
			$sql_query .= 'ORDER BY ' . join(', ', array_filter($order)) . ' ';
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
			$i = 0;
			foreach($keywords as $keyword)
			{
				$i++;

				// Add slash escaping
				// This isn't necessary with PDO
				// $keyword = SqlSearchEngine::escape_regexp($keyword);

				// Handle wildcards
				$keyword = SqlSearchEngine::word_boundary($keyword);

				// Replace remaining wildcards.
				$keyword = str_replace('\*', '.*', $keyword);

				// Build a placeholder token for sql.
				$placeholder = ':token_' . $i;

				$fields[] = $search_field . ' REGEXP ' . $placeholder;
				$sql_args[$placeholder] = $keyword;
			}
		}

		return array($fields, $sql_args);

	}

	/*
	 * String tokenizing function.  Handles quoted strings, as well.  Useful for
	 * search queries.
	 */

	public static function tokenize($string)
	{
		$buffer = '';
		$keywords = array();
		$quote_string = FALSE;

		for($i = 0; $i< strlen($string); $i++)
		{
			if($string[$i] === '"')
			{
				if(strlen($buffer))
				{
					$keywords[] = $buffer;
					$buffer = '';
				}
				$quote_string = !$quote_string;
			}
			else
			{
				if($string[$i] === ' ' && !$quote_string)
				{
					if(strlen($buffer))
					{
						$keywords[] = $buffer;
						$buffer = '';
					}
				}
				else
				{
					$buffer .= $string[$i];
				}
			}
		}

		if(strlen($buffer))
		{
			$keywords[] = $buffer;
		}

		// Remove any empty strings.
		$keywords = array_values(array_filter($keywords));

		return $keywords;
	}

	/*
	 * We have to jump through some hoops for REGEXP escaping in SQL.
	 */
	public static function escape_regexp($string)
	{
		$return_value = preg_replace("/([.\[\]*^\$])/", '\\\$1', $string);
		$return_value = str_replace('|', '\\\\|', $return_value);
		return $return_value;
	}

	/*
	 * Add regexp word boundaries.  Handles wildcards.
	 */
	public static function word_boundary($keyword)
	{
		// At this point, *s have a slash in front of them.
		if(substr($keyword, 0, 2) == '\*')
		{
			$keyword = '.*'.substr($keyword, 2);
		}
		else
		{
			// Add front word boundary
			$keyword = '[[:<:]]'.$keyword;
		}

		if(substr($keyword, -2, 2) == '\*')
		{
			$keyword = substr($keyword, 0, -2).'.*';
		}
		else
		{
			// Add back word boundary
			$keyword .= '[[:>:]]';
		}

		return $keyword;
	}

	/*
	 * Used for counting, wraps everything in binary IF clauses.
	 */
	public static function ifify($string)
	{
		return 'IF(' . $string . ', 1,0)';
	}


}
