<?php

/**
 * Structure Controller
 *
 * This controller will handle any structure routes
 *
 * PHP version 5
 *
 * @license		http://www.gnu.org/licenses/gpl.html GPL 3
 * @version		0.9
 * @link		http://www.statedecoded.com/
 * @since		0.8
 *
 */

class EditionController extends BaseController
{

	public function handle($args)
	{
		require(WEB_ROOT . '/edition.php');

		$this->render($content);
	}

}
