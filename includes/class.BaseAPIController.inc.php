<?php

/**
 * Base API Controller Class
 *
 * PHP version 5
 *
 * @author		Bill Hunt <bill at krues8dr dot com>
 * @copyright	2013 Bill Hunt
 * @license		http://www.gnu.org/licenses/gpl.html GPL 3
 * @version		0.9
 * @link		http://www.statedecoded.com/
 * @since		0.9
 *
 */

class BaseAPIController extends BaseController
{

	/**
	 * Validate the provided API key.
	 */
	public function checkAuth()
	{
		$api = new API;
		$api->key = $_GET['key'];
		try
		{
			$api->validate_key();
		}
		catch (Exception $e)
		{
			json_error($e->getMessage());
			die();
		}
	}

	/**
	 * Override the default 404
	 */
	public function handleNotFound($callback = null)
	{
		$response = new StdClass();
		$response->message = 'An error occurred';
		$response->details = 'Could not find the requested resource';

		return $this->render($response, 'NOT FOUND', $callback);
	}

	/**
	 * Check if the provided JSONP callback is safe
	 */
	public function checkCallback()
	{
		if (isset($_REQUEST['callback']))
		{
			# If this callback contains any reserved terms that raise XSS concerns, refuse to proceed.
			return valid_jsonp_callback($_REQUEST['callback']);
		}
		return true;
	}

	/**
	 * Error for bad JSONP callback
	 */
	public function handleBadCallback()
	{
		$response = new StdClass();
		$response->message = 'An error occurred';
		$response->details = 'The provided JSONP callback uses a reserved word.';

		return $this->render($response, 'BAD REQUEST');
	}

	/**
	 * Render the content.
	 */
	public function render($response, $status)
	{
		$this->sendJSONHeaders($status);

		$this->setApiVersion($response);

		if(isset($_REQUEST['callback']))
		{
			$callback = filter_var($_REQUEST['callback'], FILTER_SANITIZE_STRING);
		}

		/*
		 * Optionally wrap our response in a callback, and flush all data to the client.
		 */
		if (isset($callback))
		{
			echo $callback.' (';
		}

		echo json_encode($response);

		if (isset($callback))
		{
			echo ');';
		}
	}

	/**
	 * Send proper headers for the content type
	 */
	public function sendJSONHeaders($status = 'OK')
	{
		switch ($status)
		{
			case 'BAD REQUEST':
				header('HTTP/1.0 400 BAD REQUEST');
				break;

			case 'NOT FOUND':
				header('HTTP/1.0 404 NOT FOUND');
				break;

			case 'OK':
			default:
				header("HTTP/1.0 200 OK");
		}

		header('Content-type: application/json');
	}

	/**
	 * Set the API version in the response
	 */
	public function setApiVersion(&$response)
	{
		/*
		 * Include the API version in this response.
		 */
		if (isset($args['api_version']) && strlen($_REQUEST['api_version']))
		{
			$response->api_version = filter_var($_REQUEST['api_version'], FILTER_SANITIZE_STRING);
		}
		else {
			$response->api_version = CURRENT_API_VERSION;
		}
	}
}
