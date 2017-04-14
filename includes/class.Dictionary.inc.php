<?php

/**
 * The Dictionary class, for retrieving terms and definitions
 *
 * PHP version 5
 *
 * @license		http://www.gnu.org/licenses/gpl.html GPL 3
 * @version		0.9
 * @link		http://www.statedecoded.com/
 * @since		0.1
 *
 */

/**
 *
 */
class Dictionary
{

	public $term;
	public $law_id;
	public $section_number;
	public $edition_id;
	public $generic_terms = FALSE;


	public function __construct($args = array()) {
		foreach($args as $key => $value) {
			$this->$key = $value;
		}
		/*
		 * If we haven't been told to use generic terms explicitly, then default
		 * to the constant. If we don't have a constant, we default to FALSE.
		 */
		if(!isset($args['generic_terms']) && defined('USE_GENERIC_TERMS')) {
			$this->generic_terms = constant('USE_GENERIC_TERMS');
		}
	}

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
		 * Initialize our dictionary results.
		 */
		$dictionary = FALSE;
		$lowercase = FALSE;

		/*
		 * If no term has been defined, there is nothing to be done.
		 */
		if (!isset($this->term))
		{
			return FALSE;
		}

		/*
		 * First, we check for the term as given.
		 */
		$dictionary = $this->find_term($this->term, $this->section_number, $this->law_id, $this->edition_id);

		// If we don't have a result and we can lowercase it, lowercase it.
		if(!$dictionary && $this->term !== strtolower($this->term)) {
			$dictionary = $this->find_term(strtolower($this->term), $this->section_number, $this->law_id, $this->edition_id);
		}

		/*
		 * If the query still fails, then the term is found in the generic terms dictionary.
		 */
		if(!$dictionary && $this->generic_terms)
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
	 * Wrap our query in a function for reuse.
	 */
	public function find_term($term, $section_number = null, $law_id = null, $edition_id = null) {

		global $db;

		$heritage = new Law;
		$heritage->config = new stdClass();
		$heritage->config->get_structure = TRUE;

		if ($section_number)
		{
			$heritage->section_number = $section_number;
		}
		elseif ($law_id)
		{
			$heritage->law_id = $law_id;
		}

		if ($edition_id)
		{
			$heritage->edition_id = $edition_id;
		}

		$law = $heritage->get_law();
		$ancestry = array();
		foreach ($law->ancestry as $tmp)
		{
			$ancestry[] = $tmp->id;
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
				laws.section AS section_number, laws.id AS law_id, permalinks.url AS url
				FROM dictionary
				LEFT JOIN laws
					ON dictionary.law_id=laws.id
				LEFT JOIN permalinks
					ON permalinks.relational_id=laws.id
					AND permalinks.object_type = :object_type
					AND permalinks.preferred=1
				WHERE (dictionary.term = :term';
		$sql_args = array(
			':term' => $term,
			':object_type' => 'law'
		);
		if ($plural === TRUE)
		{
			$sql .= ' OR dictionary.term = :term_single';
			$sql_args[':term_single'] =  substr($term, 0, -1);
		}
		$sql .= ') ';
		if ($section_number || $law_id)
		{

			$sql .= 'AND (';

			$ancestor_count = count($ancestry);
			for ($i = 0; $i < $ancestor_count; $i++)
			{
				$sql .= "(dictionary.structure_id = :structure_id$i) OR ";
				$sql_args[":structure_id$i"] = $ancestry[$i];
			}
			$sql .= ' (dictionary.scope = :scope) OR ';

			if($section_number)
			{
				$sql .= '(laws.section = :section_number)';
				$sql_args[':section_number'] = $section_number;
			}
			else
			{
				$sql .= '(laws.id = :law_id)';
				$sql_args[':law_id'] = $law_id;
			}

			$sql .= ') AND dictionary.edition_id = :edition_id ';
			$sql_args[':scope'] = 'global';
			$sql_args[':edition_id'] = $edition_id;
		}

		$sql .= 'ORDER BY dictionary.scope_specificity ';
		if ($section_number || $law_id)
		{

			$sql .= 'LIMIT 1';
		}

		$statement = $db->prepare($sql);
		$result = $statement->execute($sql_args);

		$dictionary = false;
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
				$term->formatted = wptexturize($term->definition) . ' (<a href="' . $term->url . '">'
					. $term->section_number . '</a>)';
				$dictionary->$i = $term;
				$i++;
			}

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
		 * Create an array in which we'll store terms that are identified.
		 */
		$terms = array();

		/*
		 * Get a listing of all structural units that contain the current structural unit -- that is,
		 * if this is a chapter, get the ID of both the chapter and the title. And so on.
		 */
		if (isset($this->structure_id))
		{

			$heritage = new Structure;
			$ancestry = $heritage->id_ancestry($this->structure_id);
			$tmp = array();
			foreach ($ancestry as $level)
			{
				$tmp[] = $level->id;
			}
			$ancestry = $tmp;
			unset($tmp);
			
		}

		/*
		 * Get a list of all globally scoped terms.
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
		 * Otherwise, we're getting a list of all more narrowly scoped terms. We always make sure
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
						(dictionary.law_id=:section_id
						AND
						dictionary.scope=:section_scope)
					';
			$sql_args[':section_id'] = $this->section_id;
			$sql_args[':section_scope'] = 'section';
			$ancestry_count = count($ancestry);
			for($i = 0; $i < $ancestry_count; $i++)
			{
				$sql .= " OR (dictionary.structure_id=:structure$i)";
				$sql_args[":structure$i"] = $ancestry[$i];
			}
			$sql .= ' OR (scope=:global_scope))';

		}

		if(isset($this->edition_id)) {
			$sql .= ' AND dictionary.edition_id=:edition_id';
			$sql_args[':edition_id'] = $this->edition_id;
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
		if ( ($statement->rowCount() > 0) )
		{

			/*
			 * Build up the result as an object as we loop through the results.
			 */
			while ($term = $statement->fetch(PDO::FETCH_OBJ))
			{
				$terms[] = $term->term;
			}

		}

		/*
		 * Assemble a second query, this one against our generic legal dictionary, but only if we
		 * have opted to include generic terms.
		 */
		if ($this->generic_terms === TRUE)
		{

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
					$terms[] = $term->term;
				}

			}

		}

		$terms = array_unique($terms);

		return $terms;

	}

}
