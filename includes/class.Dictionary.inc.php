<?php

/**
 * The Dictionary class, for retrieving terms and definitions
 *
 * PHP version 5
 *
 * @license		http://www.gnu.org/licenses/gpl.html GPL 3
 * @version		0.8
 * @link		http://www.statedecoded.com/
 * @since		0.1
 *
 */

/**
 *
 */
class Dictionary
{

	/**
	 * Get the definition for a given term for a given section of code.
	 */
	function define_term()
	{

		/*
		 * We're going to need access to the database connection throughout this class.
		 */
		global $db;

		/*
		 * If no term has been defined, there is nothing to be done.
		 */
		if (!isset($this->term))
		{
			return FALSE;
		}

		/*
		 * Determine the structural heritage of the provided section number and store it in an
		 * array.
		 */
		if (isset($this->section_number))
		{
		
			$heritage = new Law;
			$heritage->config->get_structure = TRUE;
			$heritage->section_number = $this->section_number;
			$law = $heritage->get_law();
			$ancestry = array();
			foreach ($law->ancestry as $tmp)
			{
				$ancestry[] = $tmp->id;
			}
			
		}

		/*
		 * We want to check if the term is in all caps. If it is, then we want to keep it in
		 * all caps to query the database. Otherwise, we lowercase it. That is, "Board" should be looked
		 * up as "board," but "NAIC" should be looked up as "NAIC."
		 */
		for ($i=0; $i<strlen($this->term); $i++)
		{
		
			/*
			 * If there are any uppercase characters, then make this PCRE string case
			 * sensitive.
			 */
			if ( (ord($this->term{$i}) >= 97) && (ord($this->term{$i}) <= 122) )
			{
				$lowercase = TRUE;
				break;
			}
			
		}

		if ($lowercase === TRUE)
		{
			$this->term = strtolower($this->term);
		}

		/*
		 * If the last character in this word is an "s," then it might be a plural, in which
		 * case we need to search for this and without its plural version.
		 */

		if (substr($this->term, -1) == 's')
		{
			$plural = TRUE;
		}

		/*
		 * This is a tortured assembly of a query. The idea is to provide flexibility on a pair of
		 * axes. The first is to support both plural and singular terms. The second is to support
		 * queries with and without section numbers, to provide either the one true definition for
		 * a term within a given scope or all definitions in the whole code.
		 */
		$sql = 'SELECT dictionary.term, dictionary.definition, dictionary.scope,
				laws.section AS section_number
				FROM dictionary
				LEFT JOIN laws
					ON dictionary.law_id=laws.id
				WHERE (dictionary.term = :term';
		$sql_args = array(
			':term' => $this->term
		);
		if ($plural === TRUE)
		{
			$sql .= ' OR dictionary.term = :term_single';
			$sql_args[':term_single'] =  substr($this->term, 0, -1);
		}
		$sql .= ') ';
		if (isset($this->section_number))
		{
		
			$sql .= 'AND (';

			$ancestor_count = count($ancestry);
			for ($i = 0; $i < $ancestor_count; $i++)
			{
				$sql .= "(dictionary.structure_id = :structure_id$i) OR ";
				$sql_args[":structure_id$i"] = $ancestry[$i];
			}
			$sql .= '	(dictionary.scope = :scope)
					OR
						(laws.section = :section_number)
					) ';
			$sql_args[':scope'] = 'global';
			$sql_args[':section_number'] = $this->section_number;
			
		}

		$sql .= 'ORDER BY dictionary.scope_specificity ';
		if (isset($this->section_number))
		{

			$sql .= 'LIMIT 1';
		}
		
		$statement = $db->prepare($sql);
		$result = $statement->execute($sql_args);
		
		/*
		 * If the query succeeds, great, retrieve it.
		 */
		if ( ($result !== FALSE) && ($statement->rowCount() > 0) )
		{

			/*
			 * Get all results.
			 */
			$dictionary = new stdClass();
			$i=0;
			while ($term = $statement->fetch(PDO::FETCH_OBJ))
			{
			
				$term->url = 'http://' . $_SERVER['SERVER_NAME'] . '/' . $term->section_number . '/';
				$term->formatted = wptexturize($term->definition) . ' (<a href="' . $term->url . '">'
					. $term->section_number . '</a>)';
				$dictionary->$i = $term;
				$i++;
				
			}
			
		}

		/*
		 * Else if the query fails, then the term is found in the generic terms dictionary.
		 */
		else
		{

			/*
			 * Assemble the SQL.
			 */
			$sql = 'SELECT term, definition, source, source_url AS url
					FROM dictionary_general
					WHERE term = :term';
			$sql_args = array(
				':term' => $this->term
			);
			if ($plural === TRUE)
			{
				$sql .= ' OR term = :term_single';
				$sql_args[':term_single'] = substr($this->term, 0, -1);
			}
			$sql .= ' LIMIT 1';

			$statement = $db->prepare($sql);
			$result = $statement->execute($sql_args);

			/*
			 * If the query fails, or if no results are found, return false -- we have no terms for
			 * this structural unit.
			 */
			if ( ($result === FALSE) || ($statement->rowCount() < 1) )
			{
				return FALSE;
			}

			/*
			 * Get the first result. Assemble a slightly different response than for a custom term.
			 * We assign this to the first element of an object because that is the format that the
			 * API expects to receive a list of terms in. In this case, we have just one term.
			 */
			$dictionary->{0} = $statement->fetch(PDO::FETCH_OBJ);
			$dictionary->{0}->formatted = wptexturize($dictionary->{0}->definition) .
				' (<a href="' . $dictionary->{0}->url . '">' . $dictionary->{0}->source . '</a>)';

		}

		return $dictionary;

	}

