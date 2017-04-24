<?php

/**
 * EPUB export.
 *
 * PHP version 5
 *
 * @license   http://www.gnu.org/licenses/gpl.html GPL 3
 * @version   1.0
 * @link    http://www.statedecoded.com/
 * @since   0.9
 */

class ExportEpub extends PandocExport
{
  public $public_name = 'EPUB';
	public $format = 'epub';
	public $extension = '.epub';
  public $description = 'All of the laws in one large EPUB file.';

	public $listeners = array(
		'HTMLExportLaw',
		'HTMLExportStructure',
		'HTMLFinishExport',
    'postGetLaw',
    'showBulkDownload'
	);
}
