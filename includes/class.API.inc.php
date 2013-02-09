<?php

/**
 * The API class, for all interactions with APIs
 * 
 * PHP version 5
 *
 * @author		Waldo Jaquith <waldo at jaquith.org>
 * @copyright	2010-2012 Waldo Jaquith
 * @license		http://www.gnu.org/licenses/gpl.html GPL 3
 * @version		0.6
 * @link		http://www.statedecoded.com/
 * @since		0.6
 *
 */

/**
 * Functions for the API.
 */
class API
{
	
	/**
	 * Generate a listing of every registered API key.
	 */
	function list_all_keys()
	{
		/*
		 * We're going to need access to the database connection within this method.
		 */
		global $db;
		
		/* Only retrieve those keys that have been verified -- that is, for people who have been
		 * sent an e-mail with a unique URL, and that have clicked on that link to confirm their
		 * e-mail address.
		 */
		$sql = 'SELECT api_key
				FROM api_keys
				WHERE verified="y"';
		$result = $db->query($sql);
		
		/* If the database has returned an error. */
		if (PEAR::isError($result) === true)
		{
			throw new Exception('API keys could not be retrieved.');
		}
		
		/*
		 * If the query succeeds then retrieve each row and build up an object containing a list
		 * of all keys.
		 */
		else
		{
			
			/*
			 * If no API keys have been registered.
			 */
			if ($result->numRows() == 0)
			{
				return true;
			}
			
			/*
			 * If API keys have been registered, iterate through them and store them.
			 */
			$i=0;
			while ($key = $result->fetchRow(MDB2_FETCHMODE_OBJECT))
			{
				$this->all_keys->{$key->api_key} = true;
				$i++;
			}
			
			return true;
		}
	}
	

	/**
	 * Get all available information about a given API key.
	 */
	function get_key()
	{
		/*
		 * We're going to need access to the database connection within this method.
		 */
		global $db;
		
		/*
		 * Select all necessary fields from the api_keys table.
		 */
		$sql = 'SELECT id, api_key, email, name, url, verified, secret, date_created
				FROM api_keys
				WHERE key = "'.$db->escape($this->key).'"';
		$result = $db->query($sql);
		
		/*
		 * If the query succeeds then retrieve the result.
		 */
		if ( (PEAR::isError($result) === false) && ($result->numRows() > 0) )
		{
			$api_key = $result->fetchRow(MDB2_FETCHMODE_OBJECT);
			
			/*
			 * Bring the result into the scope of the object.
			 */
			foreach ($api_key as $key => $value)
			{
				$this->$key = $value;
			}
		}
		else
		{
			return false;
		}
	}
	
	
	/**
	 * Display a registration form.
	 */
	function display_form()
	{
	
		$form = '
			<form method="post" action="/api-key/" id="api-registration">
				
				<label for="name">Your Name</label>
				<input type="name" id="name" name="form_data[name]" placeholder="John Doe" value="'.$this->form->name.'" />
				
				<label for="email">E-Mail Address <span class="required">*</span></label>
				<input type="email" id="email" name="form_data[email]" placeholder="john_doe@example.com" required value="'.$this->form->email.'" />
				
				<label for="url">Website URL</label>
				<input type="url" id="url" name="form_data[url]" placeholder="http://www.example.com/" value="'.$this->form->url.'" />
				
				<input type="submit" value="Submit" />
				
			</form>
			
			<p id="required-note"><span class="required">*</span> Required field</p>';
		
		return $form;
	}
	
	
	/**
	 * Validate a submitted form.
	 */
	function validate_form()
	{

		if (!isset($this->form))
		{
			return false;
		}
		if (empty($this->form->email))
		{
			$this->form_errors = 'Please provide your e-mail address.';
			return false;
		}
		elseif (filter_var($this->form->email, FILTER_VALIDATE_EMAIL) === FALSE)
		{
			$this->form_errors = 'Please enter a valid e-mail address.';
			return false;
		}
		
		if ( !empty($this->form_url) && (filter_var($this->form->url, FILTER_VALIDATE_URL) === FALSE) )
		{
			$this->form_errors = 'Please enter a valid URL.';
			return false;
		}
		
		return true;
	}
	

