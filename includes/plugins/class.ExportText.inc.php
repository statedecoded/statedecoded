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
    'exportStructure',
    'finishExport'
  );

  public $format = 'txt';
  public $extension = '.txt';

  public function formatLawForExport($law)
  {
    return $law->plain_text;
  }

  public function formatStructureForExport($structure, $laws = array())
  {
    $content = ucwords($structure->label) . ' ' . $structure->identifier . ': ' .
      $structure->name;

    /*
     * If we have text, show it.
     */
    if(isset($structure->metadata) && isset($structure->metadata->text))
    {
      $content .= "\n\n" . $structure->metadata->text;
    }

    /*
     * If there are child structures under this one, list them.
     */
    $children = $structure->list_children();
    if($children) {
      $content .= "\n\nThis {$structure->label} contains the following:\n\n";

      foreach($children as $child) {
        $content .= "{$child->identifier} {$child->name}\n";
      }
    }

    /*
     * List the laws in this structure.
     */
    if(is_array($laws) && count($laws) > 0) {
      $content .= "\n\nThis {$structure->label} is comprised of the following sections:\n\n";

      foreach($laws as $law) {
        $content .= "§{$law->section_number} {$law->catch_line}\n";
      }
    }

    return $content;
  }
}
