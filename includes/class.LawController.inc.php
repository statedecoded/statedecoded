<?php

/**
 * Law Controller
 *
 * This controller will handle any law routes
 *
 * PHP version 5
 *
 * @license		http://www.gnu.org/licenses/gpl.html GPL 3
 * @version		1.0
 * @link		http://www.statedecoded.com/
 * @since		0.8
 *
 */

class LawController extends BaseController
{

	public function handle($args)
	{
		/*
		 * Put our local variables into the local scope.
		 */
		extract($this->local);

		require(WEB_ROOT.'/law.php');

		$this->render($content);
	}

}
