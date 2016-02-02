<?php

/**
 * Permalink Controller
 *
 * This controller will catch any routes in the permalinks table.
 *
 * PHP version 5
 *
 * @license		http://www.gnu.org/licenses/gpl.html GPL 3
 * @version		0.9
 * @link		http://www.statedecoded.com/
 * @since		0.8
 *
 */

class PermalinkController extends BaseController
{

	public function handle($args)
	{

		if ( $args['route'] )
		{
			$route = $this->find_route($args['route']);

			/*
			 * Try to intelligently determine if there's a matching controller
			 */
			$object_name = str_replace(' ', '', ucwords($route['object_type'])) .
				'Controller';

			try {
				if (class_exists($object_name) !== FALSE)
				{
					$controller = new $object_name();
					return $controller->handle($route);
				}
			}
			catch (Exception $error) {
				return $this->handleNotFound($args);
			}
		}

		/*
		 * If we haven't found what we're looking for, show the 404 page.
		 */
		return $this->handleNotFound($args);

	}

	public function find_route($url)
	{
		$route = FALSE;

		global $db;

		/*
		 * Look up the route in the database
		 */
		$sql = 'SELECT *
				FROM permalinks
				WHERE url = :url
				LIMIT 1';
		$sql_args = array(
			':url' => $url
		);
		$statement = $db->prepare($sql);
		$result = $statement->execute($sql_args);

		/*
		 * If we found a route
		 */
		if ( ($result !== FALSE) && ($statement->rowCount() > 0) )
		{
			$route = $statement->fetch(PDO::FETCH_ASSOC);
		}

		return $route;
	}

}
