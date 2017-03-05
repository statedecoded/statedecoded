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
	public $public_name = 'MS Word';
	public $format = 'doc';
	public $extension = '.docx';
  public $description = 'All of the laws in one large Word Doc.';

	public $listeners = array(
		'HTMLExportLaw',
		'HTMLExportStructure',
		'HTMLFinishExport',
		'postGetLaw',
    'showBulkDownload'
	);
}
