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

		$sql = 'SELECT DISTINCT section AS term FROM laws
				WHERE section LIKE :prefix_section';
		$sql_args = [
			':prefix_section' => $prefix,
			':prefix_catch_line' => $prefix
		];

		$edition_id = $this->current_edition_id();

		/*
		 * Only suggest laws from the current edition, when we know what that is.
		 */
		if ($edition_id !== false)
		{
			$sql .= ' AND edition_id = :edition_id_section';
			$sql_args[':edition_id_section'] = $edition_id;
		}

		$sql .= ' UNION
				SELECT DISTINCT catch_line AS term FROM laws
				WHERE catch_line LIKE :prefix_catch_line';

		if ($edition_id !== false)
		{
			$sql .= ' AND edition_id = :edition_id_catch_line';
			$sql_args[':edition_id_catch_line'] = $edition_id;
		}

		$sql .= ' LIMIT 5';

		$statement = $db->prepare($sql);
		$result = $statement->execute($sql_args);

		$response = new stdClass();

		/*
		 * If there are no results.
		 */
		if ($result === false || $statement->rowCount() == 0)
		{

			$response->terms = false;

		}

		/*
		 * If we have results, build up an array of them.
		 */
		else
		{

			$response->terms = [];
			$i = 0;
			while ($suggestion = $statement->fetchColumn())
			{
				$response->terms[] = [
					'id' => $i,
					'term' => $suggestion
				];
				$i++;
			}
		}

		$this->render($response, 'OK');

	} /* handle() */

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
