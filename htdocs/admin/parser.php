<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
                      "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
</head>
<body>

<?php

/*
 * During this import phase, report all errors.
 */
//ini_set('display_errors', 1);
//error_reporting(E_ALL);

/*
 * Include a master settings include file.
 */
require_once dirname(dirname(dirname(__FILE__))) . '/includes/config.inc.php';

/*
 * Include MDB2
 */
require_once 'MDB2.php';

/*
 * Include the code with the functions that drive this parser.
 */
require_once CUSTOM_FUNCTIONS;

require_once INCLUDE_PATH . '/parser-controller.inc.php';
require_once INCLUDE_PATH . '/logger.inc.php';

$logger = new DebugLogger(array('html' => true));

$parser = new ParserController(array('logger' => $logger));


/*
 * When first loading the page, show options.
 */
if (count($_POST) == 0)
{
	echo '
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
	$parser->clear_db();

}

elseif ($_POST['action'] == 'parse') {
	$parser->parse();

	$parser->write_api_key();

	$parser->export();

	$parser->clear_apc();
}


?>
</body>
</html>