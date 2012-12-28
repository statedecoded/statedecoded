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
		
		$sql = 'SELECT key
				FROM api_keys
				WHERE verified="y"';
		$result = $db->query($sql);
		
		/*
		 * If the query succeeds then retrieve each row and build up an object containing a list
		 * of all keys.
		 */
		if ( (PEAR::isError($result) === false) && ($result->numRows() > 0) )
		{
			$i=0;
			while ($key = $result->fetchRow(MDB2_FETCHMODE_OBJECT))
			{
				$this->all_keys->{$i} = $key;
				$i++;
			}
		}
		
		return true;
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
		$sql = 'SELECT id, key, email, name, url, verified, secret_hash, date_created
				FROM api_keys
				WHERE key = "'.$this->key.'"';
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
	 * Validate a submitted form.
	 */
	function validate()
	{
		if (!isset($this->form))
		{
			return false;
		}
		
		if (!isset($this->form->email))
		{
			throw new Exception('Please provide your e-mail address.');
		}
		elseif (!filter_var($this->form->email, FILTER_VALIDATE_EMAIL))
		{
			throw new Exception('Please enter a valid e-mail address.');
		}
		
		if (!filter_var($this->form->url, FILTER_VALIDATE_URL))
		{
			throw new Exception('Please enter a valid URL.');
		}
		
		return true;
	}

	/**
	 * Register a new API key. Note that a registered key must be activated before it can be used.
	 */
	function register()
	{

		/*
		 * We're going to need access to the database connection within this method.
		 */
		global $db;
		
		/*
		 * Generate an API key and a secret hash.
		 */
		API::generate_key();
		API::generate_hash();
		
		/*
		 * Assemble the SQL query.
		 */
		$sql = 'INSERT INTO api_keys
				SET key = "'.$this->key.'",
				email = "'.$this->email.'",
				secret_hash = "'.$this->secret_hash.'",
				date_created = now()';
		if (isset($this->name))
		{
			$sql .= ', name="'.$this->name.'"';
		}
		if (isset($this->url))
		{
			$sql .= ', url="'.$this->url.'"';
		}
		
		/*
		 * Insert this record.
		 */
		$result = $db->exec($sql);
				
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
				SET verified = "n"
				WHERE secret_hash = "'.$this->secret_hash.'"';
		$result = $db->exec($sql);
		
		if ( (PEAR::isError($result) === false)
		{
			return false;
		}
		return true;
	}
	
	/**
	 * E-mail an API activation URL to the provided e-mail address.
	 */
	function send_activation_email()
	{
	
		if (!isset($this->email) || !isset($this->secret_hash))
		{
			return false;
		}
		
		$email->body = 'Click on the following link to activate your '.SITE_TITLE.' API key.'
			."\r\r"
			.'http://'.$_SERVER['SERVER_NAME'].'/api-register/?hash='.$this->secret_hash;
		$email->subject = SITE_TITLE.' API Registration';
		$email->headers = 'From: '.EMAIL_ADDRESS;
		
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
	function generate_secret_hash()
	{
		
		$this->secret_hash = '';
		$valid_chars = 'abcdefghjkmnpqrstuvwxyz23456789';
		for ($i=0; $i<5; $i++)
		{
			$random = mt_rand(1, strlen($valid_chars));
			$this->secret_hash .= $valid_chars[$random-1];
		}
	}
}

?>