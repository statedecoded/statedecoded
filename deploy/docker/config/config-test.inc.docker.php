<?php

/**
 * Test config for the Docker environment.
 * Uses a separate database (statedecoded_test) so test runs cannot destroy dev data.
 */

if (!defined('INCLUDE_PATH'))
{
	define('INCLUDE_PATH', dirname(__FILE__) . '/');
}

set_include_path(get_include_path() . PATH_SEPARATOR . INCLUDE_PATH);
set_include_path(get_include_path() . PATH_SEPARATOR . INCLUDE_PATH . 'plugins/');

define('SITE_TITLE',  'The State Decoded (test)');
define('PLACE_NAME',  'State');
define('LAWS_NAME',   'Code of State');
define('SECTION_SYMBOL', '§');
define('SITE_URL',    'http://localhost');

define('WEB_ROOT',         dirname(INCLUDE_PATH) . '/htdocs/');
define('IMPORT_DATA_DIR',  dirname(WEB_ROOT) . '/deploy/import-data/');
define('IMPORT_MEMORY_LIMIT', '256M');
define('CUSTOM_FUNCTIONS', 'class.State.inc.php');
define('THEMES_DIR',       WEB_ROOT . '/themes/');
define('THEME_NAME',       'StateDecoded2013');
define('THEME_DIR',        THEMES_DIR . THEME_NAME . '/');
define('THEME_WEB_PATH',   '/themes/' . THEME_NAME . '/');

// Separate test database — never the same as the dev DB
define('PDO_DSN',      getenv('PDO_DSN_TEST')   ?: 'mysql:dbname=statedecoded_test;host=db;charset=utf8');
define('PDO_USERNAME', getenv('MYSQL_USER')      ?: 'statedecoded');
define('PDO_PASSWORD', getenv('MYSQL_PASSWORD')  ?: 'statedecoded');

define('ADMIN_USERNAME', 'admin');
define('ADMIN_PASSWORD', 'admin');

define('INCLUDES_REPEALED', true);
define('LAW_LONG_URLS',      false);
define('RECORD_VIEWS',       false);
define('USE_GENERIC_TERMS',  true);
define('CURRENT_API_VERSION', '1.0');

define('SECTION_REGEX', '/([[0-9]{1,})([0-9A-Za-z\-\.]{0,3})-([0-9A-Za-z\-\.:]*)([0-9A-Za-z]{1,})/');

define('SEARCH_CONFIG', json_encode([
	'engine' => 'SqlSearchEngine',
]));

define('EMAIL_ADDRESS', '');
define('EMAIL_NAME',    SITE_TITLE);
define('API_KEY',       '');
define('PLUGINS', json_encode([
	'ExportJSON',
	'ExportText',
	'ExportSDXML',
	'ExportHTML',
]));
define('VERSION',       '1.0');
define('DEBUG_LEVEL',   1);

// Required by includes/test/bootstrap.php
define('STATEDECODED_ENV', 'test');
