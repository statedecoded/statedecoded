<?php

/**
 * The core function library for The State Decoded.
 *
 * PHP version 5
 *
 * @author		Waldo Jaquith <waldo at jaquith.org>
 * @copyright	2010-2013 Waldo Jaquith
 * @license		http://www.gnu.org/licenses/gpl.html GPL 3
 * @version		0.6
 * @link		http://www.statedecoded.com/
 * @since		0.1
*/

/**
 * Autoload any class file when it is called.
 */
function __autoload($class_name)
{

	$filename = 'class.' . $class_name . '.inc.php';
	if ((include_once INCLUDE_PATH . '/' . $filename) === FALSE)
	{
		throw new Exception("Could not include `$filename'.");
	}
	
}


/**
 * Get the contents of a given URL. A wrapper for cURL.
 */
function fetch_url($url)
{

	if (!isset($url))
	{
		return FALSE;
	}
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
	curl_setopt($ch, CURLOPT_TIMEOUT_MS, 1200);
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

	/* Set CURLOPT_PROTOCOLS to protect against exploitation of CVE-2013-0249 that
	 * affects cURL 7.26.0 to and including 7.28.1.
	 * http://curl.haxx.se/docs/adv_20130206.html
	 * http://www.h-online.com/open/news/item/cURL-goes-wrong-1800880.html
	 */
	$allowed_protocols = CURLPROTO_HTTP | CURLPROTO_HTTPS;
	curl_setopt($ch, CURLOPT_PROTOCOLS, $allowed_protocols);
	curl_setopt($ch, CURLOPT_REDIR_PROTOCOLS, $allowed_protocols & ~(CURLPROTO_FILE | CURLPROTO_SCP));

	$html = curl_exec($ch);
	curl_close($ch);
	return $html;
	
}


/**
 * Ensure that a JSONP callback doesn't contain any reserved terms.
 * By Brett Wejrowski <http://stackoverflow.com/questions/2777021/do-i-need-to-sanitize-the-callback-parameter-from-a-jsonp-call/10900911#10900911>
 */
function valid_jsonp_callback($callback)
{
    return !preg_match( '/[^0-9a-zA-Z\$_]|^(abstract|boolean|break|byte|case|catch|char|class|const|continue|debugger|default|delete|do|double|else|enum|export|extends|false|final|finally|float|for|function|goto|if|implements|import|in|instanceof|int|interface|long|native|new|null|package|private|protected|public|return|short|static|super|switch|synchronized|this|throw|throws|transient|true|try|typeof|var|volatile|void|while|with|NaN|Infinity|undefined)$/', $callback);
}


/**
 * Finds linkable strings of text within laws and turns them into links.
 */
class Autolinker
{

	/**
	 * Make these arrays available so that we can manipulate them, if need be. There's no need to
	 * feed these to Autolinker directly, because under real-world circumstances, these can always
	 * be plucked from the globals.
	 *
	 * This is completely unnecessary for the replace_sections() method, but it doesn't do any harm.
	 */
	function __construct()
	{
	
		global $terms;
		$this->terms = $terms;
		$this->term_blacklist = array();
		
	}
	
	/**
	 * This is used as the preg_replace_callback function that inserts dictionary links into text.
	 */
	function replace_terms($term)
	{
		
		if (!isset($term))
		{
			return FALSE;
		}
		
		/*
		 * If the provided term is an array of terms, just use the first one. This might seem odd,
		 * but note that this function is written to be used within preg_replace_callback(), the
		 * PCRE provides an array-based word listing, and we only want the first one.
		 */
		if (is_array($term))
		{
			$term = $term[0];
		}
		
		/*
		 * If we have already marked this term as blacklisted -- that is, as a word that is a subset
		 * of a longer term -- then just return the term without marking it as a dictionary term.
		 */
		if (in_array(strtolower($term), $this->term_blacklist))
		{
			return $term;
		}
	
		/*
		 * Determine whether this term is made up of multiple words, so that we can eliminate any
		 * terms from our arrays of terms that are any of the individual words that make up this
		 * term. That is, if this term is "person or people," and "person" is another term in our
		 * array, then we want to drop "person," to avoid display overlapping terms.
		 */
		$num_spaces = substr_count($term, ' ');
		
		if ($num_spaces > 0)
		{

			/*
			 * Use that separator to break the term up into an array of words.
			 */
			$term_components = explode(' ', $term);
			
			/*
			 * Step through each the the words that make up this phrase, and add each of them to
			 * the blacklist, so that we can skip this word next time it appears in this law.
			 */
			foreach ($term_components as $word)
			{
				$this->term_blacklist[] = strtolower($word);
			}
			
			/*
			 * Now step through each two-word sub-phrase that make up this 3+-word phrase (assuming
			 * that there are any) and add each of them to the blacklist.
			 */
			if ($num_spaces > 1)
			{
				for ($i=0; $i<$num_spaces; $i++)
				{
					$this->term_blacklist[] = strtolower($term_components[$i].' '.$term_components[$i+1]);
				}
			}
		}

		return '<span class="dictionary">'.$term.'</span>';
	}


