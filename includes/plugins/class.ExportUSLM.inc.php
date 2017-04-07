<?php

/**
 * USLM XML export.
 *
 * PHP version 5
 *
 * @license		http://www.gnu.org/licenses/gpl.html GPL 3
 * @version		0.9
 * @link		http://www.statedecoded.com/
 * @since		0.9
 *
 */

class ExportUSLM extends Export
{
	public $public_name = 'USLM XML';
	public $format = 'uslm';
	public $extension = '.uslm.xml';

	public function exportLaw($law, $dir, $url)
	{
		$path = $this->createExportDir($dir, $url);

		$file_name = $path . $this->getLawFilename($law, $path);

		/*
		 * Encode all entities as their proper Unicode characters, save for the
		 * few that are necessary in XML.
		 */
		//$law = html_entity_decode_object($law);

		/*
		 * Load the XML string into DOMDocument.
		 */
		$doc = new DOMDocument('1.0', 'UTF-8');

		/*
		 * Set attributes on the main container.
		 */
		$lawDoc = $doc->createElementNS('http://xml.house.gov/schemas/uslm/1.0', 'lawDoc');

		$lawDoc->setAttributeNS('http://www.w3.org/2000/xmlns/' ,'xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');

		$lawDoc->setAttributeNS('http://www.w3.org/2001/XMLSchema-instance', 'schemaLocation',
			'http://xml.house.gov/schemas/uslm/1.0/USLM-1.0.xsd');

		if(defined('SITE_URL'))
		{
			$base = $doc->createAttribute('xml:base');
			$base->value = SITE_URL;
			$base = $doc->createAttribute('identifier');
			$base->value = '/us/usc/t5';
			$lawDoc->appendChild($base);

			$identifier = $doc->createAttribute('identifier');
			$identifier->value = $law->url;
			$lawDoc->appendChild($identifier);
		}

		$doc->appendChild($lawDoc);

		/*
		 * Setup metadata
		 */
		$meta = $doc->createElement('meta');
		$lawDoc->appendChild($meta);

		$meta->appendChild( $doc->createElement('docNumber', $law->section_number) );
		$meta->appendChild( $doc->createElement('longTitle', $law->catch_line) );

		$main = $doc->createElement('main');
		$lawDoc->appendChild($main);

		/*
		 * Create Table of Contents from structures
		 */
		$layout = $doc->createElement('layout');
		$main->appendChild($layout);

		$header = $doc->createElement('header', 'Breadcrumbs');
		$layout->appendChild($header);

		$toc = $doc->createElement('toc');
		$layout->appendChild($toc);

		foreach((array) $law->ancestry as $depth => $structure)
		{
			$tocItem = $doc->createElement('tocItem');
			$toc->appendChild($tocItem);

			$title = $doc->createAttribute('title');
			$title->value = $structure->label . ' ' . $structure->identifier;
			$tocItem->appendChild($title);

			$level = $doc->createAttribute('level');
			$level->value = $depth;
			$tocItem->appendChild($level);

			$url = $doc->createAttribute('url');
			$url->value = (defined('SITE_URL') ? SITE_URL : '') . $structure->url;
			$tocItem->appendChild($url);

			$numberColumn = $doc->createElement('column', $structure->identifier);
			$tocItem->appendChild($numberColumn);

			$value = $doc->createAttribute('value');
			$value->value = $structure->identifier;
			$numberColumn->appendChild($value);

			$role = $doc->createAttribute('role');
			$role->value = 'identifier';
			$numberColumn->appendChild($role);

			$titleColumn = $doc->createElement('column', $structure->name);
			$tocItem->appendChild($titleColumn);

			$role = $doc->createAttribute('role');
			$role->value = 'name';
			$titleColumn->appendChild($role);
		}

		/*
		 * Create the body
		 */

		$section = $doc->createElement('section');
		$main->appendChild($section);

		$num = $doc->createElement('num', $law->section_number);
		$section->appendChild($num);

		$value = $doc->createAttribute('value');
		$value->value = $law->section_number;
		$num->appendChild($value);

		$heading = $doc->createElement('heading', $law->catch_line);
 		$section->appendChild($heading);

 		$content = $doc->createElement('content');
 		$section->appendChild($content);

 		// TODO: Be smarter about handling this.  Just using raw HTML is not great,
 		// we should be handling paragraphs properly.
		$section_content = $doc->createCDATASection($law->html);
		$content->appendChild($section_content);

		return file_put_contents($file_name, $doc->saveXML());
	}

