<?php

/**
 * EPUB export.
 *
 * PHP version 5
 *
 * @license   http://www.gnu.org/licenses/gpl.html GPL 3
 * @version   0.9
 * @link    http://www.statedecoded.com/
 * @since   0.9
 */

class ExportEpub extends PandocExport
{
	public $format = 'epub';
	public $extension = '.epub';

	public $listeners = array(
		'HTMLExportLaw',
		'HTMLExportStructure',
		'HTMLFinishExport'
	);
}
