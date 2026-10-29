<?php

/**
 * HttpCache class
 *
 * Sends the HTTP headers that tell browsers, and any shared cache in front of the site (a CDN
 * such as Cloudflare, a Varnish server, a corporate proxy), how long they may keep a response.
 *
 * Nothing here assumes that a shared cache exists. The default headers are safe for a site with
 * no CDN at all: browsers keep pages for an hour, and shared caches for a day. Only when a cache
 * that the site can purge after an import is configured (see CachePurger) does it also send a
 * long-lived CDN-Cache-Control header, which browsers ignore and CDNs honor.
 *
 * Pages also carry an ETag that changes whenever an edition is imported or the code is updated,
 * so a cache whose copy has expired can check whether it's still good without the page being
 * rebuilt.
 *
 * PHP version 8
 *
 * @license		http://www.gnu.org/licenses/gpl.html GPL 3
 * @version		1.1
 * @link		https://www.statedecoded.com/
 * @since		1.1
 */

class HttpCache
{

	/*
	 * Default lifetimes, in seconds. Each can be overridden with a constant in config.inc.php:
	 * CACHE_BROWSER_TTL, CACHE_SHARED_TTL, and CACHE_CDN_TTL.
	 */
	const BROWSER_TTL = 3600;
	const SHARED_TTL = 86400;
	const CDN_TTL = 2592000;

	/*
	 * How long a cache may serve an expired page while it fetches a fresh copy, and how long it
	 * may go on serving one if the site is down.
	 */
	const STALE_WHILE_REVALIDATE = 86400;
	const STALE_IF_ERROR = 604800;

	/*
	 * A missing page may turn up in the next import, so 404s are kept only briefly.
	 */
	const NOT_FOUND_BROWSER_TTL = 300;
	const NOT_FOUND_SHARED_TTL = 3600;

	/*
	 * How long to keep the content version in Memcached, so that it isn't recomputed on every
	 * request.
	 */
	const VERSION_CACHE_TTL = 60;

	/*
	 * The policy most recently sent, and whether the shutdown check has been registered.
	 */
	protected static $policy = null;
	protected static $shutdown_registered = false;


	/**
	 * Get a lifetime, preferring a value set in config.inc.php over the default.
	 */
	public static function ttl($name, $default)
	{

		$constant = 'CACHE_' . $name . '_TTL';
		if (defined($constant) && is_numeric(constant($constant)) && constant($constant) >= 0)
		{
			return (int) constant($constant);
		}

		return $default;

	}


	/**
	 * Build the headers for a policy, as an array of header names and values.
	 *
	 * The policies are:
	 *
	 *   page      Anything that changes only when an edition is imported. Nearly everything.
	 *   notfound  A 404.
	 *   private   Never stored by anybody: the admin section, form submissions, errors, and
	 *             anything that changes data.
	 *
	 * $purge_configured may be passed to override the check of the site's configuration, which
	 * is useful for testing.
	 */
	public static function headers_for($policy, $purge_configured = null)
	{

		if ($purge_configured === null)
		{
			$purge_configured = CachePurger::configured();
		}

		$stale = 'stale-while-revalidate=' . self::STALE_WHILE_REVALIDATE
			. ', stale-if-error=' . self::STALE_IF_ERROR;

		switch ($policy)
		{

			case 'page':

				$headers = [
					'Cache-Control' => 'public, max-age=' . self::ttl('BROWSER', self::BROWSER_TTL)
						. ', s-maxage=' . self::ttl('SHARED', self::SHARED_TTL)
						. ', ' . $stale
				];

				/*
				 * A CDN that we can purge after an import may keep pages much longer. Browsers
				 * ignore this header, as do shared caches that don't support it, which fall back
				 * on s-maxage above.
				 */
				if ($purge_configured === true)
				{
					$headers['CDN-Cache-Control'] = 'max-age=' . self::ttl('CDN', self::CDN_TTL)
						. ', ' . $stale;
				}

				return $headers;

			case 'notfound':

				return [
					'Cache-Control' => 'public, max-age=' . self::NOT_FOUND_BROWSER_TTL
						. ', s-maxage=' . self::NOT_FOUND_SHARED_TTL
				];

			case 'private':

				return [
					'Cache-Control' => 'private, no-store'
				];

			default:

				throw new InvalidArgumentException('Unknown cache policy "' . $policy . '"');

		}

	}


