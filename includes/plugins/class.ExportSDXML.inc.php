<?php

/**
 * StateDecoded XML export.
 *
 * PHP version 5
 *
 * @license		http://www.gnu.org/licenses/gpl.html GPL 3
 * @version		1.0
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

	public function formatLawForExport($law)
	{
		/*
		 * Load the XML string into DOMDocument.
		 */
		$dom = new DOMWriter('law');

		if(defined('SITE_TITLE'))
		{
			$dom->create('site_title', SITE_TITLE);
		}

		$base_url = '';
		if(defined('SITE_URL'))
		{
			$base_url = SITE_URL;
			$dom->create('site_url', SITE_URL);
		}

		$dom->create('law_id', $law->law_id);
		$dom->create('section_number', $law->section_number);
		$dom->create('catch_line', $law->catch_line);

		if(isset($law->order_by) && $law->order_by)
		{
			$dom->create('order_by', $law->order_by);
		}

		$dom->create('edition', $law->edition->name, array(
			'url' => $base_url . '/' . $law->edition->slug . '/',
			'slug' => $law->edition->slug,
			'current' => $law->edition->current ? 'TRUE' : 'FALSE',
			'last_updated' => date('Y-m-d', strtotime($law->edition->last_import))
		));

		if(isset($law->references) && is_array($law->references) && count($law->references))
		{
			$references =& $dom->create('referred_to_by');

			foreach($law->references as $reference) {
				$references->create('reference', $reference->section_number);
			}
		}

		$structure =& $dom->create('structure');
		foreach(array_reverse($law->ancestry) as $ancestor)
		{
			$args = array(
				'label' => $ancestor->label,
				'level' => $ancestor->depth,
				'order_by' => $ancestor->order_by
			);

			if(!isset($ancestor->metadata) ||
				!isset($ancestor->metadata->admin_division) ||
				$ancestor->metadata->admin_division !== TRUE)
			{
				$args['identifier'] = $ancestor->identifier;
			}

			$structure->create('unit', $ancestor->name, $args);
		}

		$dom->create('text', html_convert_entities($law->html));

		if(isset($law->history))
		{
			$dom->create('history', $law->history);
		}

		if(isset($law->metadata) && $law != new stdClass())
		{
			$dom->create('metadata', $law->metadata);
		}

		/*
		 * Export our DOM as XML.
		 */
		return (string) $dom;
	}

}
