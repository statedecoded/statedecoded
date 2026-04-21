<?php

/**
 * PHPStan bootstrap — defines constants and stubs that the app normally
 * gets from config.inc.php and the web server environment.
 */

define('INCLUDE_PATH',         __DIR__ . '/includes/');
define('SITE_TITLE',           'State Decoded');
define('SITE_URL',             'http://localhost/');
define('PLACE_NAME',           'Virginia');
define('LAWS_NAME',            'Code of Virginia');
define('SECTION_SYMBOL',       '§');
define('WEB_ROOT',             __DIR__ . '/htdocs/');
define('IMPORT_DATA_DIR',      __DIR__ . '/import/');
define('IMPORT_MEMORY_LIMIT',  '512M');
define('CUSTOM_FUNCTIONS',     __DIR__ . '/includes/functions.inc.php');
define('THEMES_DIR',           __DIR__ . '/htdocs/themes/');
define('THEME_NAME',           'StateDecoded2013');
define('THEME_DIR',            __DIR__ . '/htdocs/themes/StateDecoded2013/');
define('THEME_WEB_PATH',       '/themes/StateDecoded2013/');
define('CURRENT_API_VERSION',  '1.0');
define('INCLUDES_REPEALED',    FALSE);
define('LAW_LONG_URLS',        FALSE);
define('PDO_DSN',              'mysql:host=localhost;dbname=statedecoded');
define('PDO_USERNAME',         'statedecoded');
define('PDO_PASSWORD',         'statedecoded');
define('ADMIN_USERNAME',       'admin');
define('ADMIN_PASSWORD',       'password');
define('SECTION_REGEX',        '\d+(\.\d+)*-\d+(\.\d+)*');
define('EMAIL_ADDRESS',        'admin@example.com');
define('EMAIL_NAME',           'State Decoded');
define('RECORD_VIEWS',         TRUE);
define('USE_GENERIC_TERMS',    FALSE);
define('SEARCH_CONFIG',        json_encode(['engine' => 'SolrSearchEngine', 'host' => 'localhost', 'port' => 8983, 'path' => '/solr/', 'core' => 'statedecoded', 'timeout' => 30, 'batch_size' => 100, 'omitheader' => false, 'site' => ['identifier' => 'statedecoded', 'name' => 'State Decoded', 'url' => 'http://localhost/']]));
define('API_KEY',              'test-api-key');
define('VERSION',              '1.0');
define('PLUGINS',              json_encode([]));
define('DEBUG_LEVEL',          5);

// Web server stubs
$_SERVER['SERVER_NAME']  = 'localhost';
$_SERVER['SERVER_PORT']  = '80';
$_SERVER['REQUEST_URI']  = '/';
$_SERVER['REDIRECT_URL'] = '/';
$_SERVER['HTTPS']        = '';
