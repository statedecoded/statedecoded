<?php

/**
 * Permalink Controller
 *
 * This controller will catch any routes in the permalinks table.
 *
 * PHP version 5
 *
 * @license		http://www.gnu.org/licenses/gpl.html GPL 3
 * @version		1.0
 * @link		http://www.statedecoded.com/
 * @since		0.8
 *
 */

class PermalinkController extends BaseController
{

	public function handle($args)
	{

		if ( isset($args['route']) )
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
					$controller = new $object_name($this->local);

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
				WHERE url = :url';
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
			if($statement->rowCount() > 1)
			{
				/*
				 * In the rare case of duplicate permalinks, they *should* be the same
				 * object handler, but with different ids. In this case, make a list of
				 * ids, and just use the first object's other data.
				 */
				$permalinks = $statement->fetchAll(PDO::FETCH_ASSOC);
				foreach($permalinks as $permalink)
				{
					if(!$route)
					{
						$route = $permalink;
						$route['relational_id'] = array($route['relational_id']);
					}
					else
					{
						$route['relational_id'][] = $permalink['relational_id'];
					}
				}
			}
			else
			{
				$route = $statement->fetch(PDO::FETCH_ASSOC);
			}
		}

		return $route;
	}

}
