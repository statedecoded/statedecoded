<?php

/**
 * Law Controller
 *
 * This controller will handle any law routes
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

class LawController extends BaseController
{

	public function handle($args)
	{
		require(WEB_ROOT.'/law.php');

		$this->render($content);
	}
	
}
