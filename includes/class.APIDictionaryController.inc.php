<?php

/**
 * The API's dictionary controller
 *
 * PHP version 8
 *
 * @license		http://www.gnu.org/licenses/gpl.html GPL 3
 * @version		1.1
 * @link		https://www.statedecoded.com/
 * @since		0.6
 *
 */

class APIDictionaryController extends BaseAPIController
{

	function handle($args)
	{

		/*
		 * If we have received neither a term nor a section, we can't do anything.
		 */
		if ( empty($args['term']) && empty($_GET['section']) )
		{
			json_error('Neither a dictionary term nor a section number have been provided.');
			die();
		}

		$section = null;
		$response = [];

		/*
		 * Clean up the term.
		 */
		$term = filter_var($args['term'], FILTER_DEFAULT);

		/*
		 * If a section has been specified, then clean that up.
		 */
		if (isset($_GET['section']))
		{
			$section = filter_input(INPUT_GET, 'section', FILTER_DEFAULT);
		}

		if (isset($_GET['law_id']))
		{
			$law_id = filter_input(INPUT_GET, 'law_id', FILTER_DEFAULT);
		}

		if (isset($_GET['edition_id']))
		{
			$edition_id = filter_input(INPUT_GET, 'edition_id', FILTER_DEFAULT);
		}
		else
		{
			$edition = new Edition();
			$current_edition = $edition->current();
			$edition_id = $current_edition->id;
		}

		/*
		 * If we're told to use generic definitions, pass that through to the Dictionary class.
		 */
		$dict_args = [];
		if(!empty($_GET['generic'])) {
			if($_GET['generic'] === '1' || strtolower($_GET['generic']) === 'true') {
				$dict_args['generic_terms'] = true;
			}
			elseif($_GET['generic'] === '0' || strtolower($_GET['generic']) === 'false') {
				$dict_args['generic_terms'] = false;
			}
		}

		$dict = new Dictionary($dict_args);


		/*
		 * Get the definitions for the requested term, if a term has been requested.
		 */
		if (!empty($args['term']))
		{

			if (isset($section))
			{
				$dict->section_number = $section;
			}
			if (isset($law_id))
			{
				$dict->law_id = $law_id;
			}
			if (isset($edition_id))
			{
				$dict->edition_id = $edition_id;
			}


			$dict->term = $term;
			$dictionary = $dict->define_term();

			/*
			 * If, for whatever reason, this term is not found, return an error.
			 */
			if ($dictionary === false)
			{
				$response = ['definition' => 'Definition not available.'];
			}

			else
			{

				/*
				 * Uppercase the first letter of the first (quoted) word. We perform this twice because
				 * some egal codes begin the definition with a quotation mark and some do not. (That is,
				 * some write '"Whale" is a large sea-going mammal' and some write 'Whale is a large
				 * sea-going mammal.")
				 */
				/*
				 * If the request contains a specific list of fields to be returned.
				 */
				if (isset($_GET['fields']))
				{

					/*
					 * Turn that list into an array.
					 */
					$returned_fields = explode(',', urldecode($_GET['fields']));
					foreach ($returned_fields as &$field)
					{
						$field = trim($field);
					}

					/*
					 * It's essential to unset $field at the conclusion of the prior loop.
					 */
					unset($field);

					foreach ($dictionary as &$term)
					{

						/*
						 * Step through our response fields and eliminate those that aren't in the
						 * requested list.
						 */
						foreach($term as $field => &$value)
						{

							if (in_array($field, $returned_fields) === false)
							{
								unset($term->$field);
							}

						}

					}

				}

				/*
				 * If a section has been specified, then simplify this response by returning just a
				 * single definition.
				 */
				if (isset($section) || isset($law_id))
				{
					$dictionary = $dictionary->{0};
				}

				/*
				 * Uppercase the first letter of the definition.
				 */
				if (isset($dictionary->definition) && strlen($dictionary->definition) > 0)
				{
					if (preg_match('/[A-Za-z]/', $dictionary->definition[0]) === 1)
					{
						$dictionary->definition[0] = strtoupper($dictionary->definition[0]);
					}
					elseif (strlen($dictionary->definition) > 1 && preg_match('/[A-Za-z]/', $dictionary->definition[1]) === 1)
					{
						$dictionary->definition[1] = strtoupper($dictionary->definition[1]);
					}
				}

				/*
				 * Rename this variable to use the expected name.
				 */
				$response = $dictionary;

			} // end else if term is found

		} // end if (!empty($args['term']))

		/*
		 * If a term hasn't been provided, then retrieve a term list for the specified section.
		 */
		elseif (!empty($_GET['section']))
		{

			/*
			 * Get the structural ID of the container for this section.
			 */
			$law = new Law;
			$law->section_number = $section;
			$law->config = false;
			$result = $law->get_law();
			if ($result == false)
			{
				$response = ['terms' => 'Term list not available.'];
			}
			else
			{

				/*
				 * Now get the term list.
				 */
				$dict->section_id = $law->section_id;
				$dict->structure_id = $law->structure_id;
				$response = $dict->term_list();
				if ($response == false)
				{
					$response = ['terms' => 'Term list not available.'];
				}

			}

		} // end elseif (!empty($args['section']))


		$this->render($response, 'OK', $_REQUEST['callback'] ?? null);

	} /* handle() */

} /* class APILawController */
