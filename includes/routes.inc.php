<?php

/**
 * Default comes first
 */
// Anything else gets passed to the Permalink Controller to determine the correct handler.
$router->addRoute('default', '^(?P<route>.*)$', array('PermalinkController', 'handle'));

/**
 * Specific routes next
 */
// Main Index
$router->addRoute('home', '^/$', 'home.php');

// About page
$router->addRoute('about', '^/about/?(.*)', 'about.php');

// Admin section
$router->addRoute('admin', '^/admin(/.*)?$', 'admin/index.php');

// Downloads and API
$router->addRoute('downloads', '^/downloads/?(.*)', 'downloads/index.php');

// Editions list
$router->addRoute('editions', '^/editions/?(.*)', array('EditionController', 'handle'));

// Search
$router->addRoute('search', '^/search/?(.*)', 'search.php');

// Browse
$router->addRoute('browse', '^/browse/?((?P<edition>.*?)/)?$', array('StructureController', 'handle'));


// New activation
$router->addRoute('api-activate', '^/api-key/activate/(?P<secret>.*)', array('ApiKeyController', 'activateKey'));
// Old activation
$router->addRoute('api-activate-legacy', '^/api-key/\?secret=(?P<secret>.*)', array('ApiKeyController', 'activateKey'));
// Create an API Key
$router->addRoute('api-create', '^/api-key/$', array('ApiKeyController', 'requestKey'));


/**
 * Dynamic routes last, most specific to least specific
 */

// API

$router->addRoute('api-list',
	'^/api/((?P<api_version>([0-9]+)\.([0-9]+))/)?(?P<operation>structure|law|)(?P<route>/.*)',
	array('APIPermalinkController', 'handle'));

$router->addRoute('api-dictionary',
	'^/api/((?P<api_version>([0-9]+)\.([0-9]+))/)?dictionary/((?P<term>.*)/)?',
	array('APIDictionaryController', 'handle'));

$router->addRoute('api-search',
	'^/api/((?P<api_version>([0-9]+)\.([0-9]+))/)?search/(?P<term>.*)/',
	array('APISearchController', 'handle'));

$router->addRoute('api-suggest',
	'^/api/((?P<api_version>([0-9]+)\.([0-9]+))/)?suggest/(?P<term>.*)/',
	array('APISuggestController', 'handle'));
