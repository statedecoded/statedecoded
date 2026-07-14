<?php

/**
 * PDO wrapper for The State Decoded.
 *
 * PHP version 8
 *
 * @license		http://www.gnu.org/licenses/gpl.html GPL 3
 * @version		1.0
 * @link		https://www.statedecoded.com/
 * @since		0.9
*/

class Database extends PDO
{
	public $_properties = [];
	public $_query = null;

	public function __construct(
		$dsn,
		$username       = null,
		$password       = null,
		$driver_options = null)
	{
		$driver_options = array_replace(
			[PDO::ATTR_ERRMODE => PDO::ERRMODE_SILENT],
			(array) $driver_options
		);

		parent::__construct($dsn, $username, $password, $driver_options);

		$this->_properties = [
			'dsn'            => $dsn,
			'username'       => $username,
			'password'       => $password,
			'driver_options' => $driver_options
		];
	}

	public function query( string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs ): \PDOStatement|false
	{
		$this->_query = $query;
		if ($fetchMode !== null) {
			$result = parent::query($query, $fetchMode, ...$fetchModeArgs);
		} else {
			$result = parent::query($query);
		}
		return $result;
	}

	public function exec( string $query ): int|false
	{
		$this->_query = $query;
		$result = parent::exec($query);

		return $result;
	}

	#[\ReturnTypeWillChange]
	public function prepare( string $query, array $driver_options = [] ): DatabaseStatement|false
	{
		$this->_query = $query;
		$pdo_statement = parent::prepare( $query, $driver_options );
		if ($pdo_statement === false) {
			return false;
		}
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
