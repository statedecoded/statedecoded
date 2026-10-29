<?php

/**
 * CachePurger class
 *
 * Empties the shared caches in front of the site after an import or an edition change, so that
 * visitors get the new laws right away. Every cache is optional: each is purged only if it is
 * configured in config.inc.php, and with none configured this does nothing.
 *
 * The caches supported are:
 *
 *   Varnish     Configured with VARNISH_HOST.
 *   Cloudflare  Configured with CLOUDFLARE_ZONE_ID and CLOUDFLARE_API_TOKEN. The token needs
 *               only the "Zone > Cache Purge" permission.
 *
 * PHP version 8
 *
 * @license		http://www.gnu.org/licenses/gpl.html GPL 3
 * @version		1.1
 * @link		https://www.statedecoded.com/
 * @since		1.1
 */

class CachePurger
{

	const CLOUDFLARE_API = 'https://api.cloudflare.com/client/v4/zones/';

	/*
	 * How long to wait for a cache to respond, in seconds.
	 */
	const TIMEOUT = 15;


	/**
	 * List the caches that are configured.
	 */
	public static function backends()
	{

		$backends = [];

		if (defined('VARNISH_HOST') && VARNISH_HOST != '')
		{
			$backends[] = 'Varnish';
		}

		if (defined('CLOUDFLARE_ZONE_ID') && CLOUDFLARE_ZONE_ID != ''
			&& defined('CLOUDFLARE_API_TOKEN') && CLOUDFLARE_API_TOKEN != '')
		{
			$backends[] = 'Cloudflare';
		}

		return $backends;

	}


	/**
	 * Whether any shared cache can be purged. HttpCache lets such a cache keep pages longer.
	 */
	public static function configured()
	{
		return count(self::backends()) > 0;
	}


	/**
	 * Purge every configured cache.
	 *
	 * Returns an array of the caches purged, with a value of true for each one that succeeded
	 * and an error message for each one that failed. A failure never throws, so that it can't
	 * interrupt an import that has otherwise finished.
	 */
	public function purge()
	{

		$results = [];

		foreach (self::backends() as $backend)
		{

			try
			{
				$method = 'purge_' . strtolower($backend);
				$results[$backend] = $this->$method();
			}
			catch (Throwable $e)
			{
				$results[$backend] = $e->getMessage();
			}

		}

		return $results;

	}


	/**
	 * Purge every configured cache, and record the outcome with a logger, if one is provided.
	 */
	public function purge_and_log($logger = null)
	{

		$results = $this->purge();

		if (!isset($logger) || !is_object($logger))
		{
			return $results;
		}

		foreach ($results as $backend => $result)
		{
			if ($result === true)
			{
				$logger->message('Purged the ' . $backend . ' cache.', 5);
			}
			else
			{
				$logger->message('Could not purge the ' . $backend . ' cache: ' . $result
					. ' Visitors may see the previous version of pages until the cache expires.',
					10);
			}
		}

		return $results;

	}


	/**
	 * Purge Varnish.
	 */
	protected function purge_varnish()
	{

		$varnish = new Varnish;
		if ($varnish->purge() !== true)
		{
			return 'Varnish did not accept the request.';
		}

		return true;

	}


	/**
	 * Purge everything that Cloudflare has cached for the zone.
	 *
	 * Purging everything, rather than a list of URLs, is the only way to be sure of catching
	 * every page, since an import can change nearly all of them.
	 */
	protected function purge_cloudflare()
	{

		if (!defined('CLOUDFLARE_ZONE_ID') || !defined('CLOUDFLARE_API_TOKEN'))
		{
			return 'Cloudflare is not configured.';
		}

		$request = curl_init(self::CLOUDFLARE_API . rawurlencode(CLOUDFLARE_ZONE_ID) . '/purge_cache');
		curl_setopt_array($request, [
			CURLOPT_POST			=> true,
			CURLOPT_POSTFIELDS		=> json_encode(['purge_everything' => true]),
			CURLOPT_RETURNTRANSFER	=> true,
			CURLOPT_CONNECTTIMEOUT	=> 5,
			CURLOPT_TIMEOUT			=> self::TIMEOUT,
			CURLOPT_HTTPHEADER		=> [
				'Authorization: Bearer ' . CLOUDFLARE_API_TOKEN,
				'Content-Type: application/json',
			],
		]);

		$body = curl_exec($request);
		$status = curl_getinfo($request, CURLINFO_HTTP_CODE);
		$error = curl_error($request);
		curl_close($request);

		if ($body === false)
		{
			return 'Could not reach the Cloudflare API (' . $error . ').';
		}

		$response = json_decode($body);

		if ($status == 200 && is_object($response) && !empty($response->success))
		{
			return true;
		}

		/*
		 * Report Cloudflare's own explanation, which is usually specific (e.g., a bad token).
		 */
		$messages = [];
		if (is_object($response) && !empty($response->errors))
		{
			foreach ($response->errors as $cf_error)
			{
				$messages[] = $cf_error->message ?? '';
			}
		}

		$messages = array_filter($messages);

		return 'Cloudflare responded with HTTP ' . $status
			. (count($messages) ? ' (' . implode('; ', $messages) . ')' : '') . '.';

	}

}
