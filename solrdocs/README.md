This is an overview of State Decoded Setup & Usage. Locally this README can be viewed using [GRIP](https://github.com/joeyespo/grip).

# Installation & Setup 

## PreRequisites

* A server with Java 6, OpenJDK or Oracle Java

## Installation

Install the latest 4.x Solr from [this location](http://lucene.apache.org/solr/). Confirm Solr works by entering Solr's example directory, and running the command:

    java -jar start.jar

Browse to 

    http://host:8983/solr

and confirm the Solr admin UI can be accessed. There should be a single, empty collection, collection1.

## Run with State Decoded solr home

Pull down State Decoded's solr_home from its github repo. Run start.jar with the extra -Dsolr.solr.home parameter (yes that's 2 solrs). This will load all of State Decoded's config files.

    java -jar  -Dsolr.solr.home=/path/to/statedecoded/solr_home/ start.jar

## Ingest State Decoded Laws & Dictionary

Ingestion is performed by sending the State Decoded XML at Solr's [XSLT update handler](http://wiki.apache.org/solr/XsltUpdateRequestHandler). Examine the [indexlaws.php](demos/indexlaws.php) for detailed examples and additional information.



# Searching

In Solr, a RequestHandler is an endpoint for processing HTTP requests and responding with results from the search index. They can be configured, with default query parameters, in the solr_config.xml.  Additional query parameters are sent in the query part of the URL get request in the form key=value&key1=value.

* See [lawsearch.php](demos/lawsearch.php) for more info for the law request handler and example usage
* See [dictsearch.php](demos/dictsearch.php) for more info on the dictionary request handler and example usage


## Dictionary Request Handler

TODO

## Suggest-as-you-type

TODO

## More-like-this

TODO

## Spell checking

TODO
