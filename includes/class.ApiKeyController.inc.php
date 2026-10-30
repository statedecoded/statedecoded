<?php

/**
 * API Key Controller
 *
 * API keys are requested and activated on the downloads page. These redirects keep the old
 * /api-key/ addresses working, including activation links in e-mails sent before that move.
 *
 * PHP version 8
 *
 * @license		http://www.gnu.org/licenses/gpl.html GPL 3
 * @version		1.3
 * @link		https://www.statedecoded.com/
 * @since		1.3
 *
 */

class ApiKeyController
{

	protected $local;

	public function __construct($local = [])
	{
		$this->local = $local;
	}

	/**
	 * Send a request for /api-key/ to the downloads page, carrying along any activation secret
	 * (old e-mails link to /api-key/?secret=...).
	 */
	public function requestKey($args)
	{
		$secret = $_GET['secret'] ?? '';
		return $this->redirect(is_string($secret) ? $secret : '');
	}

	/**
	 * Send /api-key/activate/<secret> to the downloads page, which activates the key.
	 */
	public function activateKey($args)
	{
		return $this->redirect((string) ($args['secret'] ?? ''));
	}

	/**
	 * Permanently redirect to the downloads page.
	 */
	protected function redirect($secret)
	{
		$url = '/downloads/';
		if ($secret !== '')
		{
			$url .= '?secret=' . rawurlencode($secret);
		}

		http_response_code(301);
		header('Location: ' . $url);
		return '';
	}

}
