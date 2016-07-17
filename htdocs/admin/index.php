<?php

/**
 * The administrative interface
 *
 * PHP version 5
 *
 * @author		Waldo Jaquith <waldo at jaquith.org>
 * @copyright	2010-2013 Waldo Jaquith
 * @license		http://www.gnu.org/licenses/gpl.html GPL 3
 * @version		0.9
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
$logger_args = array('html' => TRUE);
if(defined('DEBUG_LEVEL'))
{
	$logger_args['level'] = DEBUG_LEVEL;
}
$logger = new Logger($logger_args);

/*
 * Require that the user log in.
 */
if (!isset($_SERVER['PHP_AUTH_USER']) || !isset($_SERVER['PHP_AUTH_PW']) ||
    $_SERVER['PHP_AUTH_USER'] != ADMIN_USERNAME || $_SERVER['PHP_AUTH_PW'] != ADMIN_PASSWORD)
{

    Header('WWW-Authenticate: Basic realm="The State Decoded Admin"');
    Header('HTTP/1.0 401 Unauthorized');

    echo '<html><body>
        <h1>Rejected</h1>
        <big>Wrong Username or Password</big>
        </body></html>';
    exit;

}

/*
 * Create a new parser controller.
 */
global $db;
$parser = new ParserController(
	array(
		'logger' => $logger,
		'db' => &$db,
		'import_data_dir' => IMPORT_DATA_DIR
	)
);

if (isset($_GET['noframe']))
{

	/*
	 * Begin the flush immediately, by sending the content type header.
	 */
	header( 'Content-type: text/html; charset=utf-8' );

	/*
	 * Then we get the template (which contains just the head)
	 * much earlier than usual.
	 */
	$content = new Content;

	$template = Template::create('admin_frame');
	$template->parse($content);

}

/*
 * When first loading the page, show options.
 */
if (count($_POST) === 0)
{

	if (count($_GET) === 0)
	{
		$body = '<iframe id="content" src="?page=parse&amp;noframe=1"></iframe>';
	}
	elseif ($_GET['page'] == 'parse' )
	{
		$parser->logger->flush_buffer = FALSE;

		if(!$parser->check_db_populated())
		{
			ob_start();
			if(!$parser->test_environment())
			{
				$errors = ob_get_contents();

				$body = '<p>
					It looks like this is a new installation of The State
					Decoded. Unfortunately, there are a few issues you need
					to resolve before we can do the rest of the setup.  Those
					errors are listed below.
					</p>
					<p class="errors">' . $errors . '</p>';
			}
			else
			{
				$body = '
				<form method="post" action="/admin/?page=setup&noframe=1">
					<h3>Setup</h3>
					<p>
						It looks like this is a new installation of The State
						Decoded. To get started, you\'ll need to click the
						button below to setup the database.  If this is not a
						new installation, you should <a
						href="http://docs.statedecoded.com/config.html">check
						that your database is configured properly</a> and
						everything is working as it should.
					</p>
					<p>
						<input type="hidden" name="action" value="setup" />
						<input type="submit" name="submit"
							value="Setup the database"/>
					</p>
				</form>';
			}
			ob_end_clean();
		}
		elseif($parser->check_migrations())
		{
			$body = '
				<form method="post" action="/admin/?page=setup&noframe=1">
					<h3>Update</h3>
					<p>
						The State Decoded has been updated but the database
						needs to be upgraded. Please click the button below
						to update the database.
					</p>
					<p>
						<input type="hidden" name="action" value="update_db" />
						<input type="submit" name="submit"
							value="Update the database"/>
					</p>
				</form>';
		}
		else
		{
			$args['parser'] = $parser;
			$body = show_admin_forms($args);
		}
	}
}

/*
 * If we're doing the initial setup.
 */
elseif ($_POST['action'] == 'setup')
{
		/*
	 * Step through each parser method.
	 */
	if ($parser->test_environment() !== FALSE)
	{
		$body = '<p>Environment test succeeded</p>';

		if ($parser->populate_db() !== FALSE)
		{
			$body .= '<p>Database successfully setup.</p>';

			$body .= $parser->run_migrations();
			$body .= '<p>Migration complete.</p>';

			$body .= '<p><a href="/admin/?page=parse&amp;noframe=1">Back to Admin Dashboard</a></p>';
		}
		else
		{
			echo '<p>There was an error setting up the database.  Please review
				<a href="http://docs.statedecoded.com/">the documentation</a>
				 to confirm that you have everything
				 <a href="http://docs.statedecoded.com/installation.html">installed</a>
				 and
				 <a href="http://docs.statedecoded.com/config.html">configured</a>
				 properly.</p>';
		}
	}
	else
	{
			echo '<p>There was an error setting up the database.  The issues
				encountered should appear above. Please review
				<a href="http://docs.statedecoded.com/">the documentation</a>
				 to confirm that you have everything
				 <a href="http://docs.statedecoded.com/installation.html">installed</a>
				 and
				 <a href="http://docs.statedecoded.com/config.html">configured</a>
				 properly.</p>';
	}
}

