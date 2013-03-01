<?php

/**
 * Help text
 *
 * All of the help text that drives the pop-up explanations throughout the website.
 * 
 * PHP version 5
 *
 * @author		Waldo Jaquith <waldo at jaquith.org>
 * @copyright	2013 Waldo Jaquith
 * @license		http://www.gnu.org/licenses/gpl.html GPL 3
 * @version		0.7
 * @link		http://www.statedecoded.com/
 * @since		0.7
 *
 */

/*
 * Store all help text in $help.
 */
$help = new stdClass();


/*
 * Define the containers that will hold each category of help text.
 */
$help->sitewide = new stdClass();
$help->law = new stdClass();
$help->structure = new stdClass();
$help->search = new stdClass();

/*
 * Sitewide help text.
 */
$help->sitewide->test = 'This is a test of help text';
 
 
/*
 * Law-specific help text.
 */
$help->law->test = 'This is a test of help text';


/*
 * Structure-specific help text.
 */
$help->structure->test = 'This is a test of help text';


/*
 * Search-specific help text.
 */
 $help->search->test = 'This is a test of help text';
 