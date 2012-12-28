<?php

/**
 * The core function library for The State Decoded.
 *
 * PHP version 5
 *
 * @author		Waldo Jaquith <waldo at jaquith.org>
 * @copyright	2010-2012 Waldo Jaquith
 * @license		http://www.gnu.org/licenses/gpl.html GPL 3
 * @version		0.5
 * @link		http://www.statedecoded.com/
 * @since		0.1
*/

/**
 * Autoload any class file when it is called.
 */
function __autoload($class_name)
{
	include('class.'.$class_name.'.inc.php');
}

/**
 * Get the contents of a given URL. A wrapper for cURL.
 */
function fetch_url($url)
{
	if (!isset($url))
	{
		return false;
	}
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
	curl_setopt($ch, CURLOPT_TIMEOUT_MS, 1200);
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
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
			return false;
		}
		
		// If the provided term is an array of terms, just use the first one. This might seem odd,
		// but note that this function is written to be used within preg_replace_callback(), the
		// PCRE provides an array-based word listing, and we only want the first one.
		if (is_array($term))
		{
			$term = $term[0];
		}
		
		// If we have already marked this term as blacklisted -- that is, as a word that is a subset
		// of a longer term -- then just return the term without marking it as a dictionary term.
		if (in_array(strtolower($term), $this->term_blacklist))
		{
			return $term;
		}
	
		// Determine whether this term is made up of multiple words, so that we can eliminate any
		// terms from our arrays of terms that are any of the individual words that make up this
		// term. That is, if this term is "person or people," and "person" is another term in our
		// array, then we want to drop "person," to avoid display overlapping terms.
		$num_spaces = substr_count($term, ' ');
		if ($num_spaces > 0)
		{

			// Use that separator to break the term up into an array of words.
			$term_components = explode(' ', $term);
			
			// Step through each the the words that make up this phrase, and add each of them to
			// the blacklist, so that we can skip this word next time it appears in this law.
			foreach ($term_components as $word)
			{
				$this->term_blacklist[] = strtolower($word);
			}
			
			// Now step through each two-word sub-phrase that make up this 3+-word phrase (assuming
			//that there are any) and add each of them to the blacklist.
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
	
		// PCRE provides an array-based match listing. We only want the first one.
		$match = $matches[0];
		
		// If the section symbol prefixes this match, hack it off.
		if (substr($match, 0, strlen(SECTION_SYMBOL)) == SECTION_SYMBOL)
		{
			$match = substr($match, (strlen(SECTION_SYMBOL.' ')));
		}
	
		// Create an instance of the Law class.
		$law = new Law;
		
		// Just find out if this law exists.
		$law->section_number = $match;
		$section = $law->exists();
		
		// If this isn't a valid section number, then just return the match verbatim -- there's no
		// link to be provided.
		if ($section === false)
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
		return false;
	}
	$error = array('error',
		array(
			'message' => 'An Error Occurred',
			'details' => $text
		)
	);
	$error = json_encode($error);
	
	// Return a 400 "Bad Request" error. This indicates that the request was invalid. Whether this
	// is the best HTTP header is unclear.
	header("HTTP/1.0 400 OK");
	// Send an HTTP header defining the content as JSON.
	header('Content-type: application/json');
	echo $error;
}

/**
 * Throw a 404.
 */
function send_404()
{
	include('/var/www/vacode.org/htdocs/404.php');
	exit();
}

/**
 * This is relied on by usort() in law.php.
 */
function sort_by_length($a, $b)
{
	return strlen($b) - strlen($a);
}

