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

// The important stuff: Structures and Laws
Router::addRoute('^/(?P<section_number>[0-9A-Za-z\.]{1,4}-[0-9\.:]{1,10})/',
	'law.php');

Router::addRoute('^/(?P<section>(([0-9A-Za-z\.]{1,8})/)+)',
	'structure.php');
