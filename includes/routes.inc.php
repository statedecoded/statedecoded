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

Router::addRoute('^/admin/(.*)', 'admin/index.php');
Router::addRoute('^/downloads/(.*)', 'downloads/index.php');

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

// Latest version
Router::addRoute('^/api/structure/(?P<identifier>([0-9A-Za-z\.]{1,8}/)*([0-9A-Za-z\.]{1,8}))/',
	'api/1.0/structure.php');

// Other version
Router::addRoute('^/api/(?P<api_version>1.0)/structure/(?P<identifier>([0-9A-Za-z\.]{1,8}/)*([0-9A-Za-z\.]{1,8}))/',
	'api/1.0/structure.php');

// Latest version
Router::addRoute('^/api/law/(?P<section>[0-9A-Za-z\.]{1,4}-[0-9\.:]{1,10})/',
	'api/1.0/law.php');

// Other version
Router::addRoute('^/api/(?P<api_version>1.0)/law/(?P<section>[0-9A-Za-z\.]{1,4}-[0-9\.:]{1,10})/',
	'api/1.0/law.php');

// Latest version
Router::addRoute('^/api/dictionary/(?P<term>.*)',
	'api/1.0/dictionary.php');

// Other version
Router::addRoute('^/api/(?P<api_version>1.0)/dictionary/(?P<term>.*)',
	'api/1.0/dictionary.php');

// The important stuff: Structures and Laws
Router::addRoute('^/(?P<section_number>[0-9A-Za-z\.]{1,4}-[0-9\.:]{1,10})/',
	'law.php');

Router::addRoute('^/(?P<identifier>([0-9A-Za-z\.]{1,8}/)*([0-9A-Za-z\.]{1,8}))/',
	'structure.php');
