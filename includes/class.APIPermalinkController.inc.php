<?php

/**
 * API Controller
 *
 * This controller will catch any routes in the permalinks table.
 *
 * PHP version 8
 *
 * @license		http://www.gnu.org/licenses/gpl.html GPL 3
 * @version		1.1
 * @link		https://www.statedecoded.com/
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
		if (!$this->checkCallback())
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

	public function handleDictionary($args)
	{
		$controller = new APIDictionaryController($this->local);
		return $controller->handle($args);
	}

	public function handleSearch($args)
	{
		$controller = new APISearchController($this->local);
		return $controller->handle($args);
	}

	public function handleSuggest($args)
	{
		$controller = new APISuggestController($this->local);
		return $controller->handle($args);
	}

	public function handlePermalinks($args)
	{

		if ( isset($args['route']) && $args['route'] != '/')
		{

			global $db;

			/*
			 * Look up the route in the database.
			 */
			$sql = 'SELECT *
					FROM permalinks
					WHERE url = :url LIMIT 1';
			$sql_args = [
				':url' => $args['route']
			];
			$statement = $db->prepare($sql);
			$result = $statement->execute($sql_args);

			/*
			 * If we found a route.
			 */
			if ( $result !== false )
			{
			
				if ( $statement->rowCount() > 0 )
				{

					$route = $statement->fetch(PDO::FETCH_ASSOC);

					/*
					 * Try to determine intelligently if there's a matching controller.
					 */
					$object_name = 'API' .
						str_replace(' ', '', ucwords($route['object_type'])) .
						'Controller';

					if ( class_exists($object_name) == true)
					{
						$controller = new $object_name($this->local);
						return $controller->handle($route);
					}
					else
					{
						trigger_error('Cannot find permalink class for object_type "' .
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
			$controller = new APIStructureController($this->local);
			return $controller->handle($args);
		}

		/*
		 * If we haven't found what we're looking for, show the 404 page.
		 */
		return $this->handleNotFound($args);

	} /* function handlePermalinks */

} /* class APIPermalinkController */
