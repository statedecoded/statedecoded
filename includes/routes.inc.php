<?php

/**
 * Default comes first
 */
// Anything else gets passed to the Permalink Controller to determine the correct handler.
$router->addRoute('default', '^(?P<route>.*)$', ['PermalinkController', 'handle']);

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
$router->addRoute('editions', '^/editions/?(.*)', ['EditionController', 'handle']);

// Search
$router->addRoute('search', '^/search/?(.*)', 'search.php');

// API key activation (now handled by the downloads page; see ApiKeyController)
$router->addRoute('api-activate', '^/api-key/activate/(?P<secret>[^/]+)/?$', ['ApiKeyController', 'activateKey']);
// API key requests, and old activation links of the form /api-key/?secret=...
$router->addRoute('api-create', '^/api-key/$', ['ApiKeyController', 'requestKey']);

// Record a view of a law (POSTed by law pages)
$router->addRoute('record-view', '^/record-view/$', ['ViewController', 'record']);


/**
 * Dynamic routes last, most specific to least specific
 */

// API

$router->addRoute('api-list',
	'^/api/((?P<api_version>([0-9]+)\.([0-9]+))/)?(?P<operation>structure|law|)(?P<route>/.*)',
	['APIPermalinkController', 'handle']);

$router->addRoute('api-dictionary',
	'^/api/((?P<api_version>([0-9]+)\.([0-9]+))/)?dictionary/((?P<term>.*)/)?',
	['APIDictionaryController', 'handle']);

$router->addRoute('api-search',
	'^/api/((?P<api_version>([0-9]+)\.([0-9]+))/)?search/(?P<term>.*)/',
	['APISearchController', 'handle']);

$router->addRoute('api-suggest',
	'^/api/((?P<api_version>([0-9]+)\.([0-9]+))/)?suggest/(?P<term>.*)/',
	['APISuggestController', 'handle']);
