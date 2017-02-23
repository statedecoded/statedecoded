<?php

/**
 * JSON export.
 *
 * PHP version 5
 *
 * @license		http://www.gnu.org/licenses/gpl.html GPL 3
 * @version		0.9
 * @link		http://www.statedecoded.com/
 * @since		0.9
 *
 */

class ExportJSON extends Export
{
	public $listeners = array(
		'exportLaw',
		'finishExport',
		'exportDictionary'
	);

	public $format = 'json';
	public $extension = '.json';

	public function formatLawForExport($law)
	{
		return json_encode($law);
	}

	public function formatDictionaryForExport($dictionary)
	{
		return json_encode($dictionary);
	}
}
