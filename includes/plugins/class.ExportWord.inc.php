<?php

/**
 * Word docx export.
 *
 * PHP version 5
 *
 * @license   http://www.gnu.org/licenses/gpl.html GPL 3
 * @version   0.9
 * @link    http://www.statedecoded.com/
 * @since   0.9
 */

class ExportWord extends PandocExport
{
	public $format = 'docx';
	public $extension = '.docx';

	public $listeners = array(
		'HTMLExportLaw',
		'HTMLExportStructure',
		'HTMLFinishExport'
	);
}
