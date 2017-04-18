<?php

require_once 'class.CliAction.inc.php';
require_once CUSTOM_FUNCTIONS;

global $db;

// TODO: Allow editions to be made current.
//       When this happens, permalinks will need to be created.

class EditionAction extends CliAction
{
  static public $name = 'edition';
  static public $summary = 'Manage editions.';

  public function __construct($args = array())
  {
    parent::__construct($args);

    global $db;
    $db = new Database( PDO_DSN, PDO_USERNAME, PDO_PASSWORD );
    $this->db = $db;

    $this->logger = new Logger();
  }

  public function execute($args = array())
  {
    $method = array_shift($args);

    switch($method)
    {
      case 'create':
        return $this->createEdition($args);
        break;

      case 'list' :
        return $this->listEdition($args);
        break;

      case 'delete' :
        return $this->deleteEdition($args);

      default:
        return $this->showCurrentEdition($args);
    }
  }

  public function createEdition($args = array())
  {
    $edition_obj = new Edition(array('db' => $this->db));

    $edition = array();

    if(isset($args[0]))
    {
      $edition['name'] = $args[0];
    }
    else {
      // Throw an error.
      $this->result = 1;
      return 'You must provide a name for this edition.';
    }

    if(isset($args[1]))
    {
      $edition['slug'] = $args[1];
    }
    else
    {
      $edition['slug'] = preg_replace(
        array('/( )/', '/([^a-z0-9-])/'),
        array('-', ''),
        strtolower($edition['name'])
      );
    }

    $edition['current'] = 0;

    if($edition_obj->create($edition))
    {
      return 'Created';
    }
    else {
      $this->result = 1;
      return 'Unable to create edition.';
    }

  }

  public function deleteEdition($args = array())
  {
    if($args[0])
    {
      $edition_obj = new Edition(array('db' => $this->db));
      if($edition_obj->delete($args[0]))
      {
        return 'Edition deleted.';
      }
      else
      {
        $this->result = 1;
        return 'Unable to delete edition.';
      }

    }
    else
    {
      $this->result = 1;
      return 'Please specify an edition to delete.';
    }

  }

  public function listEdition($args = array())
  {
    $edition_obj = new Edition(array('db' => $this->db));

    $editions = $edition_obj->all();

    if(isset($this->options['v']))
    {
      return json_encode($editions);
    }
    else
    {
      $return = '';
      foreach($editions as $edition) {
        $return .= $edition->name;
        if($edition->current) {
          $return .= ' [current]';
        }
        $return .= "\n";
      }
      return $return;
    }
  }

  public function showCurrentEdition($args = array())
  {
    $edition_obj = new Edition(array('db' => $this->db));

    $edition = $edition_obj->current();

    if(isset($this->options['v']))
    {
      return json_encode($edition);
    }
    else
    {
      return $edition->name;
    }
  }

  public static function getHelp($args = array())
  {
    return <<<EOS
statedecoded : edition

Manages editions.

Usage:
  statedecoded edition
    Returns the current edition.

  statedecoded edition list
    Lists all available editions.

  statedecoded edition create name slug
    Creates a new edition.

  statedecoded edition delete slug
    Delete an edition.

Available options:

  name
      Use this as the name for the edition.

  slug
      Use this as the slug for the edition.


EOS;

  }
}