	/**
	 * This is used as the preg_replace_callback function that inserts section links into text.
	 */
	function replace_sections($matches)
	{
	
		/*
		 * PCRE provides an array-based match listing. We only want the first one.
		 */
		$match = $matches[0];
		
		/*
		 * If the section symbol prefixes this match, hack it off.
		 */
		if (substr($match, 0, strlen(SECTION_SYMBOL)) == SECTION_SYMBOL)
		{
			$match = substr($match, (strlen(SECTION_SYMBOL.' ')));
		}
	
		/*
		 * Create an instance of the Law class.
		 */
		$law = new Law;
		
		/*
		 * Just find out if this law exists.
		 */
		$law->section_number = $match;
		$section = $law->exists();
		
		/*
		 * If this isn't a valid section number, then just return the match verbatim -- there's no
		 * link to be provided.
		 */
		if ($section === FALSE)
		{
			return $matches[0];
		}
		
		return '<a class="law" href="/'.$match.'/">'.$matches[0].'</a>';
	}
}


/**
 * Send an error message formatted as JSON. This requires the text of an error message.
 */
function json_error($text)
{

	if (!isset($text))
	{
		return FALSE;
	}
	$error = array('error',
		array(
			'message' => 'An Error Occurred',
			'details' => $text
		)
	);
	$error = json_encode($error);
	
	/*
	 * Return a 400 "Bad Request" error. This indicates that the request was invalid. Whether this
	 * is the best HTTP header is unclear.
	 */
	header("HTTP/1.0 400 OK");
	
	/*
	 * Send an HTTP header defining the content as JSON.
	 */
	header('Content-type: application/json');
	echo $error;
	
}


/**
 * Throw a 404.
 */
function send_404()
{
	include ($_SERVER['DOCUMENT_ROOT'] . '/404.php');
	exit();
}


/**
 * This is relied on by usort() in law.php and by extract_definitions().
 */
function sort_by_length($a, $b)
{
	return strlen($b) - strlen($a);
}


/**
 * The following four functions were pulled out of WordPress 3.5. They've been modified somewhat, in
 * order to remove the use of a pair of internal WordPress functions (_x and apply_filters(), and
 * also to replace WordPress’ use of entities with the use of actual Unicode characters.
 */

/**
 * Replaces common plain text characters into formatted entities
 *
 * As an example,
 * <code>
 * 'cause today's effort makes it worth tomorrow's "holiday"...
 * </code>
 * Becomes:
 * <code>
 * ’cause today’s effort makes it worth tomorrow’s “holiday”&#8230;
 * </code>
 * Code within certain html blocks are skipped.
 *
 * @since 0.71
 * @uses $wp_cockneyreplace Array of formatted entities for certain common phrases
 *
 * @param string $text The text to be formatted
 * @return string The string replaced with html entities
 */
