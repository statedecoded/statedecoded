This is an overview of State Decoded Setup & Usage. Locally this README can be viewed using [GRIP](https://github.com/joeyespo/grip).

## Installation & Setup 

Installation and setup involves downloading Solr, using its default Jetty-based distrubution, and then pointing Solr at State Decoded's config files. To perform these steps refer to the [Setup & Installation](setup.md) instructions located in this directory. The discussion below pertains to code that can be found in the [statedecoded/solrdocs/demos](demos) directory.

## Interacting with Solr

All interaction with Solr is over HTTP. Each HTTP endpoint exposed by Solr is known as a "requestHandler". These are specified in the [solrconfig.xml](../solr_home/statedecoded/conf/solrconfig.xml) for State Decoded.

Each request handler comes with defaults for the arguments supported by that request handler. For example, for search, many relevancy related parameters have been carefully tuned for the best search results. However there are others which lend themselves to being specified at query time via the url query string. Examples of these kinds of values include the number of rows to return or what fields should be returned.

State decoded's features in Solr: indexing, searching, auto-completion, or more-like-this are all implemented as a request handler. Parameters can be overriden by passing them via the query string in the URL. The demos described below describe specific parameters you might wish to customize.

## Note about the Demos

The documentation below contains several php demos for interacting with Solr. These demos use the php-curl library to interact with Solr over HTTP. On Ubuntu, php curl can be installed via apt-get:

    sudo apt-get install php5-curl

## Ingest State Decoded Laws & Dictionary

### Laws 

Ingestion of laws is performed by sending the State Decoded XML at Solr's [XSLT update handler](http://wiki.apache.org/solr/XsltUpdateRequestHandler). State Decoded XML is posted directly at Solr, with an query string argument specifying an XSLT to be used to transform the XML to Solr Update XML. A custom XSLT has been developed [stateDecodedXml.xsl](../solr_home/statedecoded/conf/xslt/stateDecodedXml.xsl) that Solr uses behind the scenes to transfer State Decoded XML to [Solr Update XML](http://wiki.apache.org/solr/UpdateXmlMessages).

See [indexlaws.php](demos/indexlaws.php) for a command-line runnable demo that indexes State Decoded XML laws.

### Dictionary Terms 

Ingestion of dictionary terms was done via Solr's [JSON update request handler](http://wiki.apache.org/solr/UpdateJSON). This update handler takes an array of flat json objects with the names of each field to index.

See [indexdict.php](demos/indexdict.php) for a command-line runnable demo of indexing dictionary terms.


## Searching w/ Faceting & Highlighting

* See [search.php](demos/search.php) for a command-line runnable demo of search. Features demoed include:

    * Basic search on laws or dictionary terms
    * [Solr highlighting](http://wiki.apache.org/solr/HighlightingParameters) -- returning matches and surrounding snippets of specific fields highlighted
    * [Solr faceting](http://wiki.apache.org/solr/SolrFacetingOverview) for the structure and section number fields -- returning a count of the number of search results in each section of the law, along with the ability to then filter on specific subsections.

## Single Document Search

The Single Doc request handler is intended for returning results appropriate to display on a document viewing page. This includes:

* [More Like This](http://wiki.apache.org/solr/MoreLikeThis) functionality
* More extensive [Highlighting](http://wiki.apache.org/solr/MoreLikeThis) functionality intended for displaying the entire document with highlighted snippets

See [singledoc.php](demos/singledoc.php) for a command-line runnable demo of Single Doc request handler.

## Suggest-as-you-type

We have developed a Javascript based demo of autocompletion, aka ["suggest as you type"](http://www.opensourceconnections.com/2013/06/08/advanced-suggest-as-you-type-with-solr/). 

Runnable by opening in a browser, [sample_suggest.html](demos/sample_suggest.html) provides a Javascript demo of interacting with Solr for autocompletion. Note, you may need to browse directly to this html file in your file system for your browser to run the javascript correctly. 



# Solr perspective

link here
