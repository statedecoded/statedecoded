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
require $_SERVER['DOCUMENT_ROOT'].'/../includes/page-head.inc.php';

/*
 * Include the PHP declarations that drive this page.
 */
require_once dirname(dirname(dirname(__FILE__))) . '/includes/config.inc.php';

/*
 * Fire up our templating engine.
 */
$template = new Page;

/*
 * Include the code with the functions that drive this parser.
 */
require_once CUSTOM_FUNCTIONS;

require_once INCLUDE_PATH . '/parser-controller.inc.php';
require_once INCLUDE_PATH . '/logger.inc.php';

//$logger = new DebugLogger(array('html' => true));
$logger = new Logger(array('html' => true));

$parser = new ParserController(array('logger' => $logger));

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

elseif ($_POST['action'] == 'parse') {
	ob_start();

	$parser->parse();

	$parser->write_api_key();

	$parser->export();

	$parser->clear_apc();
	
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