function wptexturize($text) {
	global $wp_cockneyreplace;
	//static $opening_quote, $closing_quote, $default_no_texturize_tags, $default_no_texturize_shortcodes, $static_characters, $static_replacements, $dynamic_characters, $dynamic_replacements;

	// No need to set up these static variables more than once
	if ( empty( $opening_quote ) ) {
		/* translators: opening curly quote */
		$opening_quote = '“';
		/* translators: closing curly quote */
		$closing_quote = '”';

		$default_no_texturize_tags = array('pre', 'code', 'kbd', 'style', 'script', 'tt');
		$default_no_texturize_shortcodes = array('code');

		// if a plugin has provided an autocorrect array, use it
		if ( isset($wp_cockneyreplace) ) {
			$cockney = array_keys($wp_cockneyreplace);
			$cockneyreplace = array_values($wp_cockneyreplace);
		} else {
			$cockney = array("'tain't","'twere","'twas","'tis","'twill","'til","'bout","'nuff","'round","'cause");
			$cockneyreplace = array("’tain’t","’twere","’twas","’tis","’twill","’til","’bout","’nuff","’round","’cause");
		}

		$static_characters = array_merge(array('---', ' -- ', '--', ' - ', 'xn–', '...', '``', '\'\'', ' (tm)'), $cockney);
		$static_replacements = array_merge(array('—', ' — ', '–', ' – ', 'xn--', ' .&thinsp;.&thinsp;. ', $opening_quote, $closing_quote, ' ™'), $cockneyreplace);

		$dynamic_characters = array('/\'(\d\d(?:’|\')?s)/', '/\'(\d)/', '/(\s|\A|[([{<]|")\'/', '/(\d)"/', '/(\d)\'/', '/(\S)\'([^\'\s])/', '/(\s|\A|[([{<])"(?!\s)/', '/"(\s|\S|\Z)/', '/\'([\s.]|\Z)/', '/\b(\d+)x(\d+)\b/');
		$dynamic_replacements = array('’$1','’$1', '$1‘', '$1&″', '$1′', '$1’$2', '$1' . $opening_quote . '$2', $closing_quote . '$1', '’$1', '$1&×$2');
	}

	// Transform into regexp sub-expression used in _wptexturize_pushpop_element
	// Must do this everytime in case plugins use these filters in a context sensitive manner
	$no_texturize_tags = '(' . implode('|', $default_no_texturize_tags) . ')';
	$no_texturize_shortcodes = '(' . implode('|', $default_no_texturize_shortcodes ) . ')';

	$no_texturize_tags_stack = array();
	$no_texturize_shortcodes_stack = array();

	$textarr = preg_split('/(<.*>|\[.*\])/Us', $text, -1, PREG_SPLIT_DELIM_CAPTURE);

	foreach ( $textarr as &$curl ) {
		if ( empty( $curl ) )
			continue;

		// Only call _wptexturize_pushpop_element if first char is correct tag opening
		$first = $curl[0];
		if ( '<' === $first ) {
			_wptexturize_pushpop_element($curl, $no_texturize_tags_stack, $no_texturize_tags, '<', '>');
		} elseif ( '[' === $first ) {
			_wptexturize_pushpop_element($curl, $no_texturize_shortcodes_stack, $no_texturize_shortcodes, '[', ']');
		} elseif ( empty($no_texturize_shortcodes_stack) && empty($no_texturize_tags_stack) ) {
			// This is not a tag, nor is the texturization disabled static strings
			$curl = str_replace($static_characters, $static_replacements, $curl);
			// regular expressions
			$curl = preg_replace($dynamic_characters, $dynamic_replacements, $curl);
		}
		$curl = preg_replace('/&([^#])(?![a-zA-Z1-4]{1,8};)/', '&#038;$1', $curl);
	}
	return implode( '', $textarr );
}

/**
 * Search for disabled element tags. Push element to stack on tag open and pop
 * on tag close. Assumes first character of $text is tag opening.
 *
 * @access private
 * @since 2.9.0
 *
 * @param string $text Text to check. First character is assumed to be $opening
 * @param array $stack Array used as stack of opened tag elements
 * @param string $disabled_elements Tags to match against formatted as regexp sub-expression
 * @param string $opening Tag opening character, assumed to be 1 character long
 * @param string $opening Tag closing  character
 * @return object
 */
