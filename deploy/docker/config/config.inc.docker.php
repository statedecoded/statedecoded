<?php

/**
 * Docker runtime config — reads credentials and connection info from environment variables.
 * Copy-safe: real values never need to be committed.
 *
 * Drop non-secret site-identity values (SITE_TITLE, LAWS_NAME, etc.) directly in this file;
 * they are not secrets and change rarely.
 */

if (!defined('INCLUDE_PATH'))
{
	define('INCLUDE_PATH', dirname(__FILE__) . '/');
}

set_include_path(get_include_path() . PATH_SEPARATOR . INCLUDE_PATH);
set_include_path(get_include_path() . PATH_SEPARATOR . INCLUDE_PATH . 'plugins/');

// ------------------------------------------------------------------ identity
define('SITE_TITLE', getenv('SITE_TITLE') ?: 'The State Decoded');
define('PLACE_NAME',  getenv('PLACE_NAME')  ?: 'State');
define('LAWS_NAME',   getenv('LAWS_NAME')   ?: 'Code of State');
define('SECTION_SYMBOL', '§');

// ------------------------------------------------------------------ URLs
$url = 'http://';
if (isset($_SERVER['SERVER_NAME']))
{
	$url .= $_SERVER['SERVER_NAME'];
	if (!empty($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] != '80' && $_SERVER['SERVER_PORT'] != '443')
	{
		$url .= ':' . $_SERVER['SERVER_PORT'];
	}
}
define('SITE_URL', getenv('SITE_URL') ?: $url);

// ------------------------------------------------------------------ paths
define('WEB_ROOT',         isset($_SERVER['DOCUMENT_ROOT']) && $_SERVER['DOCUMENT_ROOT']
                               ? $_SERVER['DOCUMENT_ROOT']
                               : dirname(INCLUDE_PATH) . '/htdocs/');
define('IMPORT_DATA_DIR',  dirname(WEB_ROOT) . '/deploy/import-data/');
define('IMPORT_MEMORY_LIMIT', '256M');
define('CUSTOM_FUNCTIONS', 'class.State.inc.php');
define('THEMES_DIR',       WEB_ROOT . '/themes/');
define('THEME_NAME',       'StateDecoded2013');
define('THEME_DIR',        THEMES_DIR . THEME_NAME . '/');
define('THEME_WEB_PATH',   '/themes/' . THEME_NAME . '/');

// ------------------------------------------------------------------ database
define('PDO_DSN',      getenv('PDO_DSN')      ?: 'mysql:dbname=statedecoded;host=db;charset=utf8');
define('PDO_USERNAME', getenv('MYSQL_USER')     ?: 'statedecoded');
define('PDO_PASSWORD', getenv('MYSQL_PASSWORD') ?: 'statedecoded');

// ------------------------------------------------------------------ admin
define('ADMIN_USERNAME', getenv('ADMIN_USERNAME') ?: 'admin');
define('ADMIN_PASSWORD', getenv('ADMIN_PASSWORD') ?: 'admin');

// ------------------------------------------------------------------ features
define('INCLUDES_REPEALED', true);
define('LAW_LONG_URLS',      false);
define('RECORD_VIEWS',       true);
define('USE_GENERIC_TERMS',  true);
define('CURRENT_API_VERSION', '1.0');

// ------------------------------------------------------------------ section regex (generic)
define('SECTION_REGEX', '/([[0-9]{1,})([0-9A-Za-z\-\.]{0,3})-([0-9A-Za-z\-\.:]*)([0-9A-Za-z]{1,})/');

// ------------------------------------------------------------------ search
define('SEARCH_CONFIG', json_encode([
	'engine' => 'SqlSearchEngine',
]));

// ------------------------------------------------------------------ cache (optional)
if (getenv('CACHE_HOST'))
{
	define('CACHE_HOST', getenv('CACHE_HOST'));
	define('CACHE_PORT', getenv('CACHE_PORT') ?: '11211');
}

// ------------------------------------------------------------------ email
define('EMAIL_ADDRESS', getenv('EMAIL_ADDRESS') ?: '');
define('EMAIL_NAME',    SITE_TITLE);

// ------------------------------------------------------------------ API key (auto-populated by parser)
define('API_KEY', getenv('API_KEY') ?: '');

// ------------------------------------------------------------------ plugins
define('PLUGINS', json_encode([
	'ExportJSON',
	'ExportText',
	'ExportSDXML',
	'ExportHTML',
]));

// ------------------------------------------------------------------ misc
define('VERSION',     '1.0');
define('DEBUG_LEVEL', (int)(getenv('DEBUG_LEVEL') ?: 5));
