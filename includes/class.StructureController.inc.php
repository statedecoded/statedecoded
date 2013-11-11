<?php

/**
 * Structure Controller
 *
 * This controller will handle any structure routes
 *
 * PHP version 5
 *
 * @license		http://www.gnu.org/licenses/gpl.html GPL 3
 * @version		0.8
 * @link		http://www.statedecoded.com/
 * @since		0.8
 *
 */

class StructureController extends BaseController
{

	public function handle($args)
	{
	
		require(WEB_ROOT . '/structure.php');

		$this->render($content);
		
	}
	
}
