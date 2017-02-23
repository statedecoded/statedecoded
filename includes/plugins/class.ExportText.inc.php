<?php

/**
 * Text export.
 *
 * PHP version 5
 *
 * @license		http://www.gnu.org/licenses/gpl.html GPL 3
 * @version		0.9
 * @link		http://www.statedecoded.com/
 * @since		0.9
 *
 */

class ExportText extends Export
{
  public $listeners = array(
    'exportLaw',
    'finishExport'
  );

  public $format = 'txt';
  public $extension = '.txt';

  public function formatLawForExport($law)
  {
    return $law->plain_text;
  }
}
