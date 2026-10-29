<?php

/**
 * View Controller
 *
 * Records that a law was viewed. Law pages report their views with a small request from the
 * browser, rather than recording them while the page is built, because a page served from a
 * browser's cache or a CDN never reaches the server.
 *
 * PHP version 8
 *
 * @license		http://www.gnu.org/licenses/gpl.html GPL 3
 * @version		1.1
 * @link		https://www.statedecoded.com/
 * @since		1.1
 *
 */

class ViewController
{

	protected $local;

	public function __construct($local = [])
	{
		$this->local = $local;
	}

	/**
	 * Record a view of the law named in the POSTed "section" field. Responds with no content.
	 */
	public function record($args)
	{

		http_response_code(204);

		if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST')
		{
			http_response_code(405);
			header('Allow: POST');
			return '';
		}

		if ( defined('RECORD_VIEWS') && (RECORD_VIEWS === false) )
		{
			return '';
		}

		$section = trim((string) ($_POST['section'] ?? ''));
		if ($section === '' || strlen($section) > 255)
		{
			return '';
		}

		$db = $this->local['db'] ?? null;
		if (!$db)
		{
			return '';
		}

		/*
		 * Only record views of laws that exist, so that the table can't be filled with junk.
		 */
		$statement = $db->prepare('SELECT 1 FROM laws WHERE section = :section LIMIT 1');
		if ($statement === false
			|| $statement->execute([':section' => $section]) === false
			|| $statement->fetchColumn() === false)
		{
			return '';
		}

		$law = new Law(['db' => $db]);
		$law->section_number = $section;
		$law->record_view();

		return '';

	}

}