	function renderLawXML($law)
	{

		/*
		 * Get the dictionary terms for this chapter.
		 */
		$dictionary = new Dictionary();
		$dictionary->structure_id = $law->structure_id;
		$dictionary->section_id = $law->section_id;
		$tmp = $dictionary->term_list();
		if ($tmp !== FALSE)
		{
			$terms = (array) $tmp;
			unset($tmp);
		}

		/*
		 * If we've gotten a list of dictionary terms.
		 */
		if ( ($terms !== FALSE) && is_array($terms) )
		{
			/*
			 * Arrange our terms from longest to shortest. This is to ensure that the most specific
			 * terms are defined (e.g. "person of interest") rather than the broadest terms (e.g.
			 * "person").
			 */
			usort($terms, 'sort_by_length');

			/*
			 * Store a list of the dictionary terms as an array, which is required for
			 * preg_replace_callback, the function that we use to insert the definitions.
			 */
			$term_pcres = array();
			foreach ($terms as $term)
			{

				/*
				 * Step through each character in this word.
				 */
				for ($i=0; $i<strlen($term); $i++)
				{
					/*
					 * If there are any uppercase characters, then make this PCRE string case
					 * sensitive.
					 */
					if ( (ord($term{$i}) >= 65) && (ord($term{$i}) <= 90) )
					{
						$term_pcres[] = '/\b'.$term.'(s?)\b(?![^<]*>)/';
						$caps = TRUE;
						break;
					}
				}

				/*
				 * If we have determined that this term does not contain capitalized letters, then
				 * create a case-insensitive PCRE string.
				 */
				if (!isset($caps))
				{
					$term_pcres[] = '/\b'.$term.'(s?)\b(?![^<]*>)/i';
				}

				/*
				 * Unset our flag -- we don't want to have it set the next time through.
				 */
				if (isset($caps))
				{
					unset($caps);
				}
			}
		}

		/*
		 * Instantiate our autolinker, which embeds links. If we've defined a state-custom
		 * autolinker, use that one. Otherwise, use the built-in one. Be sure not to attempt to
		 * autoload a file fitting our class-name schema, since this class, if it exists, would be
		 * found within class.[State].inc.php.
		 */
		if (class_exists('State_Autolinker', FALSE) === TRUE)
		{
			$autolinker = new State_Autolinker;
		}
		$autolinker = new Autolinker;

		/*
		 * Iterate through every section to make some basic transformations.
		 */
		foreach ($law->text as $section)
		{

			/*
			 * Prevent lines from wrapping in the middle of a section identifier.
			 */
			$section->text = str_replace('§ ', '§&nbsp;', $section->text);

			/*
			 * Turn every code reference in every paragraph into a link.
			 */
			$section->text = preg_replace_callback(SECTION_REGEX, array($autolinker, 'replace_sections'), $section->text);

			/*
			 * Use our dictionary to embed dictionary terms in the form of span titles.
			 */
			if (isset($term_pcres))
			{
				$section->text = preg_replace_callback($term_pcres, array($autolinker, 'replace_terms'), $section->text);
			}
		}

		$html = '';

		/*
		 * Iterate through each section of text to display it.
		 */
		$i=0;
		$num_paragraphs = count((array) $law->text);
		foreach ($law->text as $paragraph)
		{

			/*
			 * Identify the prior and next sections, by storing their prefixes.
			 */
			if ($i > 0)
			{
				$paragraph->prior_prefix = $law->text->{$i-1}->entire_prefix;
			}
			if ( ($i+1) < $num_paragraphs )
			{
				$paragraph->next_prefix = $law->text->{$i+1}->entire_prefix;
			}

			/*
			 * If this paragraph's prefix hierarchy is different than that of the prior prefix, then
			 * indicate that this is a new section.
			 */
			if ( !isset($paragraph->prior_prefix) || ($paragraph->entire_prefix != $paragraph->prior_prefix) )
			{
				$html .= '
					<section';
				if (!empty($paragraph->prefix_anchor))
				{
					$html .= ' id="' . $paragraph->prefix_anchor . '"';
				}

				/*
				 * If this is a subsection, indent it.
				 */
				if ($paragraph->level > 1)
				{
					$html .= ' class="indent-' . ($paragraph->level-1) . '"';
				}
				$html .= '>';
			}

			/*
			 * Start a paragraph of the appropriate type.
			 */
			if ($paragraph->type == 'section')
			{
				$html .= '<p>';
			}
			elseif ($paragraph->type == 'table')
			{
				$html .= '<div class="tabular"><pre class="table">';
			}

			/*
			 * If we've got a section prefix, and it's not the same as the last one, then display
			 * it.
			 */
			if 	( !empty($paragraph->prefix)
				&&
				( !isset($paragraph->prior_prefix) || ($paragraph->entire_prefix != $paragraph->prior_prefix) ) )
			{

				$html .= $paragraph->prefix;

				/*
				 * We could use a regular expression to determine if we need to append a period, but
				 * that would be slower.
				 */
				if ( (substr($paragraph->prefix, -1) != ')') && (substr($paragraph->prefix, -1) != '.') )
				{
					$html .= '.';
				}
				$html .= ' ';
			}

			/*
			 * Display this section of text. Purely structural sections lack text of their own (only
			 * their child structures contain text), which is why this is conditional.
			 */
			if (!empty($paragraph->text))
			{
				$html .= $paragraph->text;
			}

			/*
			 * If we've got a section prefix, append a paragraph link to the end of this section.
			 */
			if (!empty($paragraph->prefix) && !defined('EXPORT_IN_PROGRESS'))
			{
				/*
				 * Assemble the permalink
				 */

				$permalink = $_SERVER['REQUEST_URI'] . '#'
					. $paragraph->prefix_anchor;

				$html .= ' <a id="paragraph-' . $paragraph->id . '" class="section-permalink" '
					.'href="' . $permalink . '"><i class="icon-link"></i></a>';
			}
			if ($paragraph->type == 'section')
			{
				$html .= '</p>';
			}
			elseif ($paragraph->type == 'table')
			{
				$html .= '</pre></div>';
			}

			/*
			 * If our next prefix is different than the current prefix, than terminate this section.
			 */
			if	(
					( !isset($paragraph->next_prefix) || ($paragraph->entire_prefix != $paragraph->next_prefix) )
					||
					( ($i+1) === $num_paragraphs)
				)
			{
				$html .= '</section>';
			}
			$i++;
		}

		return $html;

	} // end render()

}
