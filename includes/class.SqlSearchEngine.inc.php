<?php

/**
 * Wrapper class for searching sql engines.
 *
 * A barebones search client using the existing database.
 * Feel free to use this as a base to create new search adapters!
 *
 * PHP version 8
 *
 * @license		http://www.gnu.org/licenses/gpl.html GPL 3
 * @version		1.1
 * @link		https://www.statedecoded.com/
 * @since		0.9
 */

require_once(INCLUDE_PATH . 'class.SearchEngineInterface.inc.php');

class SqlSearchEngine extends SearchEngineInterface
{
	/*
	 * A match on a structural unit's name is a match on a title, which is a
	 * stronger signal than a match buried in the body of a law. Structures have
	 * only a name to match against, so their relevance score is weighted up to
	 * keep them competitive with laws in the combined ranking.
	 */
	const TITLE_RELEVANCE_WEIGHT = 2;

	/*
	 * An upper bound on a relevance score before it is weighted.
	 *
	 * The `OR section = :term` clause means MySQL cannot serve the WHERE with
	 * the full-text index, so it scans the table and evaluates MATCH per row,
	 * outside a full-text index scan. A relevance score computed that way is
	 * not always a sane float, and multiplying one by the title weight can
	 * exceed the range of a DOUBLE -- MySQL then aborts the whole query with
	 * error 1690 rather than returning rows, taking search down.
	 *
	 * Clamping before the multiplication bounds the arithmetic. Real scores
	 * are orders of magnitude below this, so ranking is unaffected.
	 */
	const MAX_RELEVANCE_SCORE = 1000;

	/*
	 * InnoDB will not index tokens shorter than innodb_ft_min_token_size. The
	 * default is 3, and the search falls back to REGEXP when a query has no
	 * token at least this long. Read from the server at run time, since a
	 * deployment may have configured it differently.
	 */
	protected $min_token_size;

	/*
	 * The indexer's stopword list, read once and reused.
	 */
	protected $stopword_cache;

	/*
	 * Whether the full-text indexes this engine searches against exist, read
	 * once and reused. They are added by a migration, not by the baseline
	 * schema, so an installation whose code has been updated without running
	 * `statedecoded migrate` will not have them.
	 */
	protected $has_fulltext_indexes;

	/*
	 * Search config.
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
	public $documents = [];

	/*
	 * Number of documents to store before automatically flushing.
	 */
	public $batch_size = 100;

	/*
	 * Hang on to our last result.
	 */
	protected $last_result;