function _wptexturize_pushpop_element($text, &$stack, $disabled_elements, $opening = '<', $closing = '>') {
	// Check if it is a closing tag -- otherwise assume opening tag
	if (strncmp($opening . '/', $text, 2)) {
		// Opening? Check $text+1 against disabled elements
		if (preg_match('/^' . $disabled_elements . '\b/', substr($text, 1), $matches)) {
			/*
			 * This disables texturize until we find a closing tag of our type
			 * (e.g. <pre>) even if there was invalid nesting before that
			 *
			 * Example: in the case <pre>sadsadasd</code>"baba"</pre>
			 *          "baba" won't be texturize
			 */

			array_push($stack, $matches[1]);
		}
	} else {
		// Closing? Check $text+2 against disabled elements
		$c = preg_quote($closing, '/');
		if (preg_match('/^' . $disabled_elements . $c . '/', substr($text, 2), $matches)) {
			$last = array_pop($stack);

			// Make sure it matches the opening tag
			if ($last != $matches[1])
				array_push($stack, $last);
		}
	}
}

/**
 * Replaces double line-breaks with paragraph elements.
 *
 * A group of regex replaces used to identify text formatted with newlines and
 * replace double line-breaks with HTML paragraph tags. The remaining
 * line-breaks after conversion become <<br />> tags, unless $br is set to '0'
 * or 'false'.
 *
 * @since 0.71
 *
 * @param string $pee The text which has to be formatted.
 * @param bool $br Optional. If set, this will convert all remaining line-breaks after paragraphing. Default true.
 * @return string Text which has been converted into correct paragraph tags.
 */
function wpautop($pee, $br = true) {
	$pre_tags = array();

	if ( trim($pee) === '' )
		return '';

	$pee = $pee . "\n"; // just to make things a little easier, pad the end

	if ( strpos($pee, '<pre') !== false ) {
		$pee_parts = explode( '</pre>', $pee );
		$last_pee = array_pop($pee_parts);
		$pee = '';
		$i = 0;

		foreach ( $pee_parts as $pee_part ) {
			$start = strpos($pee_part, '<pre');

			// Malformed html?
			if ( $start === false ) {
				$pee .= $pee_part;
				continue;
			}

			$name = "<pre wp-pre-tag-$i></pre>";
			$pre_tags[$name] = substr( $pee_part, $start ) . '</pre>';

			$pee .= substr( $pee_part, 0, $start ) . $name;
			$i++;
		}

		$pee .= $last_pee;
	}

	$pee = preg_replace('|<br />\s*<br />|', "\n\n", $pee);
	// Space things out a little
	$allblocks = '(?:table|thead|tfoot|caption|col|colgroup|tbody|tr|td|th|div|dl|dd|dt|ul|ol|li|pre|select|option|form|map|area|blockquote|address|math|style|p|h[1-6]|hr|fieldset|noscript|samp|legend|section|article|aside|hgroup|header|footer|nav|figure|figcaption|details|menu|summary)';
	$pee = preg_replace('!(<' . $allblocks . '[^>]*>)!', "\n$1", $pee);
	$pee = preg_replace('!(</' . $allblocks . '>)!', "$1\n\n", $pee);
	$pee = str_replace(array("\r\n", "\r"), "\n", $pee); // cross-platform newlines
	if ( strpos($pee, '<object') !== false ) {
		$pee = preg_replace('|\s*<param([^>]*)>\s*|', "<param$1>", $pee); // no pee inside object/embed
		$pee = preg_replace('|\s*</embed>\s*|', '</embed>', $pee);
	}
	$pee = preg_replace("/\n\n+/", "\n\n", $pee); // take care of duplicates
	// make paragraphs, including one at the end
	$pees = preg_split('/\n\s*\n/', $pee, -1, PREG_SPLIT_NO_EMPTY);
	$pee = '';
	foreach ( $pees as $tinkle )
		$pee .= '<p>' . trim($tinkle, "\n") . "</p>\n";
	$pee = preg_replace('|<p>\s*</p>|', '', $pee); // under certain strange conditions it could create a P of entirely whitespace
	$pee = preg_replace('!<p>([^<]+)</(div|address|form)>!', "<p>$1</p></$2>", $pee);
	$pee = preg_replace('!<p>\s*(</?' . $allblocks . '[^>]*>)\s*</p>!', "$1", $pee); // don't pee all over a tag
	$pee = preg_replace("|<p>(<li.+?)</p>|", "$1", $pee); // problem with nested lists
	$pee = preg_replace('|<p><blockquote([^>]*)>|i', "<blockquote$1><p>", $pee);
	$pee = str_replace('</blockquote></p>', '</p></blockquote>', $pee);
	$pee = preg_replace('!<p>\s*(</?' . $allblocks . '[^>]*>)!', "$1", $pee);
	$pee = preg_replace('!(</?' . $allblocks . '[^>]*>)\s*</p>!', "$1", $pee);
	if ( $br ) {
		$pee = preg_replace_callback('/<(script|style).*?<\/\\1>/s', '_autop_newline_preservation_helper', $pee);
		$pee = preg_replace('|(?<!<br />)\s*\n|', "<br />\n", $pee); // optionally make line breaks
		$pee = str_replace('<WPPreserveNewline />', "\n", $pee);
	}
	$pee = preg_replace('!(</?' . $allblocks . '[^>]*>)\s*<br />!', "$1", $pee);
	$pee = preg_replace('!<br />(\s*</?(?:p|li|div|dl|dd|dt|th|pre|td|ul|ol)[^>]*>)!', '$1', $pee);
	$pee = preg_replace( "|\n</p>$|", '</p>', $pee );

	if ( !empty($pre_tags) )
		$pee = str_replace(array_keys($pre_tags), array_values($pre_tags), $pee);

	return $pee;
}