/**
 * 
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
		// Save the contents of the template file to a variable.
		$this->html = file_get_contents(INCLUDE_PATH.'/templates/'.TEMPLATE.'.inc.php');
		
		// Create the browser title.
		if (!isset($field->browser_title))
		{
			if (isset($field->page_title))
			{
				$field->browser_title .= $field->page_title;
				$field->browser_title .= '—'.SITE_TITLE;
			}
			else
			{
				$field->browser_title .= SITE_TITLE;
			}
		}
		else
		{
			$field->browser_title .= '—'.SITE_TITLE;
		}
		
		// Replace all of our in-page tokens with our defined variables.
		foreach ($this->field as $field=>$contents)
		{
			$this->html = str_replace('{{'.$field.'}}', $contents, $this->html);
		}
		
		// Erase any unpopulated tokens that remain in our template.
		$this->html = preg_replace('/{{[0-9a-z_]+}}/', '', $this->html);
		
		// Erase selected containers, if they're empty.
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
			return false;
		}
		
		echo $this->html;
		return true;
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

		// We're going to need access to the database connection throughout this class.
		global $db;
		
		// If no term has been defined, there is nothing to be done.
		if (!isset($this->term))
		{
			return false;
		}
		
		// Determine the structural heritage of the provided section number and store it in an
		// array.
		$heritage = new Law;
		$heritage->config->get_structure = TRUE;
		$heritage->section_number = $this->section_number;
		$law = $heritage->get_law();
		$ancestry = array();
		foreach ($law->ancestry as $tmp)
		{
			$ancestry[] = $tmp->id;
		}
		
		// We want to check if the term is in all caps. If it is, then we want to keep it in all
		// caps to query the database. Otherwise, we lowercase it. That is, "Board" should be looked
		// up as "board," but "NAIC" should be looked up as "NAIC."
		for ($i=0; $i<strlen($this->term); $i++)
		{
			// If there are any uppercase characters, then make this PCRE string case sensitive.
			if ( (ord($this->term{$i}) >= 97) && (ord($this->term{$i}) <= 122) )
			{
				$lowercase = true;
				break;
			}
		}
		
		if ($lowercase === true)
		{
			$this->term = strtolower($this->term);
		}
		
		// If the last character in this word is an "s," then it might be a plural, in which case we
		// need to search for this and without its plural version.
		if (substr($this->term, -1) == 's')
		{
			$plural = true;
		}

		$sql = 'SELECT dictionary.term, dictionary.definition, dictionary.scope,
				laws.section AS section_number
				FROM dictionary
				LEFT JOIN laws
					ON dictionary.law_id=laws.id
				WHERE (dictionary.term="'.$db->escape($this->term).'"';
		if ($plural === true)
		{
			$sql .= ' OR dictionary.term = "'.$db->escape(substr($this->term, 0, -1)).'"';
		}
		$sql .= ') AND (';
		foreach ($ancestry as $structure_id)
		{
			$sql .= '(dictionary.structure_id = '.$db->escape($structure_id).') OR';
		}
		$sql .= '	(dictionary.scope = "global")
				OR
					(laws.section = "'.$db->escape($this->section_number).'")
				)
				
				ORDER BY dictionary.scope_specificity
				LIMIT 1';

		// Execute the query.
		$result =& $db->query($sql);

		// If the query succeeds, great, retrieve it.
		if ( (PEAR::isError($result) === false) && ($result->numRows() > 0) )
		{
		
			// Get the first result.
			$dictionary = $result->fetchRow(MDB2_FETCHMODE_OBJECT);
			
			$dictionary->url = 'http://'.$_SERVER['SERVER_NAME'].'/'.$dictionary->section_number.'/';
			$dictionary->formatted = wptexturize($dictionary->definition).' (<a href="'.$dictionary->url.'">'
				.$dictionary->section_number.'</a>)';
		}
		
		// Else if the query fails, then the term is found in the generic terms dictionary.
		else
		{
		
			// Assemble the SQL.		
			$sql = 'SELECT term, definition, source, source_url AS url
					FROM dictionary_general
					WHERE term="'.$db->escape($this->term).'"';
			if ($plural === true)
			{
				$sql .= ' OR term = "'.$db->escape(substr($this->term, 0, -1)).'"';
			}
			$sql .= ' LIMIT 1';

			// Execute the query.
			$result =& $db->query($sql);
			
			// If the query fails, or if no results are found, return false -- we have no terms for
			// this chapter.
			if ( (PEAR::isError($result) === true) || ($result->numRows() === 0) )
			{
				return false;
			}
		
			// Get the first result. Assemble a slightly different response than for a custom term.
			$dictionary = $result->fetchRow(MDB2_FETCHMODE_OBJECT);
			$dictionary->formatted = wptexturize($dictionary->definition).' (<a href="'.$dictionary->url.'">'
				.$dictionary->source.'</a>)';
			
		}
		
		// Return the result.
		return $dictionary;
		
	}
		
	/**
	 * Get a list of defined terms for a given chapter of the code, returning just a listing of
	 * terms. (The idea is that we can use an Ajax call to get each definition on demand.)
	 */
	function term_list()
	{

		// We're going to need access to the database connection throughout this class.
		global $db;
		
		// If a chapter ID hasn't been passed to this function, then return a listing of terms
		// that apply to the entirety of the code.
		if (!isset($this->structure_id) && !isset($this->scope))
		{
			$this->scope = 'global';
		}
		
		// Get a listing of all structural units that contain the current structural unit -- that is,
		// if this is a chapter, get the ID of both the chapter and the title. And so on.
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

		// Get a listing of all globally scoped terms.
		if ($this->scope == 'global')
		{
			$sql = 'SELECT dictionary.term
					FROM dictionary
					LEFT JOIN laws
						ON dictionary.law_id=laws.id
					 WHERE scope="global"';
		}
		
		// Otherwise, we're getting a listing of all more narrowly scoped terms. We always make sure
		// that global definitions are included, in addition to the definitions for the current
		// structural heritage.
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
						(dictionary.law_id='.$db->escape($this->section_id).')
						AND
						(dictionary.scope="section")
					)';
			foreach ($ancestry as $structure_id)
			{
				$sql .= ' OR (dictionary.structure_id='.$db->escape($structure_id).')';
			}
			$sql .= ' OR (scope="global")';
		}

		// Execute the query.
		$result =& $db->query($sql);
		
		// If the query fails, or if no results are found, return false -- we have no terms for this
		// chapter.
		if ( PEAR::isError($result) || ($result->numRows() < 1) )
		{
			return false;
		}
		
		// Build up the result as an object as we loop through the results.
		$i=0;
		while ($term = $result->fetchRow(MDB2_FETCHMODE_OBJECT))
		{
			$terms->$i = $term->term;
			$i++;
		}

		// Assemble a second query, this one against our generic legal dictionary.
		$sql = 'SELECT term
				FROM dictionary_general';

		// Execute the query.
		$result =& $db->query($sql);
		
		// If the query fails, or if no results are found, return false -- we have no terms for this
		// chapter.
		if ($result->numRows() >= 1)
		{		
			// Append these results to the existing $terms object, continuing to use the previously-
			// defined $i counter.
			while ($term = $result->fetchRow(MDB2_FETCHMODE_OBJECT))
			{
				$terms->$i = $term->term;
				$i++;
			}
		}
		
		$tmp = (array) $terms;
		$tmp = array_unique($tmp);
		$terms = (object) $tmp;

		// Return the result.
		return $terms;
	}	
}

?>