<?php

/**
 * Wrapper class for PDO Statement.
 *
 * This mostly is just a passthrough for PDO Statement methods.
 * We've also added some error checking.
 *
 * PHP version 5
 *
 * @license		http://www.gnu.org/licenses/gpl.html GPL 3
 * @version		0.9
 * @link		http://www.statedecoded.com/
 * @since		0.9
 */

class DatabaseStatement extends PDOStatement
{
	protected $pdo_statement;
	protected $database;

	protected $query;

	public function __construct ( &$database, &$pdo_statement, $query )
	{
		$this->database =& $database;
		$this->pdo_statement =& $pdo_statement;
		$this->query = $query;
	}

	public function bindColumn ( $column, &$param, $type = null, $maxlen = null,
		$driverdata = null )

	{
		return $this->pdo_statement->bindColumn($column, $param, $type, $maxlen,
			$driverdata);
	}

	public function bindParam ( $parameter, &$variable, $data_type = null, $length = null,
		$driver_options = null )
	{
		return $this->pdo_statement->bindParam($parameter, $variable, $data_type,
			$length, $driver_options);
	}

	public function bindValue ( $parameter, $value, $data_type = null )
	{
		return $this->pdo_statement->bindValue($parameter, $value, $data_type);
	}

	public function closeCursor ()
	{
		return $this->pdo_statement->closeCursor();
	}

	public function columnCount ()
	{
		return $this->pdo_statement->columnCount();
	}

	public function debugDumpParams ()
	{
		return $this->pdo_statement->debugDumpParams();
	}

	public function errorCode ()
	{
		return $this->pdo_statement->errorCode();
	}

	public function errorInfo ()
	{
		return $this->pdo_statement->errorInfo();
	}

	public function execute ( $input_parameters = null )
	{
		try
		{
			$result = $this->pdo_statement->execute($input_parameters);
		}
		catch(Exception $e)
		{
			if(strpos($e->getMessage(), 'Error while sending QUERY packet.') !== FALSE)
			{
				$result = FALSE;
				$error = 'MySQL server has gone away';
			}
			else
			{
				throw $e;
			}
		}


		if($result === FALSE)
		{
			if($this->recoverError())
			{
				$result = $this->pdo_statement->execute($input_parameters);
			}
			else {
				throw new Exception( $this->formatErrors( $this->fetchErrors($input_parameters) ) );
			}
		}

		return $result;
	}

	protected function recoverError ()
	{
		// If the server has gone away, simply try to reconnect.
		$disconnect_error = 'MySQL server has gone away';

		$error_info = $this->errorInfo();
		if ( !isset($error_info[0]) || (boolean) $error_info[0] === FALSE )
		{
			$error_info = $this->database->errorInfo();
		}

		if ( $error_info[2] === $disconnect_error)
		{
			// Create a new database instance.
			$this->database = $this->database->reconnect();

			// Re-prepare the statement.
			$statement = $this->database->prepare($this->query);

			// Replace our old statement with a new one.
			$this->pdo_statement =& $statement->pdo_statement;

			return TRUE;
		}

		return FALSE;
	}

	/*
	 * There are several types of errors we need to check for. The PDO object may
	 * return the error we need, or it may be the statement itself.  Either way,
	 * we try to get what useful data we can from it.
	 */
	protected function fetchErrors ( $input_parameters = array() )
	{
		$error = array();
		if ( strlen($this->query) )
		{
			$error['Query'] = $this->query;
		}
		if ( $this->errorCode() )
		{
			$error['Statement Code'] = $this->errorCode();
		}
		if ( $this->errorInfo() )
		{
			$error['Statement Info'] = $this->errorInfo();
		}
		if ( $this->database->errorCode() )
		{
			$error['Database Code'] = $this->database->errorCode();
		}
		if ( $this->database->errorInfo() )
		{
			$error['Database Info'] = $this->database->errorInfo();
		}

		$error['Input Parameters'] = $input_parameters;

		/*
		 * Capture the parameters from PDO.
		 */
		ob_start();
		$this->pdo_statement->debugDumpParams();
		$error['Statement Parameters'] = ob_get_contents();
		ob_end_clean();

		return $error;
	}

	protected function formatErrors ( $errors = array() )
	{
		return print_r($errors, TRUE);
	}

	public function fetch ( $fetch_style = null, $cursor_orientation = null, $cursor_offset = null )
	{
		return $this->pdo_statement->fetch($fetch_style, $cursor_orientation, $cursor_offset);
	}

	public function fetchAll ( $fetch_style = null, $fetch_argument = null, $actor_args = null )
	{
		if(isset($fetch_argument))
		{
			return $this->pdo_statement->fetchAll($fetch_style, $fetch_argument, $actor_args);
		}
		else
		{
			return $this->pdo_statement->fetchAll($fetch_style);
		}
	}

	public function fetchColumn ( $column_number = null )
	{
		return $this->pdo_statement->fetchColumn($column_number);
	}

	public function fetchObject ( $class_name = null, $actor_args = null )
	{
		return $this->pdo_statement->fetchObject($class_name, $actor_args);
	}

	public function getAttribute ( $attribute )
	{
		return $this->pdo_statement->getAttribute($attribute);
	}

	public function getColumnMeta ( $column )
	{
		return $this->pdo_statement->getColumnMeta($column);
	}

	public function nextRowset ()
	{
		return $this->pdo_statement->nextRowset();
	}

	public function rowCount ()
	{
		return $this->pdo_statement->rowCount();
	}

	public function setAttribute ( $attribute, $value )
	{
		return $this->pdo_statement->setAttribute($attribute, $value);
	}

	public function setFetchMode ( $mode, $params = null, $ctorargs = null )
	{
		return $this->pdo_statement->setFetchMode($mode, $params, $ctorargs);
	}

}