/**
 * Newline preservation help function for wpautop
 *
 * @since 3.1.0
 * @access private
 * @param array $matches preg_replace_callback matches array
 * @return string
 */
function _autop_newline_preservation_helper( $matches ) {
	return str_replace("\n", "<WPPreserveNewline />", $matches[0]);
}


/**
 * Turn the variables provided by each page into a rendered page.
 */
class Page
{
	
	/**
	 * A shortcut for all steps necessary to turn variables into an output page.
	 */
	function parse()
	{
		Page::render();
		Page::display();
	}
	
	
	/**
	 * Combine the populated variables with the template.
	 */
	function render()
	{
	
		/*
		 * Save the contents of the template file to a variable. First check APC and see if it's
		 * stored there.
		 */
		if ( APC_RUNNING === TRUE)
		{
			$this->html = apc_fetch('template-'.TEMPLATE);
			if ($this->html === FALSE)
			{
				$this->html = file_get_contents(INCLUDE_PATH . '/templates/' . TEMPLATE . '.inc.php');
				apc_store('template-'.TEMPLATE, $this->html);
			}
		}
		else
		{
			$this->html = file_get_contents(INCLUDE_PATH . '/templates/' . TEMPLATE . '.inc.php');
		}
		
		/*
		 * Create the browser title.
		 */
		if (!isset($field->browser_title))
		{
			if (isset($field->page_title))
			{
				$field->browser_title .= $field->page_title;
				$field->browser_title .= '—' . SITE_TITLE;
			}
			else
			{
				$field->browser_title .= SITE_TITLE;
			}
		}
		else
		{
			$field->browser_title .= '—' . SITE_TITLE;
		}
		
		/*
		 * Replace all of our in-page tokens with our defined variables.
		 */
		foreach ($this->field as $field=>$contents)
		{
			$this->html = str_replace('{{' . $field . '}}', $contents, $this->html);
		}
		
		/*
		 * Erase any unpopulated tokens that remain in our template.
		 */
		$this->html = preg_replace('/{{[0-9a-z_]+}}/', '', $this->html);
		
		/*
		 * Erase selected containers, if they're empty.
		 */
		$this->html = preg_replace('/<section id="sidebar">(\s*)<\/section>/', '', $this->html);
		$this->html = preg_replace('/<nav id="intercode">(\s*)<\/nav>/', '', $this->html);
		$this->html = preg_replace('/<nav id="breadcrumbs">(\s*)<\/nav>/', '', $this->html);
	}
	
	/**
	 * Send the page to the browser.
	 */
	function display()
	{
	
		if (!isset($this->html))
		{
			return FALSE;
		}
		
		echo $this->html;
		return TRUE;
		
	}
}


