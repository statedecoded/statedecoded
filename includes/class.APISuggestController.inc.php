<?php

/**
 * The API's method for suggesting autocompletion of terms
 *
 * PHP version 8
 *
 * @license		http://www.gnu.org/licenses/gpl.html GPL 3
 * @version		1.1
 * @link		https://www.statedecoded.com/
 * @since		0.8
 *
 */

class APISuggestController extends BaseAPIController
{
	/*
	 * The number of suggestions the API returns.
	 */
	const SUGGESTION_LIMIT = 5;

	function handle($args)
	{

		global $db;

		/*
		 * Make sure we have a search term.
		 */
		if (!isset($args['term']) || empty($args['term']))
		{
			json_error('Search term not provided.');
			die();
		}

		/*
		 * Clean up the search term.
		 */
		$term = filter_var($args['term'], FILTER_DEFAULT);

		/*
		 * Suggest section numbers and catch lines that begin with the search term. Escape any
		 * characters that LIKE treats as wildcards.
		 */
		$prefix = addcslashes($term, '%_') . '%';

		$edition_id = $this->current_edition_id();

		/*
		 * Query the two kinds of suggestion separately, rather than as one UNION with a single
		 * LIMIT across both. A shared limit is spent in whatever order the UNION's temporary
		 * table happens to produce, so a prefix matching several section numbers used up every
		 * slot and no catch line was ever suggested.
		 */
		$sections = $this->suggestions('section', $prefix, $edition_id);
		$catch_lines = $this->suggestions('catch_line', $prefix, $edition_id);

		$response = new stdClass();
		$terms = $this->merge_suggestions($sections, $catch_lines);

		/*
		 * An empty result is reported as false rather than as an empty list, which is what
		 * this API has always returned and what the autocomplete JavaScript expects.
		 */
		if (count($terms) === 0)
		{

			$response->terms = false;

		}

		else
		{

			$response->terms = [];
			foreach ($terms as $i => $suggestion)
			{
				$response->terms[] = [
					'id' => $i,
					'term' => $suggestion
				];
			}
		}

		$this->render($response, 'OK');

	} /* handle() */

	/**
	 * Prefix-matching values of a single column, closest match first.
	 *
	 * Ordered by length, then alphabetically. For a prefix search the shortest match is the most
	 * specific one -- typing "1-1" should suggest "1-1" ahead of "1-100.2" -- and without an
	 * ORDER BY the rows arrive in whatever order the query happens to produce, which for a
	 * common prefix means an arbitrary five out of hundreds.
	 *
	 * $column is a literal, never user input: it is interpolated into the SQL because a column
	 * name cannot be a bound parameter.
	 */
	protected function suggestions($column, $prefix, $edition_id)
	{

		global $db;

		if (!in_array($column, ['section', 'catch_line'], true))
		{
			throw new InvalidArgumentException('Cannot suggest from column "' . $column . '".');
		}

		$sql = 'SELECT DISTINCT ' . $column . ' AS term FROM laws
				WHERE ' . $column . ' LIKE :prefix';
		$sql_args = [':prefix' => $prefix];

		/*
		 * Only suggest laws from the current edition, when we know what that is.
		 */
		if ($edition_id !== false)
		{
			$sql .= ' AND edition_id = :edition_id';
			$sql_args[':edition_id'] = $edition_id;
		}

		$sql .= ' ORDER BY LENGTH(' . $column . '), ' . $column
			. ' LIMIT ' . self::SUGGESTION_LIMIT;

		$statement = $db->prepare($sql);

		if ($statement->execute($sql_args) === false)
		{
			return [];
		}

		/*
		 * fetchAll() rather than a fetch loop: looping until fetchColumn() is falsy ends the
		 * list early on a term of "0".
		 */
		return $statement->fetchAll(PDO::FETCH_COLUMN);

	} /* suggestions() */

	/**
	 * Combine section-number and catch-line suggestions into one list.
	 *
	 * Interleaved, so that both kinds are visible within the handful of slots available rather
	 * than one kind filling the list. Where one list is shorter, the other takes the remaining
	 * slots, so the caller still gets as many suggestions as it would have otherwise.
	 */
	protected function merge_suggestions($sections, $catch_lines)
	{

		$terms = [];

		for ($i = 0; $i < max(count($sections), count($catch_lines)); $i++)
		{
			if (isset($sections[$i]))
			{
				$terms[] = $sections[$i];
			}

			if (isset($catch_lines[$i]))
			{
				$terms[] = $catch_lines[$i];
			}
		}

		/*
		 * A section number and a catch line can be the same string, and the old UNION removed
		 * such duplicates.
		 */
		$terms = array_values(array_unique($terms));

		return array_slice($terms, 0, self::SUGGESTION_LIMIT);

	} /* merge_suggestions() */

	/**
	 * The ID of the edition to draw suggestions from, or false if it cannot be
	 * determined and suggestions should come from every edition.
	 *
	 * Read from the database rather than from the EDITION_ID constant. That
	 * constant is written into .htaccess at import time, so it names whichever
	 * edition was imported then; if that write ever failed, or the current
	 * edition was changed by other means, it goes on naming an older edition.
	 * Because this scoping is exclusive -- suggestions come from the named
	 * edition and no other -- a stale value means laws added since stop
	 * autocompleting altogether. The constant remains a fallback for a database
	 * with no edition flagged current.
	 */
	protected function current_edition_id()
	{

		global $db;

		try
		{
			$edition_object = new Edition(['db' => $db]);
			$edition = $edition_object->current();

			if ($edition !== false)
			{
				return $edition->id;
			}
		}
		catch (Exception $error)
		{
			// Fall through to the constant.
		}

		if (defined('EDITION_ID'))
		{
			return EDITION_ID;
		}

		return false;

	} /* current_edition_id() */
} /* class APISuggestController */
