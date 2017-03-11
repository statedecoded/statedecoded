<?php

/**
 * StateDecoded XML export.
 *
 * PHP version 5
 *
 * @license		http://www.gnu.org/licenses/gpl.html GPL 3
 * @version		0.9
 * @link		http://www.statedecoded.com/
 * @since		0.9
 *
 */

class ExportSDXML extends Export
{
	public $public_name = 'XML';
	public $format = 'xml';
	public $extension = '.xml';
	public $description = 'This is the basic data about every law, one XML file per law. These are formatted using the <a href="http://docs.statedecoded.com/xml-format.html">State Decoded XML format</a>';

	public $listeners = array(
		'exportLaw',
		'finishExport',
		'postGetLaw',
		'showBulkDownload'
	);

	public function formatLawForExport($law_original)
	{
		$law = clone($law_original);

		/*
		 * We need to massage the $law object to match the State Decoded XML
		 * standard. The first step towards this is removing unnecessary
		 * elements.
		 */
		unset($law->plain_text);
		unset($law->structure_contents);
		unset($law->next_section);
		unset($law->previous_section);
		unset($law->amendment_years);
		unset($law->dublin_core);
		unset($law->plain_text);
		unset($law->section_id);
		unset($law->structure_id);
		unset($law->edition_id);
		unset($law->full_text);
		unset($law->formats);
		unset($law->html);
		$law->structure = $law->ancestry;
		unset($law->ancestry);
		$law->referred_to_by = $law->references;
		unset($law->references);

		/*
		 * Encode all entities as their proper Unicode characters, save for the
		 * few that are necessary in XML.
		 */
		$law = html_entity_decode_object($law);

		/*
		 * Load the XML string into DOMDocument.
		 */
		$doc = new DOMDocument();
		$dom = $doc->createElement('law');
		$doc->appendChild($dom);

		object_to_dom($law, $doc, $dom);

		/*
		 * We're going to be inserting some things before the catch line.
		 */
		$catch_lines = $dom->getElementsByTagName('catch_line');
		$catch_line = $catch_lines->item(0);

		if(!$dom)
		{
			var_dump('!!', $xml, $dom->saveXML());
			throw new Exception('Couldn\'t find a law in the xml.');
		}

		/*
		 * Add the main site info.
		 */
		if(defined('SITE_TITLE'))
		{
			$site_title = $doc->createElement('site_title');
			$site_title->appendChild($doc->createTextNode(SITE_TITLE));
			$dom->insertBefore($site_title, $catch_line);
		}

		if(defined('SITE_URL'))
		{
			$site_url = $doc->createElement('site_url');
			$site_url->appendChild($doc->createTextNode(SITE_URL));
			$dom->insertBefore($site_url, $catch_line);
		}

		/*
		 * Set the edition.
		 */
		$edition = $doc->createElement('edition');
		$edition->appendChild($doc->createTextNode($law->edition->name));

		$edition_url = $doc->createAttribute('url');
		$edition_url->value = '';
		if(defined('SITE_URL'))
		{
			$edition_url->value = SITE_URL;
		}
		$edition_url->value .= '/' . $law->edition->slug . '/';
		$edition->appendChild($edition_url);

		$edition_id = $doc->createAttribute('id');
		$edition_id->value = $law->edition->id;
		$edition->appendChild($edition_id);

		$edition_last_updated = $doc->createAttribute('last_updated');
		$edition_last_updated->value = date('Y-m-d', strtotime($law->edition->last_import));
		$edition->appendChild($edition_last_updated);

		$edition_current = $doc->createAttribute('current');
		$edition_current->value = $law->edition->current ? 'TRUE' : 'FALSE';
		$edition->appendChild($edition_current);

		$dom->insertBefore($edition, $catch_line);

		/*
		 * Simplify every reference, stripping them down to the cited sections.
		 */
		$referred_to_by = $dom->getElementsByTagName('referred_to_by');
		if ( !empty($referred_to_by) && ($referred_to_by->length > 0) )
		{
			$referred_to_by = $referred_to_by->item(0);
			$references = $referred_to_by->getElementsByTagName('unit');

			/*
			 * Iterate backwards through our elements.
			 */
			for ($i = $references->length; --$i >= 0;)
			{

				$reference = $references->item($i);

				/*
				 * Save the section number.
				 */
				$section_number = trim($reference->getElementsByTagName('section_number')->item(0)->nodeValue);

				/*
				 * Create a new element, named "reference," which contains the only
				 * the section number.
				 */
				$element = $doc->createElement('reference', $section_number);
				$reference->parentNode->insertBefore($element, $reference);

				/*
				 * Remove the "unit" node.
				 */
				$reference->parentNode->removeChild($reference);

			}

		}

		/*
		 * Simplify and reorganize every structural unit.
		 */
		$structure_elements = $dom->getElementsByTagName('structure');
		if ( !empty($structure_elements) && ($structure_elements->length > 0) )
		{
			$structure_element = $structure_elements->item(0);
			$structural_units = $structure_element->getElementsByTagName('unit');

			$dom->insertBefore($structure_element, $catch_line);

			/*
			 * Iterate backwards through our elements.
			 */
			for ($i = $structural_units->length; --$i >= 0;)
			{
				$structure_element->removeChild($structural_units->item($i));
			}

			/*
			 * Build up our structures.
			 * The count/get_object_vars is really fragile, and not a good way to do this.
			 * TODO: Refactor all of $law->structure to be an array, not an object.
			 */
			$level_value = 0;
			for ($i = count(get_object_vars($law->structure))+1; --$i >= 1;)
			{
				$structure = $law->structure->{$i};
				$level_value++;

				$unit = $doc->createElement('unit');

				/*
				 * Add the "level" attribute.
				 */
				$label = trim(strtolower($unit->getAttribute('label')));
				$level = $doc->createAttribute('level');
				$level->value = $level_value;

				$unit->appendChild($level);

				/*
				 * Add the "identifier" attribute.
				 */
				$identifier = $doc->createAttribute('identifier');
				$identifier->value = trim($structure->identifier);
				$unit->appendChild($identifier);

				/*
				 * Add the "url" attribute.
				 */
				$url = $doc->createAttribute('url');

				$url->value = '';
				if(defined('SITE_URL'))
				{
					$url->value = SITE_URL;
				}
				$url->value .= $law->url;

				$unit->appendChild($url);

				/*
				 * Store the name of this structural unit as the contents of <unit>.
				 */
				$unit->nodeValue = trim($structure->name);

				/*
				 * Save these changes.
				 */
				$structure_element->appendChild($unit);
			}

		}

		/*
		 * Rename text units as text sections.
		 */
		$text = $dom->getElementsByTagName('text');
		if (!empty($text) && ($text->length > 0))
		{
			$text = $text->item(0);
			$text_units = $text->getElementsByTagName('unit');

			/*
			 * Iterate backwards through our elements.
			 */
			for ($i = $text_units->length; --$i >= 0;)
			{
				$text_unit = $text_units->item($i);
				renameElement($text_unit, 'section');
			}

		}

		/*
		 * Return the cleaned-up XML.
		 */
		return $doc->saveXML();
	}

}
