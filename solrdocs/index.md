# Solr + State Decoded

## Standup State Decoded

1. Install [Solr](http://www.apache.org/dyn/closer.cgi/lucene/solr/4.3.0)
2. Retreive the State Decoded [solr_home](https://github.com/o19s/statedecoded/tree/master/solr_home) directory containing State Decodeds Solr config & schema
3. Run Solr with the command

    cd example/
    java -jar -Dsolr.solr.home=/path/to/statedecoded/solr_home/ start.jar

This runs Solr with the built-in Jetty Binaries



## Ingestion Process

### Prereqs:

* You have laws in the StateDecoded XML format

### Outputs:

* Laws ingested into Solr

Check out [this](this.md) link!