elseif ($_POST['action'] == 'update_db')
{
	$body .= $parser->run_migrations();
	$body .= '<p>Migration complete.</p>';
	$body .= '<p><a href="/admin/?page=parse&amp;noframe=1">Back to Admin Dashboard</a></p>';
}

/*
 * If the request is to empty the database.
 */
elseif ($_POST['action'] == 'empty')
{

	echo 'Emptying the database<br />';
	flush();

	if($_POST['edition'])
	{
		$parser->clear_edition($_POST['edition']);
	}
	else
	{
		$parser->clear_db();
	}

	echo 'Emptying the index<br />';
	flush();
	$parser->clear_index();

	echo 'Done<br />';

}

/*
 * Else if we're actually running the parser.
 */
elseif ($_POST['action'] == 'parse')
{
	define('EXPORT_IN_PROGRESS', true);

	echo 'Beginning import<br />';
	flush();
	ob_flush();

	/*
	 * Step through each parser method.
	 */
	if ($parser->test_environment() !== FALSE)
	{

		echo 'Environment test succeeded<br />';

		if ($parser->populate_db() !== FALSE)
		{

			$edition_errors = $parser->handle_editions($_POST);

			if (count($edition_errors) > 0)
			{
				$args = $_POST;
				$args['import_errors'] = $edition_errors;
				$args['parser'] = $parser;

				echo show_admin_forms($args);
			}

			else
			{


				$parser->clear_cache();
				$parser->clear_edition($_POST['edition']);

				/*
				 * We should only continue if parsing was successful.
				 */
				if ($parser->parse())
				{

					$parser->build_permalinks();
					$parser->write_api_key();
					$parser->export();
					$parser->generate_sitemap();
					$parser->index_laws();
					$parser->structural_stats_generate();
					$parser->prune_views();
					$parser->finish_import();
				}

			}

		}

		else
		{
			$this->logger->message('The database isn\'t populated.  Please run the setup function.', 10);
		}
	}

	/*
	 * Attempt to purge Varnish's cache. (Fails silently if Varnish isn't installed or running.)
	 */
	$varnish = new Varnish;
	$varnish->purge();

	echo 'Done<br />';

	echo '<br /><a href="/admin/?page=parse&amp;noframe=1">Back</a>';

}

elseif ($_POST['action'] == 'permalinks')
{

	ob_start();

	echo 'Beginning permalinks<br />';

	$parser->build_permalinks();

	echo 'Done<br />';

	echo '<br /><a href="/admin/?page=parse&amp;noframe=1">Back</a>';

	$body = ob_get_contents();
	ob_end_clean();

}

elseif ($_POST['action'] == 'cache')
{

	ob_start();

	echo 'Clearing in-memory cache<br />';

	$parser->clear_cache();

	echo 'Done<br />';

	echo '<br /><a href="/admin/?page=parse&amp;noframe=1">Back</a>';

	$body = ob_get_contents();
	ob_end_clean();

}

elseif ($_POST['action'] == 'test_environment')
{

	ob_start();

	echo 'Testing environment<br />';

	$result = $parser->test_environment();

	echo 'Done. ';

	if ($result === TRUE)
	{
		echo 'No errors detected.<br/>';
	}
	else
	{
		echo 'Errors encountered.<br />';
	}

	echo '<br /><a href="/admin/?page=parse&amp;noframe=1">Back</a>';

	$body = ob_get_contents();
	ob_end_clean();

}

/*
 * If this is an AJAX request
 */
if (isset($_GET['noframe']))
{
	echo $body;
}

else
{

	/*
	 * Create a container for our content.
	 */
	$content = new Content;

	/*
	 * Define some page elements.
	 */
	$content->set('browser_title', 'Admin');

	$content->set('body_class', 'inside');

	/*
	 * Put the shorthand $body variable into its proper place.
	 */

	$content->set('body', '<div class="nest narrow">');
	$content->append('body', '<h1>Admin</h1>');
	$content->append('body', $body);
	$content->append('body', '</div>');
	unset($body);


	/*
	 * Parse the template, which is a shortcut for a few steps that culminate in sending the content
	 * to the browser.
	 */
	$template = Template::create('admin');
	$template->parse($content);

}

