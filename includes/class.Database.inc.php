<?php

/**
 * PDO wrapper for The State Decoded.
 *
 * PHP version 5
 *
 * @license		http://www.gnu.org/licenses/gpl.html GPL 3
 * @version		0.9
 * @link		http://www.statedecoded.com/
 * @since		0.9
*/

class Database extends PDO
{
	public $_properties = array();

	public function __construct(
		$dsn,
		$username       = null,
		$password       = null,
		$driver_options = null)
	{
		parent::__construct($dsn, $username, $password, $driver_options);

		$this->_properties = array(
			'dsn'            => $dsn,
			'username'       => $username,
			'password'       => $password,
			'driver_options' => $driver_options
		);
	}

	public function query( $query )
	{
		$this->_query = $query;
		$result = parent::query($query);

		return($result);
	}

	public function exec( $query )
	{
		$this->_query = $query;
		$result = parent::exec($query);

		return($result);
	}

	public function prepare( $query, $driver_options = array() )
	{
		$this->_query = $query;
		$pdo_statement = parent::prepare( $query, $driver_options );
		return new DatabaseStatement($this, $pdo_statement, $query);
	}

	/*
	 * As we don't have access to the raw connection, we instead return a new instance of
	 * this class, as a drop-in replacement for the previous one.
	 */
	public function reconnect() {
		return new Database(
			$this->_properties['dsn'],
			$this->_properties['username'],
			$this->_properties['password'],
			$this->_properties['driver_options']
		);
	}
}
