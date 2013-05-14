<?php

/**
 * Varnish interactions
 *
 * Interact with the Varnish server, should there be one.
 * 
 * PHP version 5
 *
 * @author		Waldo Jaquith <waldo at jaquith.org>
 * @copyright	2013 Waldo Jaquith
 * @license		http://www.gnu.org/licenses/gpl.html GPL 3
 * @version		0.7
 * @link		http://www.statedecoded.com/
 * @since		0.7
 *
 */
 
class Varnish
{
	
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
			return FALSE;
		}
		
		/*
		 * Set our Varnish options.
		 */
		$options = array(
			CURLOPT_URL				=>	'http://' . $_SERVER['SERVER_NAME'] . '/',
			CURLOPT_CUSTOMREQUEST	=>	'BAN',
			CURLOPT_RETURNTRANSFER	=>	TRUE,
			CURLOPT_HTTPHEADER		=> 	array ('Host: ' . VARNISH_HOST ),
		);
		
		/*
		 * If a URL has been specified, then replace the default URL (for the entire site)
		 */
		if (isset($this->url))
		{
			$options['CURLOPT_URL'] = $this->url;
		}
		
		/*
		 * Run cURL to execute the URL ban (as Varnish calls it).
		 */
		$request = curl_init();
		curl_setopt_array($request, $options);
		curl_exec($request);
		
		return TRUE;
		
	}
	
}
		
?>