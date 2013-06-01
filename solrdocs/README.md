This is an overview of State Decoded Setup & Usage. Locally this README can be viewed using [GRIP](https://github.com/joeyespo/grip).

# Installation & Setup 

Installation and setup involves downloading Solr, using its default Jetty-based distrubution, and then pointing Solr at State Decoded's config files. To perform these steps refer to the [Setup & Installation](setup.md) instructions located in this directory.

## Ingest State Decoded Laws & Dictionary

Ingestion is performed by sending the State Decoded XML at Solr's [XSLT update handler](http://wiki.apache.org/solr/XsltUpdateRequestHandler). Examine the [indexlaws.php](demos/indexlaws.php) for detailed examples and additional information.


## Searching

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



## John's Notes
https://docs.google.com/a/opensourceconnections.com/document/d/1qDTDl_VMSjpQGQcdXl8abqY3EKAtJtqUQ_8t-zLkSwY/edit

