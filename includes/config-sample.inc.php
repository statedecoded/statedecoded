<?php

/*
 * Define include path, since we need it sometimes.
 */
define('INCLUDE_PATH', $_SERVER['DOCUMENT_ROOT'].'/../includes');

/*
 * Append "/includes/" to the include path.
 */
set_include_path(get_include_path().PATH_SEPARATOR.INCLUDE_PATH);

/*
 * The file in the /includes/ directory that contains functions custom to this installation.
 */
//define('CUSTOM_FUNCTIONS', 'state-sample.inc.php');

/*
 * Which template to use.
 */
define('TEMPLATE', 'default');

/*
 * What is the title of the website?
 */
define('SITE_TITLE', 'The State Decoded');

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
define('INCLUDES_REPEALED', true);

/*
 * The DSN to connect to MySQL.
 */
define('MYSQL_DSN', 'mysql://username:password@localhost/statelaws');

/*
 * Specify the title and chapter of the code that contains definitions of terms that are used
 * throughout the code, and thus should have a global scope.
 *
 * IMPORTANT: This is NOT a standard citation method. For instance, "1-2.1" would normally refer to
 * title 1, section 2.1. But here it refers to title 1, chapter 2.1. That's because there's simply
 * no standard way to cite a title and chapter, so we use this.
 */
define('GLOBAL_DEFINITIONS', '');

/*
 * Create a list of the hiearchy of the code, from the top container to the name of an individual
 * law.
 */
define('STRUCTURE', 'title,chapter,section');

/*
 * Define the PCRE that identifies section references. It is best to do so without using the section
 * (§) symbol, since section references are frequently made without its presence.
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
 * used? This may be in the terse format of "jdoe@example.com", or in the named format of
 * "John Doe <jdoe@example.com>".
 */
define('EMAIL_ADDRESS', SITE_TITLE.' <waldo@jaquith.org>');


/**
 * API Keys
 */

/*
 * The site uses its own API extensively. Provide the API key here. (This is populated automatically
 * at the time that the parser is run.)
 */ 
define('API_KEY', '');
