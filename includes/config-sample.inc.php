<?php

// Define include path, since we need it sometimes.
define('INCLUDE_PATH', $_SERVER['DOCUMENT_ROOT'].'/../includes');

// Append "/includes/" to the include path.
set_include_path(get_include_path().PATH_SEPARATOR.INCLUDE_PATH);

// The file in the /includes/ directory that contains functions custom to this installation.
//define('CUSTOM_FUNCTIONS', 'state-sample.inc.php');

// Which template to use.
define('TEMPLATE', 'default');

// What is the title of the website?
define('SITE_TITLE', 'The State Decoded');

// What does this state call its laws?
define('LAWS_NAME', 'Code of State');

// What is the prefix that indicates a section? In many states, this is §, but in others it might be
// "s".
define('SECTION_SYMBOL', '§');

// Establish which version of the code that's in effect sitewide.
define('EDITION_ID', 1);
define('EDITION_YEAR', 2012);

// Does this state's code include laws that have been repealed formally, and that are marked as
// such?
define('INCLUDES_REPEALED', true);

// Database information.
define('MYSQL_DSN', 'mysql://username:password@localhost/statelaws');

// Specify the title and chapter of the code that contains definitions of terms that are used
// throughout the code, and thus should have a global scope.
//
// IMPORTANT: This is NOT a standard citation method. For instance, "1-2.1" would normally refer to
// title 1, section 2.1. But here it refers to title 1, chapter 2.1. That's because there's simply
// no standard way to cite a title and chapter, so we use this.
define('GLOBAL_DEFINITIONS', '');

# Create a list of the hiearchy of the code, from the top container to the name of an individual
# law.
define('STRUCTURE', 'title,chapter,section');

# Define the PCRE that identifies section references. It is best to do so without using the section
# (§) symbol, since section references are frequently made without its presence.
define('SECTION_PCRE', '/([[0-9]{1,})([0-9A-Za-z\-\.]{0,3})-([0-9A-Za-z\-\.:]*)([0-9A-Za-z]{1,})/');

# Map the above PCRE's stanzas to its corresponding hierarchical labels. It's OK to have duplicates.
# For example, if the PCRE is broken up like (title)(title)-(part)-(section)(section), then list
# "title,title,part,section,section".
define('SECTION_PCRE_STRUCTURE','title,title,section,section');

?>