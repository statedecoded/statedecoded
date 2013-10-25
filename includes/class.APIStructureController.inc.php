<?php

/**
 * The API's structure class
 *
 * PHP version 5
 *
 * @author		Waldo Jaquith <waldo at jaquith.org>
 * @copyright	2013 Waldo Jaquith
 * @license		http://www.gnu.org/licenses/gpl.html GPL 3
 * @version		0.9
 * @link		http://www.statedecoded.com/
 * @since		0.6
 *
 */

class APIStructureController extends BaseAPIController
{
	function handle($args)
	{
		/*
		 * If the request is for the structural units sorted by a specific criteria.
		 */
		if (isset($_GET['sort']))
		{

			/*
			 * Explicitly reassign the external value to an internal one, for safety's sake.
			 */
			if ($_GET['sort'] == 'views')
			{
				$order_by = filter_var($_GET['sort'], FILTER_SANITIZE_STRING);
			}

		}

		/*
		 * Create a new instance of the class that handles information about individual laws.
		 */
		$struct = new Structure();


		/*
		 * Get the structure based on our identifier.
		 */
		if ($args['relational_id'])
		{
			$struct->structure_id = $args['relational_id'];
		}
		else {
			$struct->structure_id = '';
		}

		$struct->get_current();
		$response = $struct->structure;

		/*
		 * If this structural element does not exist.
		 */
		if ($response === FALSE)
		{
			$this->handleNotFound();
		}

		/*
		 * List all child structural units.
		 */
		$struct->order_by = $order_by;
		$response->children = $struct->list_children();

		/*
		 * List all laws.
		 */
		$response->laws = $struct->list_laws();

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
} /* class APIStructureController */
