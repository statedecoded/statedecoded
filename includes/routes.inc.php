<?php

/**
 * Default comes first
 */
Router::addRoute('.*', array('ContentController', 'notFound'));

/**
 * Specific routes next
 */
// Main Index
Router::addRoute('^/$', 'home.php');

// About page
Router::addRoute('^/about/(.*)', 'about.php');

// Browse
Router::addRoute('^/browse/(.*)', 'browse.php');


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
Router::addRoute('^/api/(?P<api_version>1.0)/structure/(?P<identifier>([0-9A-Za-z\.]{1,8}/)*([0-9A-Za-z\.]{1,8}))/',
	'api/1.0/structure.php');

Router::addRoute('^/api/(?P<api_version>1.0)/law/(?P<section>[0-9A-Za-z\.]{1,4}-[0-9\.:]{1,10})/',
	'api/1.0/law.php');

Router::addRoute('^/api/(?P<api_version>1.0)/dictionary/(?P<term>.*)',
	'api/1.0/dictionary.php');

// The important stuff: Structures and Laws
Router::addRoute('^/(?P<section_number>[0-9A-Za-z\.]{1,4}-[0-9\.:]{1,10})/',
	'law.php');

Router::addRoute('^/(?P<identifier>([0-9A-Za-z\.]{1,8}/)*([0-9A-Za-z\.]{1,8}))/',
	'structure.php');
