<?php

/**
 * Structure Controller
 *
 * This controller will handle any structure routes
 *
 * PHP version 8
 *
 * @license		http://www.gnu.org/licenses/gpl.html GPL 3
 * @version		1.0
 * @link		http://www.statedecoded.com/
 * @since		0.8
 *
 */

class StructureController extends BaseController
{

	public function handle($args)
	{
		/*
		 * Make controller local variables available to structure.php.
		 * NOTE: Using extract() is a security risk, so we assign variables explicitly instead.
		 */
		foreach ($this->local as $__key => $__value)
		{
			$$__key = $__value;
		}
		unset($__key, $__value);

		require(WEB_ROOT.'/structure.php');

		$this->render($content);
	}

}