	public function __construct($args = [])
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
		return true;
	}

	public function add_document($record)
	{
		return true;
	}

	public function commit()
	{
		return true;
	}

	public function debug()
	{

	}

	public function search($query = [])
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
		$select_fields = ['*'];

		$law_fields = [];
		$law_fields[] = 'laws.id';
		$law_fields[] = 'laws.catch_line AS name';
		$law_fields[] = 'laws.edition_id';
		$law_fields[] = '"law" AS object_type';
		$law_where = [];

		$structure_fields = [];
		$structure_fields[] = 'structure.id';
		$structure_fields[] = 'structure.name';
		$structure_fields[] = 'structure.edition_id';
		$structure_fields[] = '"structure" AS object_type';
		$structure_where = [];

		$order = [];

		$query_args = [];

		if(isset($query['q']))
		{
			/*
			 * Searches run against the full-text indexes on laws and structure
			 * (see the migration that adds ft_laws_search and
			 * ft_structure_search). This replaced a REGEXP-based search, which
			 * no index could serve: every query read every row of every law.
			 *
			 * Boolean mode is used rather than natural language mode because it
			 * supports quoted phrases and +/- operators, and because natural
			 * language mode silently discards terms appearing in more than half
			 * the rows -- surprising behaviour in a corpus where words like
			 * "section" are ubiquitous.
			 */

			# Note: structure->metadata->text is serialized and not directly searchable in SQL.

			$law_where_or = [];
			$structure_where_or = [];

			/*
			 * A search for a section number should find that law, and section
			 * numbers are not usefully tokenized by the full-text indexer. This
			 * is an exact, indexed lookup.
			 */
			$law_where_or[] = 'section = :term';
			$structure_where_or[] = 'name = :term';
			$query_args[':term'] = $query['q'];

			/*
			 * With no full-text index to search, MATCH would fail with error
			 * 1191 rather than return rows, so the REGEXP path is used instead.
			 */
			$boolean_query = $this->has_fulltext_indexes()
				? $this->boolean_query($query['q'])
				: '';

			if($boolean_query !== '')
			{
				$query_args[':match'] = $boolean_query;

				$law_where_or[] =
					'MATCH(laws.catch_line, laws.text) AGAINST (:match IN BOOLEAN MODE)';
				$structure_where_or[] =
					'MATCH(structure.name) AGAINST (:match IN BOOLEAN MODE)';

				/*
				 * Relevance, as scored by MySQL. A match in the title is worth
				 * more than one in the body, so the title score is weighted up;
				 * this preserves the old ordering, which put title matches
				 * first, without hand-rolling the arithmetic. The score is
				 * clamped before weighting; see MAX_RELEVANCE_SCORE.
				 */
				$law_fields[] = '(MATCH(laws.catch_line, laws.text) '
					. 'AGAINST (:match_score IN BOOLEAN MODE)) AS relevance';
				$structure_fields[] = '(LEAST(MATCH(structure.name) '
					. 'AGAINST (:match_score_structure IN BOOLEAN MODE), '
					. self::MAX_RELEVANCE_SCORE . ') * '
					. self::TITLE_RELEVANCE_WEIGHT . ') AS relevance';

				$query_args[':match_score'] = $boolean_query;
				$query_args[':match_score_structure'] = $boolean_query;

				$order[0] = 'relevance DESC';
			}
			else
			{
				/*
				 * Either nothing in the search term survived tokenizing -- it
				 * is all shorter than innodb_ft_min_token_size, or all
				 * stopwords -- or this database has no full-text indexes to
				 * search. Fall back to the old REGEXP search, which has no
				 * minimum length and needs no index. It is slow, but it is
				 * correct; on a properly migrated database these searches are
				 * rare.
				 */
				$query_args[':term_boundary'] = SqlSearchEngine::word_boundary(
					str_replace('"', '', $query['q'])
				);

				$law_where_or[] = 'catch_line REGEXP :term_boundary';
				$law_where_or[] = 'text REGEXP :term_boundary';
				$structure_where_or[] = 'name REGEXP :term_boundary';

				$law_fields[] = '(catch_line REGEXP :term_boundary) AS relevance';
				$structure_fields[] = '(name REGEXP :term_boundary) AS relevance';

				$order[0] = 'relevance DESC';
			}

			if(count($law_where_or))
			{
				$law_where[] = '(' . implode(' OR ', $law_where_or) . ')';
			}
			if(count($structure_where_or))
			{
				$structure_where[] = '(' . implode(' OR ', $structure_where_or) . ')';
			}
		}

		/*
		 * Handle editions.
		 */
		if(isset($query['edition_id']) && strlen($query['edition_id']))
		{
			$law_where[] = 'laws.edition_id = :edition_id';
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
			$select_fields = ['COUNT(*) AS count'];
			// $law_fields = array('count(*) AS count');
			// $structure_fields = array('count(*) AS count');
			unset($order, $offset, $limit);
		}

		/*
		 * Assemble our final query.
		 */
		$sql_query = 'SELECT ' . implode(',', $select_fields) . ' FROM ';

			$sql_query .= '(SELECT ' . implode(', ', $law_fields) . ' FROM laws ';
			if(count($law_where))
			{
				$sql_query .= 'WHERE ' . implode(' AND ', $law_where) . ' ';
			}
			$sql_query .= 'UNION ';

			$sql_query .= 'SELECT ' . implode(', ', $structure_fields) . ' FROM structure ';
			if(count($structure_where))
			{
				$sql_query .= 'WHERE ' . implode(' AND ', $structure_where) . ' ';
			}

		$sql_query .= ') AS records ';

		if(isset($order) && is_array($order) && count($order))
		{
			$sql_query .= 'ORDER BY ' . implode(', ', array_filter($order)) . ' ';
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

		return [$sql_query, $query_args];
	}

	/*
	 * The smallest token InnoDB will index, per innodb_ft_min_token_size.
	 *
	 * Read from the server rather than assumed, because a deployment may have
	 * raised or lowered it. Falls back to MySQL's default if the variable
	 * cannot be read.
	 */
	public function min_token_size()
	{

		if(isset($this->min_token_size))
		{
			return $this->min_token_size;
		}

		$this->min_token_size = 3;

		try
		{
			$statement = $this->db->query('SELECT @@innodb_ft_min_token_size');
			if($statement !== false)
			{
				$size = $statement->fetchColumn();
				if($size !== false && (int) $size > 0)
				{
					$this->min_token_size = (int) $size;
				}
			}
		}
		catch(Throwable $e)
		{
			// Keep the default.
		}

		return $this->min_token_size;

	}

	/*
	 * Whether both full-text indexes that this engine's MATCH clauses require
	 * are present.
	 *
	 * These indexes are created by a migration rather than by the baseline
	 * schema, so they are absent on an installation whose code was updated
	 * without running `statedecoded migrate`. MySQL answers a MATCH against a
	 * column list it has no index for with error 1191 rather than with rows, so
	 * without this check every search on such an installation fails outright.
	 * Knowing in advance lets the caller use the REGEXP path instead, which
	 * needs no index.
	 *
	 * The column list must match the index exactly: MySQL will not use an index
	 * on (catch_line, text) to serve MATCH(text) alone. Both columns of
	 * ft_laws_search are therefore checked, not merely the index's existence.
	 */
	public function has_fulltext_indexes()
	{

		if(isset($this->has_fulltext_indexes))
		{
			return $this->has_fulltext_indexes;
		}

		/*
		 * Assume the indexes are absent, so that a database we cannot
		 * interrogate falls back to a search that always works rather than one
		 * that always fails.
		 */
		$this->has_fulltext_indexes = false;

		try
		{
			$statement = $this->db->prepare(
				'SELECT COUNT(DISTINCT TABLE_NAME) FROM information_schema.STATISTICS
				 WHERE TABLE_SCHEMA = DATABASE() AND INDEX_TYPE = "FULLTEXT"
				 AND ((TABLE_NAME = "laws" AND COLUMN_NAME IN ("catch_line", "text"))
				   OR (TABLE_NAME = "structure" AND COLUMN_NAME = "name"))
				 GROUP BY TABLE_NAME HAVING COUNT(*) = IF(TABLE_NAME = "laws", 2, 1)');

			if($statement !== false && $statement->execute())
			{
				/*
				 * One row per table that is fully indexed; both must be.
				 */
				$this->has_fulltext_indexes =
					count($statement->fetchAll(PDO::FETCH_COLUMN)) === 2;
			}
		}
		catch(Throwable $e)
		{
			// Keep the assumption that they are absent.
		}

		return $this->has_fulltext_indexes;

	}

	/*
	 * Turn a user's search string into a MySQL boolean-mode expression.
	 *
	 * Quoted sections are preserved as phrases; every other word becomes a
	 * required term. Characters that are operators in boolean mode are stripped
	 * from the user's words, so that a stray "+" or "-" cannot change the
	 * meaning of the search or produce a syntax error.
	 *
	 * Returns an empty string when nothing usable survives -- when every word is
	 * shorter than the indexer's minimum token size, for instance -- which tells
	 * the caller to fall back to a REGEXP search.
	 */
	public function boolean_query($search_string)
	{

		$minimum = $this->min_token_size();
		$stopwords = $this->stopwords();
		$terms = [];

		foreach(SqlSearchEngine::tokenize_with_phrases($search_string) as $token)
		{
			/*
			 * Strip the boolean-mode operators: + - > < ( ) ~ * " @ and the
			 * comma, which MySQL treats specially inside distance searches.
			 */
			$clean = trim(preg_replace('/[+><\(\)~*"@,]+/', ' ', $token['value']));

			if($clean === '')
			{
				continue;
			}

			/*
			 * A term containing punctuation -- a section number such as
			 * "18.2-12", say -- is tokenized by MySQL into its separate parts.
			 * Searching for those parts individually is meaningless, so such a
			 * term is quoted, which asks for the parts in that order.
			 */
			$is_phrase = $token['phrase'] || preg_match('/[^\p{L}\p{N}\s]/u', $clean);

			/*
			 * Words the indexer will not have stored are dropped: a required
			 * "+the" matches nothing at all, which would turn a search
			 * containing a common word into a search returning nothing.
			 */
			$words = preg_split('/\s+/', preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $clean));
			$indexable = array_filter($words,
				function($word) use ($minimum, $stopwords) {
					return (strlen($word) >= $minimum)
						&& !in_array(strtolower($word), $stopwords);
				});

			if(count($indexable) === 0)
			{
				continue;
			}

			if($is_phrase)
			{
				$terms[] = '+"' . $clean . '"';
			}
			else
			{
				$terms[] = '+' . $clean;
			}
		}

		return implode(' ', $terms);

	}

	/*
	 * The words the full-text indexer ignores.
	 *
	 * Searching for a stopword as a required term matches nothing, so they are
	 * dropped from the query; if that leaves nothing, the caller falls back to
	 * a REGEXP search, which does find them.
	 */
	public function stopwords()
	{

		if(isset($this->stopword_cache))
		{
			return $this->stopword_cache;
		}

		$this->stopword_cache = [];

		try
		{
			$statement = $this->db->query(
				'SELECT value FROM information_schema.INNODB_FT_DEFAULT_STOPWORD');
			if($statement !== false)
			{
				$this->stopword_cache = array_map('strtolower',
					$statement->fetchAll(PDO::FETCH_COLUMN));
			}
		}
		catch(Throwable $e)
		{
			// An empty list simply means nothing is treated as a stopword.
		}

		return $this->stopword_cache;

	}

	/*
	 * Split a search string into tokens, keeping quoted phrases intact and
	 * flagged as such.
	 *
	 * tokenize() discards the distinction between "water quality" and
	 * water quality, which is exactly the distinction boolean mode can honour.
	 */
	public static function tokenize_with_phrases($string)
	{
		$tokens = [];
		$buffer = '';
		$in_quotes = false;

		for($i = 0; $i < strlen($string); $i++)
		{
			if($string[$i] === '"')
			{
				if(strlen(trim($buffer)))
				{
					$tokens[] = ['value' => trim($buffer), 'phrase' => $in_quotes];
				}
				$buffer = '';
				$in_quotes = !$in_quotes;
			}
			elseif($string[$i] === ' ' && !$in_quotes)
			{
				if(strlen(trim($buffer)))
				{
					$tokens[] = ['value' => trim($buffer), 'phrase' => false];
				}
				$buffer = '';
			}
			else
			{
				$buffer .= $string[$i];
			}
		}

		if(strlen(trim($buffer)))
		{
			$tokens[] = ['value' => trim($buffer), 'phrase' => $in_quotes];
		}

		return $tokens;
	}

	/*
	 * String tokenizing function.  Handles quoted strings, as well.  Useful for
	 * search queries.
	 */

	public static function tokenize($string)
	{
		$buffer = '';
		$keywords = [];
		$quote_string = false;

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
	 * Note that MySQL 8 uses ICU regular expressions, where the word boundary
	 * is \b; the old [[:<:]] and [[:>:]] syntax throws error 3685.
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
			$keyword = '\\b'.$keyword;
		}

		if(substr($keyword, -2, 2) == '\*')
		{
			$keyword = substr($keyword, 0, -2).'.*';
		}
		else
		{
			// Add back word boundary
			$keyword .= '\\b';
		}

		return $keyword;
	}

	// We don't have a real index, so we don't do anything on delete, successfully.
	public function delete($edition_id)
	{
		return true;
	}

}