	/**
	 * Register a new API key. Note that a registered key must be activated before it can be used.
	 */
	function register_key()
	{

		/*
		 * We're going to need access to the database connection within this method.
		 */
		global $db;

		$this->email = $this->form->email;
		
		if (isset($this->form->name))
		{
			$this->name = $this->form->name;
		}
		if (isset($this->form->url))
		{
			$this->url = $this->form->url;
		}
		
		/*
		 * Generate an API key and a secret hash.
		 */
		API::generate_key();
		API::generate_secret();
		
		/*
		 * Assemble the SQL query.
		 */
		$sql = 'INSERT INTO api_keys
				SET api_key = "'.$db->escape($this->key).'",
				email = "'.$db->escape($this->email).'",
				secret = "'.$db->escape($this->secret).'",
				date_created = now()';
		if (!empty($this->name))
		{
			$sql .= ', name="'.$db->escape($this->name).'"';
		}
		if (!empty($this->url))
		{
			$sql .= ', url="'.$db->escape($this->url).'"';
		}
		
		/*
		 * Insert this record.
		 */
		$result = $db->exec($sql);
		if (PEAR::isError($result) === true)
		{
			throw new Exception('API key could not be created.');
		}
		
		/*
		 * Send an activation e-mail, unless instructed otherwise.
		 */
		if ( !isset($this->suppress_activation_email) || ($this->suppress_activation_email !== TRUE) )
		{	
			API::send_activation_email();
		}
				
	}
	
	
	/**
	 * Activate an API key. (A registered key's default state is inactivate.)
	 */
	function activate_key()
	{

		/*
		 * We're going to need access to the database connection within this method.
		 */
		global $db;
		
		/*
		 * Assemble the SQL query and execute it.
		 */
		$sql = 'UPDATE api_keys
				SET verified = "y"
				WHERE secret = "'.$db->escape($this->secret).'"';
		$result = $db->exec($sql);
		
		if (PEAR::isError($result) === true)
		{
			throw new Exception('API key could not be activated.');
		}
		
		return true;
	}
	
	
	/**
	 * E-mail an API activation URL to the provided e-mail address.
	 *
	 * TODO
	 * This is an awfully crude way to send an e-mail. At present (v0.5), there is no other e-mail
	 * functionality, so there's no more advanced functionality to hook into, nor is it worth
	 * establishing a system just for this functionality.
	 */
	function send_activation_email()
	{
	
		if (!isset($this->email) || !isset($this->secret))
		{
			return false;
		}
		
		$email->body = 'Click on the following link to activate your '.SITE_TITLE.' API key.'
			."\r\r"
			.'http://'.$_SERVER['SERVER_NAME'].'/api-key/?secret='.$this->secret;
		$email->subject = SITE_TITLE.' API Registration';
		$email->headers = 'From: '.EMAIL_ADDRESS."\n"
						.'Return-Path: '.EMAIL_ADDRESS."\n"
						.'Reply-To: '.EMAIL_ADDRESS."\n"
						.'X-Originating-IP: '.$_SERVER['REMOTE_ADDR'];
		
		/*
		 * Send the e-mail.
		 */
		mail($this->email, $email->subject, $email->body, $email->headers);
		
		return true;
	}
	
	
	/**
	 * Generate an API key 16 characters in length.
	 */
	function generate_key()
	{
		
		$this->key = '';
		$valid_chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890';
		for ($i=0; $i<16; $i++)
		{
			$random = mt_rand(1, strlen($valid_chars));
			$this->key .= $valid_chars[$random-1];
		}
	}
	
	
	/**
	 * Generate a secret hash five characters in length.
	 */
	function generate_secret()
	{
		
		$this->secret = '';
		$valid_chars = 'bcdfghjkmnpqrstvwxz23456789';
		for ($i=0; $i<5; $i++)
		{
			$random = mt_rand(1, strlen($valid_chars));
			$this->secret .= $valid_chars[$random-1];
		}
	}
}
