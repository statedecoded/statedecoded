<?php

/**
 * API Controller
 *
 * This controller will catch any routes in the permalinks table.
 *
 * PHP version 5
 *
 * @license		http://www.gnu.org/licenses/gpl.html GPL 3
 * @version		0.8
 * @link		http://www.statedecoded.com/
 * @since		0.8
 *
 */

class APIPermalinkController extends BaseAPIController
{

	public function handle($args)
	{
		/*
		 * You must be authorized to use the API
		 */
		$this->checkAuth();

		/*
		 * If the request is using JSONP, make sure there's a valid callback.
		 */
		if(!$this->checkCallback())
		{
			return $this->handleBadCallback();
		}

		/*
		 * Call the correct handler
		 */
		switch($args['operation'])
		{
			case 'law' :
			case 'structure' :
				return $this->handlePermalinks($args);

			case 'dictionary':
				return $this->handleDictionary($args);

			case 'search':
				return $this->handleSearch($args);

			case 'suggest':
				return $this->handleSuggest($args);

			default :
				return $this->handleNotFound($args);
		}

	}

	public function handlePermalinks($args)
	{

		if ( isset($args['route']) && $args['route'] != '/')
		{

			global $db;

			/*
			 * Look up the route in the database
			 */
			$sql = 'SELECT * FROM permalinks WHERE url = :url LIMIT 1';
			$sql_args = array(
				':url' => $args['route']
			);
			$statement = $db->prepare($sql);
			$result = $statement->execute($sql_args);

			/*
			 * If we found a route
			 */
			if ( $result !== FALSE )
			{
				if ( $statement->rowCount() > 0 )
				{

					$route = $statement->fetch(PDO::FETCH_ASSOC);

					/*
					 * Try to intelligently determine if there's a matching controlelr
					 */
					$object_name = 'API' .
						str_replace(' ', '', ucwords($route['object_type'])) .
						'Controller';
					$filename = 'class.' . $object_name . '.inc.php';

					/*
					 * We use file_exists rather than class_exists, as the latter
					 * will invoke the autoloader.
					 */
					if ( file_exists(INCLUDE_PATH . '/' . $filename) )
					{
						$controller = new $object_name();
						return $controller->handle($route);
					}
					else
					{
						trigger_error('Cannot find permalink class for object_type"' .
							$route['object_type'] . '"', E_USER_WARNING);
					}
				}

			}

		}
		/*
		 * If we did not get a route, assume we want all structures
		 */
		else
		{
			$controller = new APIStructureController();
			return $controller->handle($route);
		}

		/*
		 * If we haven't found what we're looking for, show the 404 page.
		 */
		return $this->handleNotFound($args);

	} /* function handlePermalinks */

} /* class APIPermalinkController */
