<?php

/**
 * Default comes first
 */
// Anything else gets passed to the Permalink Controller to determine the correct handler.
Router::addRoute('^(?P<route>.*)$', array('PermalinkController', 'handle'));

/**
 * Specific routes next
 */
// Main Index
Router::addRoute('^/$', 'home.php');

// About page
Router::addRoute('^/about/?(.*)', 'about.php');

// Admin section
Router::addRoute('^/admin/?(.*)', 'admin/index.php');

// Downloads and API
Router::addRoute('^/downloads/?(.*)', 'downloads/index.php');

// Search
Router::addRoute('^/search/?(.*)', 'search.php');

// Browse
Router::addRoute('^/browse/?(.*)', array('StructureController', 'handle'));


// New activation
Router::addRoute('^/api-key/activate/(?P<secret>.*)', array('ApiKeyController', 'activateKey'));
// Old activation
Router::addRoute('^/api-key/\?secret=(?P<secret>.*)', array('ApiKeyController', 'activateKey'));
// Create an API Key
Router::addRoute('^/api-key/$', array('ApiKeyController', 'requestKey'));


/**
 * Dynamic routes last, most specific to least specific
 */

// API

Router::addRoute('^/api/((?P<api_version>([0-9]+)\.([0-9]+))/)?(?P<operation>structure|law|)(?P<route>/.*)',
	array('APIPermalinkController', 'handle'));

Router::addRoute('^/api/((?P<api_version>([0-9]+)\.([0-9]+))/)?dictionary/(?P<term>.*)/',
	array('APIDictionaryController', 'handle'));

Router::addRoute('^/api/((?P<api_version>([0-9]+)\.([0-9]+))/)?search/(?P<term>.*)/',
	array('APISearchController', 'handle'));

Router::addRoute('^/api/((?P<api_version>([0-9]+)\.([0-9]+))/)?suggest/(?P<term>.*)/',
	array('APISuggestController', 'handle'));
