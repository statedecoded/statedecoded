<?php

/**
 * The configuration file that drives The State Decoded.
 * 
 * PHP version 5
 *
 * @author		Waldo Jaquith <waldo at jaquith.org>
 * @copyright	2010-2013 Waldo Jaquith
 * @license		http://www.gnu.org/licenses/gpl.html GPL 3
 * @version		0.6
 * @link		http://www.statedecoded.com/
 * @since		0.1
 *
 */

/* 
 * Define base path 
 */

define('BASE_PATH', dirname(dirname(__FILE__)));

/*
 * Define the path to the includes library.
 */
define('INCLUDE_PATH', BASE_PATH . '/includes');

/*
 * Append "/includes/" to the include path.
 */
set_include_path(get_include_path().PATH_SEPARATOR.INCLUDE_PATH);

/*
 * Define web root.
 */
define('WEB_ROOT', BASE_PATH . '/htdocs');

/*
 * The file in the /includes/ directory that contains functions custom to this installation.
 */
define('CUSTOM_FUNCTIONS', 'class.State-sample.inc.php');

/*
 * Which template to use.
 */
define('TEMPLATE', 'default');

/*
 * What is the title of the website?
 */
define('SITE_TITLE', 'The State Decoded');

/*
 * What is the name of the place that these laws govern?
 */
define('PLACE_NAME', 'State');

/*
 * What does this state call its laws?
 */
define('LAWS_NAME', 'Code of State');

/*
 * What is the prefix that indicates a section? In many states, this is §, but in others it might be
 * "s".
 */
define('SECTION_SYMBOL', '§');

/*
 * Establish which version of the code that's in effect sitewide. The ID is the database ID in the
 * "editions" table.
 */
define('EDITION_ID', 1);
define('EDITION_YEAR', 2012);

/*
 * Does this state's code include laws that have been repealed formally, and that are marked as
 * such?
 */
define('INCLUDES_REPEALED', TRUE);

/*
 * The DSN to connect to MySQL.
 */
define('PDO_DSN', 'mysql:dbname=statelaws;host=localhost;charset=utf8');
define('PDO_USERNAME', 'username');
define('PDO_PASSWORD', 'password');

/*
 * Specify the structural identifier ancestry for the unit of the code that contains definitions of
 * terms that are used throughout the code, and thus should have a global scope. Separate each
 * identifier with a comma. If all global definitions are found in Title 15A, Part BD, Chapter 16.2,
 * that would be identified as '15A,BD,16.2'. If all global definitions are found in Article 36,
 * Section 105, that would be identified as '36,105'. This must be the COMPLETE PATH to the
 * container for global definitions, and not a standard citation.
 */
define('GLOBAL_DEFINITIONS', '');

/*
 * Create a list of the hiearchy of the code, from the top container to the name of an individual
 * law.
 */
define('STRUCTURE', 'title,chapter,section');

/*
 * Define the regular expression that identifies section references. It is best to do so without
 * using a section symbol (e.g., §), since section references are frequently made without its
 * presence. A growing collection of per-state regular expressions can be found at
 * <https://github.com/statedecoded/law-identifier>.
 */
define('SECTION_PCRE', '/([[0-9]{1,})([0-9A-Za-z\-\.]{0,3})-([0-9A-Za-z\-\.:]*)([0-9A-Za-z]{1,})/');

/*
 * Map the above PCRE's stanzas to its corresponding hierarchical labels. It's OK to have duplicates.
 * For example, if the PCRE is broken up like (title)(title)-(part)-(section)(section), then list
 * "title,title,part,section,section".
 */
define('SECTION_PCRE_STRUCTURE','title,title,section,section');

/*
 * The path, relative to the webroot, to an error page to be displayed if the database connection is
 * not available. Do not begin this path with a slash. If this is undefined, a bare database
 * connection error will be displayed.
 */
// define('ERROR_PAGE_DB', '')

/**
 * When there is cause to send an e-mail (e.g., API registration), what "From" address should be
 * used? And what name should appear in the "From" field?
 */
define('EMAIL_ADDRESS', '');
define('EMAIL_NAME', SITE_TITLE);


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
