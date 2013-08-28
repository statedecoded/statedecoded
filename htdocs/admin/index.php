<?php

/**
 * The administrative parser page
 *
 * PHP version 5
 *
 * @author		Waldo Jaquith <waldo at jaquith.org>
 * @copyright	2010-2013 Waldo Jaquith
 * @license		http://www.gnu.org/licenses/gpl.html GPL 3
 * @version		0.7
 * @link		http://www.statedecoded.com/
 * @since		0.1
 *
 */

/*
 * Include the code with the functions that drive this parser.
 */

/*
 * Log parser output.
 */
$logger = new Logger(array('html' => true));

/*
 * Create a new parser controller.
 */
$parser = new ParserController(array('logger' => $logger));

if (isset($_GET['noframe']))
{
	/*
	 * Begin the flush immediately, by sending the content type header.
	 */
	header( 'Content-type: text/html; charset=utf-8' );
}

/*
 * When first loading the page, show options.
 */
if (count($_POST) === 0)
{
	if (count($_GET) === 0)
	{
		$body = '<iframe id="content" src="?page=parse&noframe=1"></iframe>';
	}
	elseif ($_GET['page'] == 'parse' )
	{
		$body = '<p>What do you want to do?</p>
		<nav id="parse-options">
		<form method="post" action="/admin/?page=parse&noframe=1">
			<input type="hidden" name="action" value="parse" />
			<input type="submit" value="Parse" />
		</form>
		<form method="post" action="/admin/?page=parse&noframe=1">
			<input type="hidden" name="action" value="empty" />
			<input type="submit" value="Empty the DB" />
		</form>
		<form method="post" action="/admin/?page=parse&noframe=1">
			<input type="hidden" name="action" value="permalinks" />
			<input type="submit" value="Rebuild Permalinks" />
		</form>';

		/*
		 * If APC is running, provide an option to clear the cache.
		 */
		if (APC_RUNNING === TRUE)
		{
			$body .= '
				<form method="post" action="/admin/?page=parse&noframe=1">
					<input type="hidden" name="action" value="apc" />
					<input type="submit" value="Clear APC Cache" />
				</form>';
		}

		$body .= '</nav>';
	}
}

/*
 * If the request is to empty the database.
 */
elseif ($_POST['action'] == 'empty')
{

	echo 'Emptying the database.<br />';
	flush();
	$parser->clear_db();
	echo 'Done.<br />';

}

/*
 * Else if we're actually running the parser.
 */
elseif ($_POST['action'] == 'parse')
{

	echo 'Beginning parse.<br />';
	flush();
	ob_flush();
	/*
	 * Step through each parser method.
	 */
	if ($parser->test_environment() !== FALSE)
	{
		if ($parser->populate_db() !== FALSE)
		{
			$parser->clear_apc();
			$parser->populate_editions();
			/*
			 * We should only continue if parsing was successful.
			 */
			if ($parser->parse())
			{
				$parser->build_permalinks();
				$parser->write_api_key();
				$parser->export();
				$parser->generate_sitemap();
				$parser->structural_stats_generate();
				$parser->prune_views();
			}
		}
	}

	/*
	 * Attempt to purge Varnish's cache. (Fails silently if Varnish isn't installed or running.)
	 */
	$varnish = new Varnish;
	$varnish->purge();

	echo 'Done.<br />';

}

elseif ($_POST['action'] == 'permalinks')
{

	ob_start();

	echo 'Beginning permalinks.<br />';

	$parser->build_permalinks();

	echo 'Done.<br />';

	$body = ob_get_contents();
	ob_end_clean();

}

elseif ($_POST['action'] == 'apc')
{

	ob_start();

	echo 'Clearing APC cache.<br />';

	$parser->clear_apc();

	echo 'Done.<br />';

	$body = ob_get_contents();
	ob_end_clean();

}

/*
 * If this is an AJAX request
 */
if(isset($_GET['noframe']))
{
	echo $body;
}

else
{

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
	 * Put the shorthand $body variable into its proper place.
	 */
	$template->field->body = $body;
	unset($body);

	/*
	 * Parse the template, which is a shortcut for a few steps that culminate in sending the content
	 * to the browser.
	 */
	$template->parse();

}
