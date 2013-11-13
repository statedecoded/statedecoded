<?php

/**
 * The configuration file that drives The State Decoded.
 *
 * PHP version 5
 *
 * @license		http://www.gnu.org/licenses/gpl.html GPL 3
 * @version		0.8
 * @link		http://www.statedecoded.com/
 * @since		0.1
 *
 */

/*
 * Append the includes directory to the include path.
 */
set_include_path(get_include_path() . PATH_SEPARATOR . INCLUDE_PATH);

/*
 * What is the title of the website?
 */
define('SITE_TITLE', 'The State Decoded');

/*
 * What is the name of the place that these laws govern?
 */
define('PLACE_NAME', 'State');

/*
 * What does this place call its laws?
 */
define('LAWS_NAME', 'Code of State');

/*
 * What is the prefix that indicates a section? In many states, this is §, but in others it might be
 * "s".
 */
define('SECTION_SYMBOL', '§');

/*
 * Define the web root -- the directory in which index.php is found.
 */
define('WEB_ROOT', $_SERVER['DOCUMENT_ROOT']);

/*
 * Define the location of the files to import.
 */
define('IMPORT_DATA_DIR', WEB_ROOT . '/admin/import-data/');

/*
 * The file in the /includes/ directory that contains functions custom to this installation.
 */
define('CUSTOM_FUNCTIONS', 'class.State-sample.inc.php');

/*
 * The directory in which templates are stored.
 */
define('TEMPLATE_DIR', WEB_ROOT . '/themes/');

/*
 * Which theme to use.
 */
define('THEME_NAME', 'StateDecoded2013');
define('THEME_DIR', TEMPLATE_DIR . THEME_NAME . '/');
define('THEME_WEB_PATH', '/themes/' . THEME_NAME . '/');

/*
 * Define the default version of the API to send requests to, if a version isn't othewise specified.
 */
define('CURRENT_API_VERSION', '1.0');

/*
 * Does this state's code include laws that have been repealed formally, and that are marked as
 * such?
 */
define('INCLUDES_REPEALED', TRUE);

/*
 * Should we use short URLs or long URLs for laws? Short URLs are the default (e.g.,
 * <http://example.com/12.3-45:67/>), but if laws have non-unique identifiers, then you'll need to
 * use long URLs (e.g. <http://example.com/56/21/12.3-45:67/>), which are URLs that incorporate
 * the structures that contain each law.
 */
define('LAW_LONG_URLS', FALSE);

/*
 * The DSN to connect to MySQL.
 */
define('PDO_DSN', 'mysql:dbname=statedecoded;host=localhost;charset=utf8');
define('PDO_USERNAME', 'username');
define('PDO_PASSWORD', 'password');

/*
 * Specify the structural identifier ancestry for the unit of the code that contains definitions of
 * terms that are used throughout the code, and thus should have a global scope. Separate each
 * identifier with a comma. If all global definitions are found in Title 15A, Part BD, Chapter 16.2,
 * that would be identified as '15A,BD,16.2'. If all global definitions are found in Article 36,
 * Section 105, that would be identified as '36,105'. This must be the COMPLETE PATH to the
 * container for global definitions, and not a standard citation.
 *
 * Not all legal codes need this. This only needs to be specified for those legal codes that list
 * globally applicable definitions but that don't specify within the list of definitions that they
 * are globally applicable. For instance, a legal code might set aside a chapter to list all global
 * definitions, and use the first law in the chapter to say "all following laws apply globally,"
 * and then have 100 more laws, each containing a single definition. This is a legal code for which
 * this configuration option is necessary.
 */
//define('GLOBAL_DEFINITIONS', '');

/*
 * Define the regular expression that identifies section references. It is best to do so without
 * using a section symbol (e.g., §), since section references are frequently made without its
 * presence. A growing collection of per-state regular expressions can be found at
 * <https://github.com/statedecoded/law-identifier>.
 */
define('SECTION_REGEX', '/([[0-9]{1,})([0-9A-Za-z\-\.]{0,3})-([0-9A-Za-z\-\.:]*)([0-9A-Za-z]{1,})/');

/*
 * The path, relative to the webroot, to an error page to be displayed if the database connection is
 * not available. Do not begin this path with a slash. If this is undefined, a bare database
 * connection error will be displayed.
 */
// define('ERROR_PAGE_DB', '')

/*
 * When there is cause to send an e-mail (e.g., API registration), what "From" address should be
 * used? And what name should appear in the "From" field?
 */
define('EMAIL_ADDRESS', '');
define('EMAIL_NAME', SITE_TITLE);

/*
 * Record each view of each law in the laws_views table? Doing so provides a corpus of data that can
 * be useful for analysis, data that will be drawn on in future releases of The State Decoded, but
 * that at present is not used for anything. This is done via MySQL's INSERT DELAYED, so it will not
 * slow down page rendering time, but it does require a certain amount of system resources and
 * storage.
 */
define('RECORD_VIEWS', TRUE);

/*
 * The URL for your installation of Solr. End with a trailing slash.
 */
define('SOLR_URL', 'http://localhost:8080/solr/statedecoded/');

/**
 * API Keys
 */

/*
 * The site uses its own API extensively. Provide the API key here. (This is populated automatically
 * at the time that the parser is run.)
 */
define('API_KEY', '');

/*
 * If you want to enable Disqus <http://www.disqus.com/> commenting for every law, register for
 * Disqus, create a new site, and enter the assigned Disqus shortname here.
 */
// define('DISQUS_SHORTNAME', '');

/*
 * If you're running a Varnish server, and you want The State Decoded to automatically purge expired
 * content, provide the URL (including the port number) here.
 */
// define('VARNISH_HOST', 'http://127.0.0.1:80/');

/*
 * If you want to track traffic stats with Google Analytics, provide your site's web property ID
 * here.
 */
// define('GOOGLE_ANALYTICS_ID', 'UA-XXXXX-X');

/*
 * If you have a Portfolio-level Typekit account, enter the Typekit ID for your website here. The
 * Typekit ID is found in the HTML snippet that Typekit provides you with, like such:
 *
 * <script type="text/javascript" src="http://use.typekit.net/abc1efg.js"></script>
 * <script type="text/javascript">try{Typekit.load();}catch(e){}</script>
 *
 * The Typekit ID is "abc1efg".
 */
// define('TYPEKIT_ID', 'abc1efg');

/*
 * If you want to display court decisions that affect each law using CourtListener's REST API
 * <https://www.courtlistener.com/api/rest-info/>, you must register for an account and enter your
 * username and password here. See the get_court_decisions() method in class.State-sample.inc.php
 * for more.
 */
// define('COURTLISTENER_USERNAME', 'jane_doe');
// define('COURTLISTENER_PASSWORD', 's3cr3tp@ssw0rd');
