<?php

/**
 * Varnish interactions
 *
 * Interact with the Varnish server, should there be one.
 * 
 * PHP version 8
 *
 * @license		http://www.gnu.org/licenses/gpl.html GPL 3
 * @version		1.1
 * @link		https://www.statedecoded.com/
 * @since		0.7
 *
 */
 
class Varnish
{

	/*
	 * A single URL to purge. If unset, the whole site is purged.
	 */
	public $url = null;
	
	/*
	 * Remove a given URL (or all URLs) from Varnish. If $this->url is not set, then it will
	 * remove all URLs. Otherwise, only the specified URL will be removed.
	 */
	function purge()
	{
		
		/*
		 * If VARNISH_HOST isn't defined, we cannot interact with Varnish.
		 */
		if (!defined('VARNISH_HOST'))
		{
			return false;
		}
		
		/*
		 * Send the request to Varnish itself, naming the site in the Host header, so that this
		 * works from the command line (where imports run) as well as from the web. Without a
		 * specific URL, the whole site is banned.
		 */
		$url = rtrim(VARNISH_HOST, '/') . '/';
		if (isset($this->url))
		{
			$url = rtrim(VARNISH_HOST, '/') . '/' . ltrim(parse_url($this->url, PHP_URL_PATH) ?? '', '/');
		}

		$site_host = defined('SITE_URL') ? parse_url(SITE_URL, PHP_URL_HOST) : null;
		if (empty($site_host))
		{
			$site_host = $_SERVER['SERVER_NAME'] ?? 'localhost';
		}

		$options = [
			CURLOPT_URL				=>	$url,
			CURLOPT_CUSTOMREQUEST	=>	'BAN',
			CURLOPT_RETURNTRANSFER	=>	true,
			CURLOPT_CONNECTTIMEOUT	=>	5,
			CURLOPT_TIMEOUT			=>	15,
			CURLOPT_HTTPHEADER		=> 	['Host: ' . $site_host],
		];

		/*
		 * Run cURL to execute the URL ban (as Varnish calls it).
		 */
		$request = curl_init();
		curl_setopt_array($request, $options);
		$result = curl_exec($request);
		$status = curl_getinfo($request, CURLINFO_HTTP_CODE);
		curl_close($request);

		return ($result !== false && $status >= 200 && $status < 300);
		
	}
	
}
		
?>
