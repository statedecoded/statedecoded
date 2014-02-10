<?php

/**
 * The "Downloads" page, listing all of the bulk download files.
 *
 * PHP version 5
 *
 * @license		http://www.gnu.org/licenses/gpl.html GPL 3
 * @version		0.8
 * @link		http://www.statedecoded.com/
 * @since		0.8
 *
 */

/*
 * Create a container for our content.
 */
$content = new Content();

/*
 * Define some page elements.
 */
$content->set('browser_title', 'Downloads');
$content->set('page_title', 'Downloads');

/*
 * Provide some custom CSS for this form.
 */
$content->set('inline_css',
	'<style>
		#required-note {
			font-size: .85em;
			margin-top: 2em;
		}
		.required {
			color: #f00;
		}
		#api-registration label {
			display: block;
			margin-top: 1em;
		}
		#api-registration input[type=text] {
			width: 35em;
		}
		#api-registration input[type=submit] {
			display: block;
			clear: left;
			margin-top: 1em;
		}
	</style>');

$body = '
	<h2>Laws as JSON</h2>
	<p><a href="current/code.json.zip">code.json.zip</a><br />
	This is the basic data about every law, one JSON file per law. Fields include section, catch
	line, text, history, and structural ancestry (i.e., title number/name and chapter number/name).
	Note that any sections that contain colons (e.g., § 8.01-581.12:2) have an underscore in place
	of the colon in the filename, because neither Windows nor Mac OS support colons in filenames.</p>

	<h2>Laws as Plain Text</h2>
	<p><a href="current/code.txt.zip">code.txt.zip</a><br />
	This is the basic data about every law, one plain text file per law. Note that any sections that
	contain colons (e.g., § 8.01-581.12:2) have an underscore in place of the colon in the filename,
	because neither Windows nor Mac OS support colons in filenames.</p>

	<h2>Dictionary as JSON</h2>
	<p><a href="current/dictionary.json.zip">dictionary.json.zip</a><br />
	All terms defined in the laws, with each term’s definition, the section in which it is defined,
	and the scope (section, chapter, title, global) of that definition.</p>';

/*
 * Create an instance of the API class.
 */
$api = new API();

/*
 * If the form on this page is being submitted, process the submitted data.
 */
if (isset($_POST['form_data']))
{

	/*
	 * Pass the submitted form data to the API class, as an object rather than as an array.
	 */
	$api->form = (object) $_POST['form_data'];

	/*
	 * If this form hasn't been completed properly, display the errors and re-display the form.
	 */
	if ($api->validate_form() === FALSE)
	{
		$body = '<p class="error">Error: ' . $api->form_errors . '</p>';
		$body .= $api->display_form();
	}

	/*
	 * But if the form has been filled out correctly, then proceed with the registration process.
	 */
	else
	{

		/*
		 * Register this key.
		 */
		try
		{
			$api->register_key();
		}
		catch (Exception $e)
		{
			$body = '<p class="error">Error: ' . $e->getMessage() . '</p>';
		}

		$body .= '<p>You have been sent an e-mail to verify your e-mail address. Please click the
					link in that e-mail to activate your API key.</p>';
	}

}

/*
 * Activate the API key.
 */
elseif (isset($_GET['secret']))
{

	/*
	 * If this isn't a five-character string, bail -- something's not right.
	 */
	if (strlen($_GET['secret']) != 5)
	{
		$body = '<h2>Error</h2>

				<p>Invalid API key.</p>';
	}
	else
	{

		/*
		 * Import the variable into the class scope.
		 */
		$api->secret = filter_input(INPUT_GET, 'secret', FILTER_SANITIZE_SPECIAL_CHARS);

		/*
		 * Activate the key.
		 */
		$api->activate_key();

		$body = '<h2>API Key Activated</h2>

				<p>Your API key has been activated. You may now make requests from the API. Your key
				is:</p>

				<p><code>'.$api->key.'</code></p>';

	}
}

/* If this page is being loaded normally (that is, without submitting data), then display the
 * registration form.
 */
else
{
	$sidebar = '<h1>Register for the API</h1>

				<p>' . SITE_TITLE . ' has a rich application programming interface (API). To use it,
				simply register for a key and confirm your e-mail address. You can be using it
				within a minute or so. See the project’s
				<a href="http://statedecoded.github.io/documentation/api.html">API
				documentation</a> for details.</p>';

	$sidebar .= $api->display_form();

	$sidebar .= '<h1>Documentation</h1>

				<p>A great deal of information is available about how to use ' . SITE_TITLE .'’s
				API and downloads, plus much more, within
				<a href="http://statedecoded.github.io/documentation/">the documentation</a> for
				The State Decoded, the software that drives this website.</p>';
}

/*
 * Put the shorthand $body variable into its proper place.
 */
$content->set('body', $body);
unset($body);

/*
 * Put the shorthand $sidebar variable into its proper place.
 */
$content->set('sidebar', $sidebar);
unset($sidebar);

/*
 * Add the custom classes to the body.
 */
$content->set('body_class', 'law inside');

/*
 * Parse the template, which is a shortcut for a few steps that culminate in sending the content
 * to the browser.
 */
$template = Template::create();
$template->parse($content);
