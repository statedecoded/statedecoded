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
		'exportDictionary',
		'postGetLaw',
		'showBulkDownload'
	);

	public $public_name = 'JSON';
	public $format = 'json';
	public $extension = '.json';
	public $description = 'This is the basic data about every law, one JSON file
			per law. Fields include section, catch line, text, history, and structural
			ancestry (i.e., title number/name and chapter number/name). Note that any
			sections that contain colons (e.g., § 8.01-581.12:2) have an underscore in
			place of the colon in the filename, because neither Windows nor Mac OS
			support colons in filenames.';
	public $dictionary_description = 'All terms defined in the laws, with each
			term’s definition, the section in which it is defined, and the scope
			(section, chapter, title, global) of that definition.';

	public function formatLawForExport($law)
	{
		return json_encode($law);
	}

	public function formatDictionaryForExport($dictionary)
	{
		return json_encode($dictionary);
	}
}
