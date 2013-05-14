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

TODO


# Searching

In Solr, a RequestHandler is an endpoint for processing HTTP requests and responding with results from the search index. They can be configured, with default query parameters, in the solr_config.xml. 

At present, two RequestHandlers are defined for search. One for the laws and another for the dictionary. 

## Law Request Handler

The law request handler searches only the laws. It can be inspected by looking in solr_config.xml for the requestHandler that begins with:

    <requestHandler name="/law" ...>

Search the law request handler by accessing the endpoint:

    http://solrhost:8983/statedecodec/law?q=no%20child%20left%20behind

See [demos/lawsearch.php](demos/lawsearch.php) for more info.


## Dictionary Request Handler

The dictionary request handler searches only the dictionary terms.

    <requestHandler name="/dict" ...>

Search the dictionary request handler:

    http://solrhost:8983/statedecodec/law?q=no%20child%20left%20behind

See [demos/dictsearch.php](demos/dictsearch.php) for more info

# Additional Solr Features

## Suggest-as-you-type

TODO

## More-like-this

TODO

## Spell checking

TODO