/**
 * 
 */
class Dictionary
{
	
	/**
	 * Get the definition for a given term for a given section of code.
	 */
	function define_term()
	{
		
		/*
		 * We're going to need access to the database connection throughout this class.
		 */
		global $db;
		
		/*
		 * If no term has been defined, there is nothing to be done.
		 */
		if (!isset($this->term))
		{
			return FALSE;
		}
		
		/*
		 * Determine the structural heritage of the provided section number and store it in an
		 * array.
		 */
		if (isset($this->section_number))
		{
			$heritage = new Law;
			$heritage->config->get_structure = TRUE;
			$heritage->section_number = $this->section_number;
			$law = $heritage->get_law();
			$ancestry = array();
			foreach ($law->ancestry as $tmp)
			{
				$ancestry[] = $tmp->id;
			}
		}
		
		/*
		 * We want to check if the term is in all caps. If it is, then we want to keep it in
		 * all caps to query the database. Otherwise, we lowercase it. That is, "Board" should be looked
		 * up as "board," but "NAIC" should be looked up as "NAIC."
		 */
		for ($i=0; $i<strlen($this->term); $i++)
		{
			/*
			 * If there are any uppercase characters, then make this PCRE string case
			 * sensitive.
			 */
			if ( (ord($this->term{$i}) >= 97) && (ord($this->term{$i}) <= 122) )
			{
				$lowercase = TRUE;
				break;
			}
		}
		
		if ($lowercase === TRUE)
		{
			$this->term = strtolower($this->term);
		}
		
		/*
		 * If the last character in this word is an "s," then it might be a plural, in which
		 * case we need to search for this and without its plural version.
		 */
		if (substr($this->term, -1) == 's')
		{
			$plural = TRUE;
		}
		
		/*
		 * This is a tortured assembly of a query. The idea is to provide flexibility on a pair of
		 * axes. The first is to support both plural and singular terms. The second is to support
		 * queries with and without section numbers, to provide either the one true definition for
		 * a term within a given scope or all definitions in the whole code.
		 */
		$sql = 'SELECT dictionary.term, dictionary.definition, dictionary.scope,
				laws.section AS section_number
				FROM dictionary
				LEFT JOIN laws
					ON dictionary.law_id=laws.id
				WHERE (dictionary.term=' . $db->quote($this->term);
		if ($plural === TRUE)
		{
			$sql .= ' OR dictionary.term = ' . $db->quote(substr($this->term, 0, -1));
		}
		$sql .= ') ';
		if (isset($this->section_number))
		{
			$sql .= 'AND (';
			foreach ($ancestry as $structure_id)
			{
				$sql .= '(dictionary.structure_id = ' . $db->quote($structure_id) . ') OR';
			}
			$sql .= '	(dictionary.scope = "global")
					OR
						(laws.section = ' . $db->quote($this->section_number) . ')
					) ';
		}
		
		$sql .= 'ORDER BY dictionary.scope_specificity ';
		if (isset($this->section_number))
		{
		
			$sql .= 'LIMIT 1';
		}
		
		$result = $db->query($sql);

		/*
		 * If the query succeeds, great, retrieve it.
		 */
		if ( ($result !== FALSE) && ($result->rowCount() > 0) )
		{
		
			/*
			 * Get all results.
			 */
			$dictionary = new stdClass();
			$i=0;
			while ($term = $result->fetch(PDO::FETCH_OBJ))
			{
				$term->url = 'http://' . $_SERVER['SERVER_NAME'] . '/' . $term->section_number . '/';
				$term->formatted = wptexturize($term->definition) . ' (<a href="' . $term->url . '">'
					. $term->section_number . '</a>)';
				$dictionary->$i = $term;
				$i++;
			}
		}
		
		/*
		 * Else if the query fails, then the term is found in the generic terms dictionary.
		 */
		else
		{
		
			/*
			 * Assemble the SQL.
			 */
			$sql = 'SELECT term, definition, source, source_url AS url
					FROM dictionary_general
					WHERE term=' . $db->quote($this->term);
			if ($plural === TRUE)
			{
				$sql .= ' OR term = ' . $db->quote(substr($this->term, 0, -1));
			}
			$sql .= ' LIMIT 1';
			
			$result = $db->query($sql);
			
			/*
			 * If the query fails, or if no results are found, return false -- we have no terms for
			 * this structural unit.
			 */
			if ( ($result === FALSE) || ($result->rowCount() < 1) )
			{
				return FALSE;
			}
		
			/*
			 * Get the first result. Assemble a slightly different response than for a custom term.
			 * We assign this to the first element of an object because that is the format that the
			 * API expects to receive a list of terms in. In this case, we have just one term.
			 */
			$dictionary->{0} = $result->fetch(PDO::FETCH_OBJ);
			$dictionary->{0}->formatted = wptexturize($dictionary->{0}->definition) .
				' (<a href="' . $dictionary->{0}->url . '">' . $dictionary->{0}->source . '</a>)';

		}
		
		return $dictionary;
		
	}
		
