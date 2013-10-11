<?php

/**
 * Permalink Controller
 *
 * This controller will catch any routes in the permalinks table.
 *
 * PHP version 5
 *
 * @author		Bill Hunt <bill at krues8dr dot com>
 * @copyright	2013 Bill Hunt
 * @license		http://www.gnu.org/licenses/gpl.html GPL 3
 * @version		0.8
 * @link		http://www.statedecoded.com/
 * @since		0.8
 *
 */

class PermalinkController extends BaseController
{

	function handle($args)
	{
	
		if ( $args['route'] )
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
			if ( $result !== FALSE && $statement->rowCount() > 0 )
			{

				$route = $statement->fetch(PDO::FETCH_ASSOC);

				/*
				 * Try to intelligently determine if there's a matching controlelr
				 */
				$object_name = str_replace(' ', '', ucwords($route['object_type'])) .
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
		
		/*
		 * If we haven't found what we're looking for, show the 404 page.
		 */
		return $this->notFound($args);
		
	}

	function notFound($args)
	{
	
		$body = '<h1>Content Not Found</h1>
			<p>We were unable to find the content you requested.</p>';

		$content = new Content();
		$content->set('body', $body);

		return $this->render($content);
		
	}
}
