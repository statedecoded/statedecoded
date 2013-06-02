<?php

/**
 * The administrative parser page
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
 * During this import phase, report all errors.
 */
//ini_set('display_errors', 1);
//error_reporting(E_ALL);

/*
 * Include the PHP declarations that drive this page.
 */
require dirname(dirname(dirname(__FILE__))).'/includes/page-head.inc.php';

/*
 * Include the code with the functions that drive this parser.
 */
require_once INCLUDE_PATH . '/parser-controller.inc.php';
require_once INCLUDE_PATH . '/logger.inc.php';

/*
 * Log parser output.
 */
$logger = new Logger(array('html' => true));

/*
 * Create a new parser controller.
 */
$parser = new ParserController(array('logger' => $logger));

/*
 * Fire up our templating engine.
 */
$template = new Page;

/*
 * Define some page elements.
 */
$template->field->browser_title = 'Parser';
$template->field->page_title = 'Parser';

/*
 * When first loading the page, show options.
 */
if (count($_POST) === 0)
{
	$body = '
		<p>What do you want to do?</p>
		<form method="post" action="/admin/parser.php">
			<input type="hidden" name="action" value="parse" />
			<input type="submit" value="Parse" />
		</form>
		<form method="post" action="/admin/parser.php">
			<input type="hidden" name="action" value="empty" />
			<input type="submit" value="Empty the DB" />
		</form>';
}

/*
 * If the request is to empty the database.
 */
elseif ($_POST['action'] == 'empty')
{

	ob_start();
	
	$parser->clear_db();
	
	$body = ob_get_contents();
	ob_end_clean();
	
}

/*
 * Else if we're actually running the parser.
 */
elseif ($_POST['action'] == 'parse')
{

	ob_start();
	
	/*
	 * Step through each parser method.
	 */
	$parser->parse();
	$parser->write_api_key();
	$parser->export();
	$parser->clear_apc();
	$parser->prune_views();
	
	/*
	 * Attempt to purge Varnish's cache. (Fails silently if Varnish isn't installed or running.)
	 */
	$varnish = new Varnish;
	$varnish->purge();
	
	$body = ob_get_contents();
	ob_end_clean();
}


/*
 * Put the shorthand $body variable into its proper place.
 */
$template->field->body = $body;
unset($body);

/*
 * Parse the template, which is a shortcut for a few steps that culminate in sending the content
 * to the browser.
 */
$template->parse();
