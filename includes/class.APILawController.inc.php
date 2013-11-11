<?php

/**
 * The API's law class
 *
 * PHP version 5
 *
 * @license		http://www.gnu.org/licenses/gpl.html GPL 3
 * @version		0.8
 * @link		http://www.statedecoded.com/
 * @since		0.6
 *
 */

class APILawController extends BaseAPIController
{
	function handle($args)
	{
		/*
		 * Create a new instance of the class that handles information about individual laws.
		 */
		$laws = new Law();

		/*
		 * Instruct the Law class on what, specifically, it should retrieve.
		 */
		$laws->config->get_text = TRUE;
		$laws->config->get_structure = TRUE;
		$laws->config->get_amendment_attempts = FALSE;
		$laws->config->get_court_decisions = TRUE;
		$laws->config->get_metadata = TRUE;
		$laws->config->get_references = TRUE;
		$laws->config->get_related_laws = TRUE;

		/*
		 * Pass the requested section number to Law.
		 */
		$laws->section_number = $args['identifier'];
		$laws->law_id = $args['relational_id'];

		/*
		 * Get a list of all of the basic information that we have about this section.
		 */
		$response = $laws->get_law();

		/*
		 * If, for whatever reason, this section is not found, return an error.
		 */
		if ($response === false)
		{
			$this->handleNotFound();
		}
		else
		{

			/*
			 * Eliminate the listing of all other sections in the chapter that contains this section. That's
			 * returned by our internal API by default, but it's not liable to be useful to folks receiving
			 * this data.
			 */
			unset($response->chapter_contents);
		}

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

			/*
			 * Step through our response fields and eliminate those that aren't in the requested list.
			 */
			foreach($response as $field => &$value)
			{
				if (in_array($field, $returned_fields) === false)
				{
					unset($response->$field);
				}
			}
		}

		$this->render($response, 'OK');

	} /* handle() */
} /* class APILawController */
