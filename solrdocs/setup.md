## Prerequisites

* OpenJDK/Oracle Java 6 or 7

## Download & Install

Download the latest binary release from [this location](http://lucene.apache.org/solr/). Untar and unzip the 
contents.

    tar -xzf solr-4.3.0

Confirm that you can run Solr from the example directory:

    cd solr-4.3.0/example/
    java -jar start.jar

You should be able to browse to the [Solr Admin UI](http://localhost:8983/solr) located on port 8983 on localhost. There should be a single collection -- collection1 located in the left drop-down. This is the default collection that Solr ships with -- it should be empty.

Don't worry that this directory is called "example". Its a production-ready Jetty based Solr install. The simplest approach is to simply use the example directory as effectively the "bin" directory of Solr. 

## Configuration

The next step is to point Solr at State Decoded's Solr config files. These files are part of the ["solr_home"](https://github.com/o19s/statedecoded/tree/master/solr_home) directory in this repository. So you'll need to pull down that directory on the same box as Solr. This directory contains information for creating a "statedecoded" Solr collection. A "collection" in Solr speak in synonymous with a database in a SQL database. Its going to be the entity that stores/indexes our laws and dictionary terms.

Pull down the Stated Decoded repository:

    git clone https://github.com/o19s/statedecoded.git 

Solr makes it pretty straightforward to point new configuraiton files. We simply need to specify a new solr home to Solr at startup (still running from the example directory):

    java -jar  -Dsolr.solr.home=/path/to/statedecoded/solr_home/ start.jar

(and yes thats two solrs in that argument)

Now if you browse to the [Solr Admin UI](http://localhost:8983/solr) instead of "collection1" you should see "statedecoded" in the drop down. It should be empty. Now you should be able to begin indexing [laws](demos/indexlaws.php) and [dictionary terms](demos/indexdict.php) into Solr.

## Additional Customization

See the full [Solr Jetty](http://wiki.apache.org/solr/SolrJetty) documentation for more information on ways to configure Solr with Jetty. Additional parameters that might be passed to Java:

* -Djetty.port=80 -- change the jetty port to port 80
