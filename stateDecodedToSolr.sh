#!/bin/bash

echo "select * from laws limit 100" | mysql -u$1 -p$2 vadecoded > /tmp/laws.tsv

# Stolen from
# http://wiki.apache.org/solr/UpdateCSV
curl 'http://localhost:8983/solr/statedecoded/update/csv?commit=true&separator=%09&escape=\&stream.file=/tmp/laws.tsv'
