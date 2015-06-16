<?php

/**
 * Wrapper class for Solarium.
 *
 * PHP version 5
 *
 * @license		http://www.gnu.org/licenses/gpl.html GPL 3
 * @version		0.9
 * @link		http://www.statedecoded.com/
 * @since		0.9
 */

require_once(INCLUDE_PATH . 'class.SearchEngineInterface.inc.php');

require_once(INCLUDE_PATH . 'Solarium/Autoloader.php');
Solarium_Autoloader::register();

class SolrSearchEngine extends SearchEngineInterface
{
	/*
	 * Solr Config.
	 */
	protected $config;

	/*
	 * Our Solarium client.
	 */
	protected $client;

	/*
	 *The current transaction.
	 */
	protected $transaction;

	/*
	 * The type of transaction.
	 */
	protected $transaction_type;

	/*
	 * The documents to put into our current transaction.
	 */
	public $documents = array();

	/*
	 * Number of documents to store before automatically flushing.
	 */
	public $batch_size = 100;

	/*
	 * Hang on to our last result.
	 */
	protected $last_result;

	public function __construct($args = array())
	{
		parent::__construct($args);

		if(!isset($this->config['batch_size']))
		{
			$this->config['batch_size'] = $this->batch_size;
		}

		$this->client = new Solarium_Client(
			array(
				'adapteroptions' => $this->config
			)
		);
	}

	public function start_update()
	{
		if($this->transaction !== null)
		{
			trigger_error('Warning from SolrSearchEngine->start_update() - ' .
				'Starting update, stomping on existing transaction',
				E_USER_WARNING);
		}

		$this->transaction = $this->client->createUpdate();
		$this->transaction_type = 'update';
	}

	public function add_document($record)
	{
		if(strtolower(get_class($record)) === 'law')
		{
			$document = $this->law_to_document($record);
		}
		elseif(strtolower(get_class($record)) === 'structure')
		{
			$document = $this->structure_to_document($record);
		}
		else {
			throw new Exception('Record has a bad type in SolrSearchEngine->add_document');
		}

		$this->documents[] = $document;

		// If we've reached the maximum size, flush our documents to the index.
		if(count($this->documents) >= $this->config['batch_size'])
		{
			$this->commit();
			$this->start_update();
		}

		return $document;
	}

	public function commit()
	{
		if($this->transaction_type === 'update')
		{

			// Add the documents and a commit command to the update query.
			$this->transaction->addDocuments($this->documents);
			$this->transaction->addCommit();

			// This executes the query and returns the result.
			$this->last_result = $this->client->update($this->transaction);
		}

		if($this->last_result->getStatus() === 0)
		{
			unset($this->transaction);
			unset($this->documents);
			return TRUE;
		}

		throw new Exception('Solr query failed.');
		return FALSE;
	}

	public function debug()
	{
		echo 'Query type: ' . $this->transaction_type . "\n";
		echo 'Query status: ' . $result->getStatus(). "\n";
		echo 'Query time: ' . $result->getQueryTime() . "\n";
	}

	public function law_to_document($law)
	{
		$document = $this->transaction->createDocument();

		// Set our site config info.
		$document->site_identifier = $this->config['site']['identifier'];
		$document->site_name = $this->config['site']['name'];
		$document->site_url = $this->config['site']['url'];

		$document->id = 'l_' . $law->edition->id . '_' . $law->token;
		$document->law_id = $law->law_id;

		$document->section_number = $law->section_number;

		$document->edition_id = $law->edition->id;
		$document->edition = $law->edition->name;
		$document->edition_slug = $law->edition->slug;
		$document->edition_updated = $law->edition->last_import;
		$document->edition_current = $law->edition->current;

		$document->catch_line = $law->catch_line;
		$document->tags = $law->tags;

		$document->text = $law->full_text;

		$document->repealed = $law->metadata->repealed;

		$structure = array();
		foreach($law->ancestry as $key=>$value)
		{
			$structure[] = $value->identifier . ' ' . $value->name;
		}
		$document->structure = join('/', $structure);

		$document->refers_to = array();
		foreach($document->refers_to as $law)
		{
			$document->refers_to[] = $law->section;
		}

		$document->referred_to_by = array();
		foreach($document->referred_to_by as $law)
		{
			$document->referred_to_by[] = $law->section;
		}

		return $document;
	}

	public function structure_to_document($structure)
	{
	/*
		$document = $this->transaction->createDocument();

		$document->id =

		//$document->section =

		$document->edition_id = $structure->edition->id;
		$document->edition = $structure->edition->name;
		$document->edition_updated = $structure->edition->last_import;
		$document->edition_current = $structure->edition->current;

		$document->catch_line = $structure->name;
		//$document->tags =

		$document->text =

		$document->repealed =

		$document->structure =

		return $document;
	*/
	}

	public function search($query = array())
	{
		// We try our query here so we can wrap the exception into a standard one.
		try
		{
			/*
			 * Set up our query.
			 */
			$select = $this->client->createSelect();
			$select->setHandler('search');
			$select->setQuery($query['q']);

			if(isset($query['edition_id']))
			{
				$select->createFilterQuery('edition_id')->setQuery(
					'edition_id:' . $query['edition_id']);
			}

			/*
			 * We want the most useful bits highlighted as search results snippets.
			 */
			$hl = $select->getHighlighting();
			$hl->setFields('catch_line, text');
			$hl->setSimplePrefix('<span>');
			$hl->setSimplePostfix('</span>');

			/*
			 * Check the spelling of the query and suggest alternates.
			 */
			$spellcheck = $select->getSpellcheck();
			$spellcheck->setQuery($query['q']);
			$spellcheck->setBuild(TRUE);
			$spellcheck->setCollate(TRUE);
			$spellcheck->setExtendedResults(TRUE);
			$spellcheck->setCollateExtendedResults(TRUE);

			/*
			 * Specify which page we want, and how many results.
			 */
			$select->setStart(($query['page'] - 1) * $query['per_page'])
				->setRows($query['per_page']);


			$results = $this->client->select($select);
			$this->last_result = $results;

			// Wrap the results and return them.
			return new SolrSearchResults($query, $results);
		}
		catch (Exception $error)
		{
			throw new Exception( $error->getStatusMessage() );
		}
	}

	public function find_related($object, $count=5)
	{
		if(strtolower(get_class($object)) === 'law')
		{
			$id = 'l_' . $object->edition_id . '_' . $object->permalink->token;
		}
		// elseif(strtolower(get_class($object)) === 'structure')
		// {
		// NOT YET SUPPORTED
		// }
		else {
			throw new Exception('Record has a bad type in SolrSearchEngine->add_document');
		}

		if(isset($id))
		{
			$query = $this->client->createMoreLikeThis();

			$query->setQuery('id:' . $id);
			$query->setMltFields('catch_line,text,definition');
			$query->setRows($count);
			$query->setMinimumDocumentFrequency(1);
			$query->setMinimumTermFrequency(1);
			$query->createFilterQuery('edition_id')->setQuery('edition_id:'.$object->edition_id);
			$query->setInterestingTerms('details');
			$query->setMatchInclude(true);

			$results = $this->client->select($query);

			$this->last_result = $results;

			if($results)
			{
				// Wrap the results and return them.
				return new SolrSearchResults($query, $results);
			}
			else
			{
				return FALSE;
			}
		}
	}
}
