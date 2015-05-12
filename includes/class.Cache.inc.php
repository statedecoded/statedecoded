<?php

/**
 * Caching functionality
 *
 * Interact with Memcached or Redis.
 * 
 * PHP version 5
 *
 * @license		http://www.gnu.org/licenses/gpl.html GPL 3
 * @version		0.9
 * @link		http://www.statedecoded.com/
 * @since		0.82
 *
 */
 
class Cache
{

	function __construct()
	{
		
		/*
		 * If the use of a cache isn't configured, then do not proceed.
		 */
		if (!defined('CACHE_HOST') || !defined('CACHE_PORT'))
		{
			return FALSE;
		}

		/*
		 * Connect to the caching server.
		 */
		$this->cache = new Memcached();
		$this->cache->addServer(CACHE_HOST, CACHE_PORT);
		
		/*
		 * Namespace keys, using the first 8 characters of a hash of the server name.
		 */
		$this->prefix = substr(md5($_SERVER['SERVER_NAME']), 0, 8) . ':';

	}
	
	/*
	 * Add a given item to the cache.
	 *
	 * Default to caching for 1 day.
	 */
	function store($key, $data, $expiration = 86400)
	{
		
		global $cache;
		
		if ( empty($key) || empty($data) )
		{
			return FALSE;
		}
		
		/*
		 * Cache this item in Memcached.
	 	 */
		$this->cache->set($this->prefix . $key, $data, $expiration);
	
		return TRUE;

	}
	
	/*
	 * Erase a given item from cache.
	 */
	function erase($key)
	{
		
		global $cache;
		
		if (empty($key))
		{
			return FALSE;
		}
		
		$result = $this->cache->delete($this->prefix . $key);
		if ($result === FALSE)
		{
			return FALSE;
		}

		return TRUE;

	}

	/*
	 * Retrieve a given item from cache.
	 *
	 * @return str the contents of the cache. FALSE if the retrieval failed.
	 */
	function retrieve($key)
	{
		
		global $cache;
		
		if (empty($key))
		{
			return FALSE;
		}
		
		$result = $this->cache->get($this->prefix . $key);
		if ($result === FALSE)
		{
			return FALSE;
		}
		
		return $result;

	}

	/*
	 * Flush the cache by invalidating all matching items.
	 *
	 * @return TRUE or FALSE.
	 */
	function flush()
	{
		
		global $cache;
		
		/*
		 * Erase every cached item that has the correct prefix. We do this to avoid invalidating
		 * cached items for the whole of Memcached (e.g., other website).
		 */
		$keys = $this->cache->getAllKeys();
		
		if ($keys != FALSE)
		{
		
			foreach ($keys as $index => $key)
			{
				if (strpos($key, $this->prefix) !== FALSE)
				{
					$this->cache->delete($key);
				}
			}
		
		}
		
		return TRUE;

	}

}
