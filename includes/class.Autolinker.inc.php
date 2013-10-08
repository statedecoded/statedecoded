<?php

/**
 * The Autolinker class, for identifying linkable text and turn it into links
 *
 * PHP version 5
 *
 * @author		Waldo Jaquith <waldo at jaquith.org>
 * @copyright	2013 Waldo Jaquith
 * @license		http://www.gnu.org/licenses/gpl.html GPL 3
 * @version		0.7
 * @link		http://www.statedecoded.com/
 * @since		0.2
 *
 */

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
