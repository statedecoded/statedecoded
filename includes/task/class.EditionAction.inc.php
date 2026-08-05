<?php

require_once 'class.CliAction.inc.php';
require_once CUSTOM_FUNCTIONS;

class EditionAction extends CliAction
{
  static public $name = 'edition';
  static public $summary = 'Manage editions.';

  public function __construct($args = [])
  {
    parent::__construct($args);

    global $db;
    $db = new Database( PDO_DSN, PDO_USERNAME, PDO_PASSWORD );
    $this->db = $db;

    $this->logger = new Logger();
  }

  public function execute($args = [])
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

      case 'current' :
        if(count($args) > 0)
        {
          return $this->setCurrentEdition($args);
        }
        return $this->showCurrentEdition($args);

      default:
        return $this->showCurrentEdition($args);
    }
  }

  public function createEdition($args = [])
  {
    $edition_obj = new Edition(['db' => $this->db]);

    $edition = [];

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
        ['/( )/', '/([^a-z0-9-])/'],
        ['-', ''],
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

  public function deleteEdition($args = [])
  {
    if($args[0])
    {
      $edition_obj = new Edition(['db' => $this->db]);
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

  public function listEdition($args = [])
  {
    $edition_obj = new Edition(['db' => $this->db]);

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

  public function setCurrentEdition($args = [])
  {
    $edition_obj = new Edition(['db' => $this->db]);

    $edition = $edition_obj->find_by_slug($args[0]);

    if(!$edition)
    {
      $this->result = 1;
      return 'Unable to find edition "' . $args[0] . '".';
    }

    if($edition->current)
    {
      return 'Edition "' . $edition->name . '" is already current.';
    }

    /*
     * Promote the edition through the same code path that the importer and
     * the admin web UI use, so that the previous edition is demoted and the
     * edition ID is stored in .htaccess.
     */
    $parser = $this->getParserController();

    $started = microtime(true);

    $this->logger->message('Making edition "' . $edition->name . '" current.', 5);

    $edition_errors = $parser->handle_editions(
      [
        'edition_option' => 'existing',
        'edition' => $edition->id,
        'make_current' => 1
      ]
    );

    if(count($edition_errors) > 0)
    {
      $this->result = 1;
      return implode("\n", $edition_errors);
    }

    /*
     * The site's URLs are derived from the current edition, so they must be
     * rebuilt, and any cached copies of the old URLs invalidated. Rebuilding
     * the permalinks is by far the slowest part of this, so each step says so
     * before it starts.
     */
    $this->logger->message('Clearing the cache.', 5);
    $parser->clear_cache();

    $parser->build_permalinks();

    /*
     * The bulk downloads are served through a "current" symlink, which points
     * at whichever edition was last exported. Promoting an edition does not
     * re-export it, so the symlink has to be repointed here.
     */
    $parser->update_downloads_symlink();

    /*
     * sitemap.xml describes whichever edition was last imported, so promoting
     * a different edition leaves it advertising the wrong URLs to search
     * engines. Rebuild it for the edition that is now current.
     */
    $this->logger->message('Regenerating sitemap.xml.', 5);
    $parser->generate_sitemap();

    $this->logger->message('Purging the Varnish cache, if there is one.', 5);
    $varnish = new Varnish;
    $varnish->purge();

    return 'Edition "' . $edition->name . '" is now current. ('
      . $parser->format_duration(microtime(true) - $started) . ')';
  }

  /*
   * Instantiate the parser controller that setCurrentEdition() drives.
   * A separate method so that tests can substitute a test double.
   */
  public function getParserController()
  {
    return new ParserController(
      [
        'logger' => $this->logger,
        'db' => &$this->db
      ]
    );
  }

  public function showCurrentEdition($args = [])
  {
    $edition_obj = new Edition(['db' => $this->db]);

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

  public static function getHelp($args = [])
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

  statedecoded edition current slug
    Makes an edition current, and rebuilds the site's permalinks so that
    the edition is served from the site root.

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