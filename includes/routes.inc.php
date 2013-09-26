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
Router::addRoute('^/about/(.*)', 'about.php');

// Admin section
Router::addRoute('^/admin/(.*)', 'admin/index.php');

// Downloads and API
Router::addRoute('^/downloads/(.*)', 'downloads/index.php');

// Search
Router::addRoute('^/search/(.*)', 'search.php');

// Browse
Router::addRoute('^/browse/(.*)', array('StructureController', 'handle'));


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

// Structure
Router::addRoute('^/api/((?P<api_version>([0-9]+)\.([0-9]+))/)?structure/(?P<identifier>([0-9A-Za-z\.]{1,8}/)*([0-9A-Za-z\.]{1,8}))/',
	'api/1.0/structure.php');

// Law
Router::addRoute('^/api/((?P<api_version>([0-9]+)\.([0-9]+))/)?law/(?P<section>[0-9A-Za-z\.]{1,4}-[0-9\.:]{1,10})/?',
	'api/1.0/law.php');

// Dictionary
Router::addRoute('^/api/((?P<api_version>([0-9]+)\.([0-9]+))/)?dictionary/(?P<term>.*)',
	'api/1.0/dictionary.php');