	/**
	 * Send the headers for a policy, replacing any cache headers already sent.
	 *
	 * Returns false if it's too late to send headers.
	 */
	public static function send($policy)
	{

		$headers = self::headers_for($policy);

		if (headers_sent())
		{
			return false;
		}

		header_remove('Cache-Control');
		header_remove('CDN-Cache-Control');
		header_remove('Expires');
		header_remove('Pragma');

		/*
		 * Only pages carry an ETag. One left on an error or a private response would let a
		 * cache revalidate it into a page.
		 */
		if ($policy !== 'page')
		{
			header_remove('ETag');
		}

		foreach ($headers as $name => $value)
		{
			header($name . ': ' . $value);
		}

		self::$policy = $policy;

		/*
		 * If PHP dies partway through a page, the headers that say to cache it have usually been
		 * sent already. Check for that once the request ends.
		 */
		if (self::$shutdown_registered === false)
		{
			register_shutdown_function(['HttpCache', 'on_shutdown']);
			self::$shutdown_registered = true;
		}

		return true;

	}


	/**
	 * The policy most recently sent, or null if none has been.
	 */
	public static function policy()
	{
		return self::$policy;
	}


	/**
	 * When a request ends in a fatal error or a server error, make sure that the response isn't
	 * cached. This only works if nothing has been output yet, which is usually true here, because
	 * pages are rendered into a string and printed at the end.
	 */
	public static function on_shutdown()
	{

		if (self::$policy === null || self::$policy === 'private' || headers_sent())
		{
			return;
		}

		$error = error_get_last();
		$fatal = E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR;

		if ( ($error !== null && ($error['type'] & $fatal)) || http_response_code() >= 500 )
		{
			self::send('private');
		}

	}


	/**
	 * Get a string that changes whenever anything on the site's pages might: an edition is
	 * imported, created, or made current; the code, theme, or config is changed; or the software
	 * is upgraded.
	 *
	 * Returns false if the editions can't be read, in which case no ETag should be sent.
	 */
	public static function content_version($db, $cache = null)
	{

		$key = 'http-cache-content-version';

		if (isset($cache) && is_object($cache))
		{
			$version = $cache->retrieve($key);
			if ($version !== false && $version !== null)
			{
				return $version;
			}
		}

		/*
		 * Every edition, rather than just the current one, because pages list the editions and
		 * pages for past editions can change too.
		 */
		$statement = $db->prepare('SELECT id, current, last_import, date_modified
				FROM editions
				ORDER BY id');
		if ($statement === false || $statement->execute() === false)
		{
			return false;
		}

		$parts = [
			defined('VERSION') ? VERSION : '',
			json_encode($statement->fetchAll(PDO::FETCH_NUM)),
			self::code_mtime()
		];

		$version = md5(implode('|', $parts));

		if (isset($cache) && is_object($cache))
		{
			$cache->store($key, $version, self::VERSION_CACHE_TTL);
		}

		return $version;

	}


	/**
	 * Get the newest modification time of any file that goes into building a page: the PHP
	 * includes (config included), the page scripts, the theme, its CSS and JavaScript (whose
	 * versioned URLs appear in every page), and the custom content.
	 */
	public static function code_mtime()
	{

		$patterns = [
			INCLUDE_PATH . '/*.php',
			INCLUDE_PATH . '/plugins/*.php',
			WEB_ROOT . '/*.php',
			WEB_ROOT . '/content/*',
			THEME_DIR . '*.php',
			THEME_DIR . 'static/css/*.css',
			THEME_DIR . 'static/js/*.js',
			THEME_DIR . 'static/js/vendor/*.js',
		];

		$newest = 0;
		foreach ($patterns as $pattern)
		{
			foreach (glob($pattern) ?: [] as $file)
			{
				$mtime = @filemtime($file);
				if ($mtime !== false && $mtime > $newest)
				{
					$newest = $mtime;
				}
			}
		}

		return $newest;

	}


	/**
	 * Get the ETag for pages, or false if there isn't one.
	 *
	 * It's a weak ETag because the same page may be sent compressed or not.
	 */
	public static function etag($db, $cache = null)
	{

		$version = self::content_version($db, $cache);
		if ($version === false)
		{
			return false;
		}

		return 'W/"' . $version . '"';

	}


	/**
	 * Determine whether an If-None-Match request header matches our ETag.
	 *
	 * Comparison is weak, as RFC 9110 requires for If-None-Match. Apache's mod_deflate appends
	 * "-gzip" (and mod_brotli "-br") to the ETag of a compressed response, but does not remove it
	 * from the If-None-Match header that comes back, so that suffix is ignored.
	 */
	public static function matches($etag, $if_none_match)
	{

		if (empty($etag) || empty($if_none_match))
		{
			return false;
		}

		if (trim($if_none_match) === '*')
		{
			return true;
		}

		$normalize = function($tag)
		{
			$tag = trim($tag);
			if (strncmp($tag, 'W/', 2) === 0)
			{
				$tag = substr($tag, 2);
			}
			$tag = trim($tag, '"');
			return preg_replace('/-(gzip|br)$/', '', $tag);
		};

		$ours = $normalize($etag);

		foreach (explode(',', $if_none_match) as $theirs)
		{
			if ($normalize($theirs) === $ours)
			{
				return true;
			}
		}

		return false;

	}

}