	/**
	 * Get a list of defined terms for a given structural unit of the code, returning just a listing
	 * of terms. (The idea is that we can use an Ajax call to get each definition on demand.)
	 */
	function term_list()
	{

		/*
		 * We're going to need access to the database connection throughout this class.
		 */
		global $db;

		/*
		 * If a structural ID hasn't been passed to this function, then return a listing of terms
		 * that apply to the entirety of the code.
		 */
		if (!isset($this->structure_id) && !isset($this->scope))
		{
			$this->scope = 'global';
		}
		
		/*
		 * Create an object in which we'll store terms that are identified.
		 */
		$terms = new stdClass();
		
		/*
		 * Get a listing of all structural units that contain the current structural unit -- that is,
		 * if this is a chapter, get the ID of both the chapter and the title. And so on.
		 */
		if (isset($this->structure_id))
		{
		
			$heritage = new Structure;
			$heritage->id = $this->structure_id;
			$ancestry = $heritage->id_ancestry();
			$tmp = array();
			foreach ($ancestry as $level)
			{
				$tmp[] = $level->id;
			}
			$ancestry = $tmp;
			unset($tmp);
			
		}

		/*
		 * Get a listing of all globally scoped terms.
		 */
		$sql_args = array(
			':global_scope' => 'global'
		);
		if ( isset($this->scope) && ($this->scope == 'global') )
		{
		
			$sql = 'SELECT dictionary.term
					FROM dictionary
					LEFT JOIN laws
						ON dictionary.law_id=laws.id
					 WHERE scope = :global_scope';
					 
		}

		/*
		 * Otherwise, we're getting a listing of all more narrowly scoped terms. We always make sure
		 * that global definitions are included, in addition to the definitions for the current
		 * structural heritage.
		 */
		else
		{
		
			$sql = 'SELECT DISTINCT dictionary.term
					FROM dictionary
					LEFT JOIN laws
						ON dictionary.law_id=laws.id
					LEFT JOIN structure
						ON laws.structure_id=structure.id
					WHERE
					(
						(dictionary.law_id=:section_id)
						AND
						(dictionary.scope=:section_scope)
					)';
			$sql_args[':section_id'] = $this->section_id;
			$sql_args[':section_scope'] = 'section';
			$ancestry_count = count($ancestry);
			for($i = 0; $i < $ancestry_count; $i++)
			{
				$sql .= " OR (dictionary.structure_id=:structure$i)";
				$sql_args[":structure$i"] = $ancestry[$i];
			}
			$sql .= ' OR (scope=:global_scope)';
			
		}

		$statement = $db->prepare($sql);
		$result = $statement->execute($sql_args);
		
		/*
		 * Establish the counter we'll use as our object numbering scheme throughout both of our
		 * queries.
		 */
		$i=0;

		/*
		 * If any terms are found, then add them to our $terms object.
		 */
		if ( ($statement->rowCount() > 1) )
		{

			/*
			 * Build up the result as an object as we loop through the results.
			 */
			while ($term = $statement->fetch(PDO::FETCH_OBJ))
			{
				$terms->$i = $term->term;
				$i++;
			}

		}

		/*
		 * Assemble a second query, this one against our generic legal dictionary.
		 */
		$sql = 'SELECT term
				FROM dictionary_general';
		$sql_args = null;

		$statement = $db->prepare($sql);
		$result = $statement->execute($sql_args);

		if ($result !== FALSE && $statement->rowCount() > 0)
		{
		
			/*
			* Append these results to the existing $terms object, continuing to use the previously-
			* defined $i counter.
			*/
			while ($term = $statement->fetch(PDO::FETCH_OBJ))
			{
				$terms->$i = $term->term;
				$i++;
			}
			
		}

		$tmp = (array) $terms;
		$tmp = array_unique($tmp);
		$terms = (object) $tmp;

		return $terms;
		
	}
	
}
