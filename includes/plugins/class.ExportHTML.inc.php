<?php

/**
 * HTML export.
 *
 * PHP version 5
 *
 * @license   http://www.gnu.org/licenses/gpl.html GPL 3
 * @version   1.0
 * @link    http://www.statedecoded.com/
 * @since   0.9
 *
 * Since HTML is the base for most of our advanced conversions (EPUB, PDF, etc),
 * this class calls plugins as well!
 */

class ExportHTML extends Export
{
	public $listeners = array(
		'exportLaw',
		'exportStructure',
		'finishExport',
		'postGetLaw',
		'showBulkDownload'
	);

	public $public_name = 'HTML';
	public $format = 'html';
	public $extension = '.html';
	public $description = 'This is the basic data about every law, one HTML
    file per law. Note that any sections that contain colons (e.g.,
    § 8.01-581.12:2) have an underscore in place of the colon in the filename,
    because neither Windows nor Mac OS support colons in filenames.';

	/*
	 * Keep a list of what we've exported, in order.
	 */
	public static $exported = array();

	public $htmlTemplate = '<!doctype html>
<html>
<head>
	<title>{{title}}</title>
</head>
<body>
	<main>
		{{content}}
	</main>
</body>
</html>';

	/*
	 * Abstract the article outside of the main html template.
	 * This allows us to re-use the body for aggregation later.
	 */
	public $bodyTemplate = '<article>
	<header>
		<h1>{{title}}</h1>
	</header>
	{{content}}
</article>';

	public function formatLawForExport($law)
	{
		$title = $law->catch_line . ' (' . SECTION_SYMBOL . ' ' .
			$law->section_number . ') — ' . SITE_TITLE;
		$content = $this->formatLawBody($law);

		return str_replace(
			array(
				'{{title}}',
				'{{content}}'
			),
			array(
				$title,
				$content
			),
			$this->htmlTemplate
		);
	}

	public function formatLawBody($law)
	{
		$title = '<h2>' . SECTION_SYMBOL . '&nbsp;' . $law->section_number . ' – ' .
			$law->catch_line . '<h2>';

		$content = "<section>" .
			$this->sanitizeContent($law->html) .
			"</section>\n";

		return str_replace(
			array(
				'{{title}}',
				'{{content}}'
			),
			array(
				$title,
				$content
			),
			$this->bodyTemplate
		);
	}

	public function formatStructureForExport($structure, $laws)
	{
		$title = ucwords($structure->label) . ' ' . $structure->identifier . ' ' .
			$structure->name;

		$content = $this->formatStructureBody($structure, $laws);

		return str_replace(
			array(
				'{{title}}',
				'{{content}}'
			),
			array(
				$title,
				$content
			),
			$this->htmlTemplate
		);
	}

	public function formatStructureBody($structure, $laws)
	{
		$title = ucwords($structure->label) . ' ' . $structure->identifier .
			' ' . $structure->name;
		$content = '';

		/*
		 * If we have text, show it.
		 */
		if(isset($structure->metadata) && isset($structure->metadata->text))
		{
			$content .= "section" .
				$this->sanitizeContent($structure->metadata->text) .
				"</section>\n";
		}

		/*
		 * If there are child structures under this one, list them.
		 */
		$children = $structure->list_children();
		if($children)
		{
			$content .= "<section>This {$structure->label} contains the following:\n<ul>\n";

			foreach($children as $child)
			{
				$label = ucwords($child->label);
				$content .= "<li>{$label} {$child->identifier} {$child->name}</li>\n";
			}

			$content .= "</ul>\n</section>\n";
		}

		/*
		 * List the laws in this structure.
		 */
		if(is_array($laws) && count($laws) > 0)
		{
			$content .= "<section>This {$structure->label} is comprised of the following sections:\n<ul>\n";

			foreach($laws as $law)
			{
				$content .= "<li>" . SECTION_SYMBOL . "{$law->section_number} {$law->catch_line}</li>\n";
			}

			$content .= "</ul>\n</section>\n";
		}

		# TODO: Handle History, Ed. Note, etc.

		return str_replace(
			array(
				'{{title}}',
				'{{content}}'
			),
			array(
				$title,
				$content
			),
			$this->bodyTemplate
		);
	}

	public function sanitizeContent($content)
	{
		/*
		 * We must strip out most html, including links, since we don't the
		 * context the file will be used in.
		 */
		# TODO: fix images.
		return strip_tags($content,
					'<p><br><section><div><pre><ul><li><table><tr><td><i><b><em><strong>');
	}

	/*
	 * Wrappers to add HTML-specific plugin hooks.
	 */

	public function exportLaw($law, $dir)
	{
		list($filename, $content) = parent::exportLaw($law, $dir);

		/*
		 * Keep track of our exports.
		 */
		self::$exported[] = array(
			'type' => 'law',
			'identifier' => $law->section_number,
			'name' => $law->catch_line,
			'filename' => $filename,
			'content' => $this->formatLawBody($law)
		);

		$this->events->trigger('HTMLExportLaw', $law, $dir, $filename, $content);

		return array($filename, $content);
	}

	public function exportStructure($structure, $laws, $dir)
	{
		list($filename, $content) = parent::exportStructure($structure, $laws, $dir);

		/*
		 * Keep track of our exports.
		 */
		self::$exported[] = array(
			'type' => 'structure',
			'identifier' => $structure->identifier,
			'name' => $structure->name,
			'label' => $structure->label,
			'filename' => $filename,
			'content' => $this->formatStructureBody($structure, $laws)
		);

		$this->events->trigger('HTMLExportStructure', $structure, $laws, $dir, $filename, $content);

		return array($filename, $content);
	}

	public function finishExport($downloads_dir)
	{
		$result = parent::finishExport($downloads_dir);

		$this->events->trigger('HTMLFinishExport', self::$exported, $downloads_dir);

		return $result;
	}
}