	/**
	 * Get a list of defined terms for a given structural unit of the code, returning just a listing
	 * of terms. (The idea is that we can use an Ajax call to get each definition on demand.)
	 */
	function term_list()
	{

		/*
		 * We're going to need access to the database connection throughout this class.
		 */
		global $db;
		
		/*
		 * If a structural ID hasn't been passed to this function, then return a listing of terms
		 * that apply to the entirety of the code.
		 */
		if (!isset($this->structure_id) && !isset($this->scope))
		{
			$this->scope = 'global';
		}
		
		/*
		 * Get a listing of all structural units that contain the current structural unit -- that is,
		 * if this is a chapter, get the ID of both the chapter and the title. And so on.
		 */
		if (isset($this->structure_id))
		{
			$heritage = new Structure;
			$heritage->id = $this->structure_id;
			$ancestry = $heritage->id_ancestry();
			$tmp = array();
			foreach ($ancestry as $level)
			{
				$tmp[] = $level->id;
			}
			$ancestry = $tmp;
			unset($tmp);
		}

		/*
		 * Get a listing of all globally scoped terms.
		 */
		if ( isset($this->scope) && ($this->scope == 'global') )
		{
			$sql = 'SELECT dictionary.term
					FROM dictionary
					LEFT JOIN laws
						ON dictionary.law_id=laws.id
					 WHERE scope="global"';
		}
		
		/*
		 * Otherwise, we're getting a listing of all more narrowly scoped terms. We always make sure
		 * that global definitions are included, in addition to the definitions for the current
		 * structural heritage.
		 */
		else
		{
			$sql = 'SELECT DISTINCT dictionary.term
					FROM dictionary
					LEFT JOIN laws
						ON dictionary.law_id=laws.id
					LEFT JOIN structure
						ON laws.structure_id=structure.id
					WHERE
					(
						(dictionary.law_id=' . $db->quote($this->section_id) . ')
						AND
						(dictionary.scope="section")
					)';
			foreach ($ancestry as $structure_id)
			{
				$sql .= ' OR (dictionary.structure_id=' . $db->quote($structure_id) . ')';
			}
			$sql .= ' OR (scope="global")';
		}

		$result = $db->query($sql);
		
		/*
		 * If the query fails, or if no results are found, return false -- we have no terms for this
		 * structural unit.
		 */
		if ( ($result === FALSE) || ($result->rowCount() < 1) )
		{
			return FALSE;
		}
		
		/*
		 * Build up the result as an object as we loop through the results.
		 */
		$i=0;
		while ($term = $result->fetch(PDO::FETCH_OBJ))
		{
			$terms->$i = $term->term;
			$i++;
		}

		/*
		 * Assemble a second query, this one against our generic legal dictionary.
		 */
		$sql = 'SELECT term
				FROM dictionary_general';

		$result = $db->query($sql);
		
		if ($result->rowCount() > 0)
		{		
			/*
			* Append these results to the existing $terms object, continuing to use the previously-
			* defined $i counter.
			*/
			while ($term = $result->fetch(PDO::FETCH_OBJ))
			{
				$terms->$i = $term->term;
				$i++;
			}
		}
		
		$tmp = (array) $terms;
		$tmp = array_unique($tmp);
		$terms = (object) $tmp;

		return $terms;
	}	
}