function show_admin_forms($args = array())
{

	global $db;
	$edition = new Edition(array('db' => $db));

	try {
		$editions = $edition->all();
	}
	catch(Exception $exception) {
		$editions = FALSE;
	}

	if ( (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['SERVER_PORT'] == 443) )
	{
		$base_url = 'https://';
	}
	else
	{
		$edition_url_base = 'http://';
	}
	$edition_url_base .= $_SERVER['SERVER_NAME'];
	if ($_SERVER['SERVER_PORT'] != 80)
	{
		$edition_url_base .= ':' . $_SERVER['SERVER_PORT'];
	}
	$edition_url_base .= '/';

	$body = '<p>What do you want to do?</p>

	<form method="post" action="/admin/?page=parse&noframe=1">
		<h3>Import Data</h3>';
	if (isset($args['import_errors']))
	{
		$body .= '<div class="errors">
			Please fix the following errors:
			<ul>';
		foreach($args['import_errors'] as $error)
		{
			$body .= '<li>' . $error . '</li>';
		}
		$body .= '</div>';
	}

	$body .= '<p>This will import all data files from your data directory:
		<span class="code">' . IMPORT_DATA_DIR . '</span></p>
		<input type="hidden" name="action" value="parse" />
			<div class="option">
				<input type="radio" class="radio" name="edition_option"
					id="edition_option_new" value="new"';
	if (!$editions || $args['edition_option'] == 'existing')
	{
		$body .= 'checked="checked"';
	}

	$body .='/>
				<label for="edition_option_new">I want to create a new edition of the laws.</label>

				<div class="suboption">
					<label for="new_edition_name">I want to call this edition</label>
					<div>
						<input type="text" class="text" name="new_edition_name"
							id="new_edition_name" placeholder="' . date('Y-m') . '"
							value="' . ( isset($args['new_edition_name']) ? $args['new_edition_name'] : '' ) . '"
							/>
					</div>
				</div>
				<div class="suboption">
					<label for="new_edition_slug">.&thinsp;.&thinsp;.</option> and the URL for this edition will be</label>
					<div class="edition_url">' . $edition_url_base . '
						<input type="text" class="text" name="new_edition_slug"
							id="new_edition_slug" placeholder="' . date('Y-m') . '"
							value="' . ( isset($args['new_edition_slug']) ? $args['new_edition_slug'] : '' ) . '"
							/> /
					</div>
					<p class="note">Note: In general, try to only use letters, numbers, and hyphens,
					and avoid using anything that might conflict with a section number or structure
					name.</p>
				</div>
			</div>
			<div class="option">
				<input type="radio" class="radio" name="edition_option"
					id="edition_option_existing" value="existing"';

	if ( !isset($editions) || ($editions === FALSE) )
	{
		$body .= 'disabled="disabled"';
	}
	elseif ($args['edition_option'] == 'existing')
	{
		$body .= 'checked="checked"';
	}

	$body .= '/><label for="edition_option_existing">I want to update an existing edition of the laws.</label>';

	if ($editions !== FALSE)
	{

		$body .= '<div class="suboption">
					<p>This will remove all existing data in this edition.</p>
					<select name="edition" value="edition">
						<option value="">Choose Edition .&thinsp;.&thinsp;.</option>';
		foreach($editions as $edition)
		{
			$body .= '<option value="' . $edition->id . '"';
			if ($args['edition'] == $edition->id)
			{
				$body .= ' select="selected"';
			}
			$body .= '>' . $edition->name;
			if($edition->current === '1')
			{
				$body .= ' [current]';
			}
			$body .= '</option>';
		}

		$body .= '</select>
			</div>';

	}
	else
	{
		$body .= '<div class="suboption">
			You don’t have any editions yet, you’ll need to create a new one.
		</div>';
	}

	$body .= '</div>
		<div class="option">
			<input type="checkbox" class="checkbox" name="make_current"
				id="make_current" value="1"';
	if (!$editions)
	{
		$body .= 'checked="checked"';
	}
	$body .= ' />
			<label for="make_current">Make this edition current.</label>
		</div>
		<input type="submit" value="Import" />
	</form>

	<form method="post" action="/admin/?page=parse&noframe=1">
		<h3>Empty the Database</h3>
		<p>
			Remove data from the database. This leaves database tables intact.
			You may choose all data, or just data from a specific edition below.
		</p>';


	if ($editions !== FALSE)
	{

		$body .= '<div class="suboption">
					<select name="edition" value="edition">
						<option value="">All Data</option>';
		foreach($editions as $edition)
		{
			$body .= '<option value="' . $edition->id . '"';
			if ($args['edition'] == $edition->id)
			{
				$body .= ' select="selected"';
			}
			$body .= '>' . $edition->name;
			if($edition->current === '1')
			{
				$body .= ' [current]';
			}
			$boduy .= '</option>';
		}

		$body .= '</select>
			</div>';

	}

	$body .= '<input type="hidden" name="action" value="empty" />
		<input type="submit" value="Empty the Database" />
	</form>

	<form method="post" action="/admin/?page=parse&noframe=1">
		<h3>Rebuild Permalinks</h3>
		<p>Completely rebuild all permalinks for all editions of code.</p>

		<input type="hidden" name="action" value="permalinks" />
		<input type="submit" value="Rebuild Permalinks" />
	</form>

	<form method="post" action="/admin/?page=parse&noframe=1">
		<h3>Test Your Environment</h3>
		<p>Verifies that the State Decoded will run on the current server
			environment.</p>

		<input type="hidden" name="action" value="test_environment" />
		<input type="submit" value="Test Environment" />
	</form>';

	/*
	 * If Memcached / Redis is in use, provide an option to clear the cache.
	 */
	global $cache;
	if (isset($cache))
	{

		$body .= '
			<form method="post" action="/admin/?page=parse&noframe=1">
				<h3>Clear the In-Memory Cache</h3>
				<p>Delete all data currently stored in Memcached or Redis.</p>
				<input type="hidden" name="action" value="cache" />
				<input type="submit" value="Clear Cache" />
			</form>';

	}

	return $body;

}
