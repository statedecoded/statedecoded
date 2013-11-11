<?php

/**
 * The Content class, a simple holder of content
 *
 * PHP version 5
 *
 * @license		http://www.gnu.org/licenses/gpl.html GPL 3
 * @version		0.8
 * @link		http://www.statedecoded.com/
 * @since		0.7
 *
 */

class Content
{

	public $data = array();

	/**
	 * Add a property to our content.
	 */
	public function set($field, $content = null)
	{
		return $this->data[$field] = $content;
	}

	/**
	 * Append to existing content.
	 */
	public function append($field, $content = null)
	{
	
		if(!isset($this->data[$field]))
		{
			$this->data[$field] = '';
		}
		return $this->data[$field] .= $content;
		
	}

	/**
	 * Prepend to existing content.
	 */
	public function prepend($field, $content = null)
	{
		return $this->data[$field] = $content . $this->data[$field];
	}

	/**
	 * Add several properties to our content.
	 */
	public function set_many($data)
	{
	
		foreach ($data as $name => $value)
		{
			$this->data[$name] = $value;
		}
		
	}

	public function get($field=null)
	{
	
		if (isset($field))
		{
		
			if(isset($this->data[$field]))
			{
				return $this->data[$field];
			}
			else
			{
				/*
				 * We return an empty string so we can easily check strlen() .
				 * Using empty() will throw an error:
				 *  "Can't use method return value in write context"
				 */
				return '';
			}
			
		}
		else
		{
			return $this->data;
		}
		
	}
	
}
