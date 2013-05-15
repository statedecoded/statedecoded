<?php

// *****************************************************************
// An HTTP get request to the request handler for law
// for the state decoded Solr core
// (GetRequest is just a simple wrapper around curl or 
//  file_get_contents)
$searchReq = new GetRequest("http://localhost:8983/solr/statedecoded/dict");


// *****************************************************************
// Specify a bunch of parameters into the Query String
// 
// Each RequestHandler has a series of default parameters as
// specified in the solrconfig.xml file. To modify default params,
// the line in solrconfig.xml defining the requestHandle can be
// found and modified. In this case, 
//      
//      <requestHandler name="/dict" class="solr.SearchHandler">
//
// You can override anything specified in the requestHandler
// by passing it in the query string. 
//
// Parameters specified at this link:
// http://wiki.apache.org/solr/CommonQueryParameters
// 
// ******************************************************************
// Query Parser
//
// Fields that take search queries (fq, q, etc) all conform to 
// a specific query parser. The default query parser specified
// for q in sorlconfig is edismax.
//
// Edismax allows you to specify a field to search (as we 
// have done below) OR it allows you to get more "google like"
// behavior -- typically more human friendly. When using the
// latter strategy, Edismax will simultaneously search all
// fields specified in the qf parameter.
//
// We have setup a baseline qf default in solrconfig that you
// can investigate and modify.
//
// qf also allows you to boost the impact of each field match
// in the final search rankings. pf allows you to boost the impact
// of a full phrase match within the specified fields.
//
// More information can be found here
// http://wiki.apache.org/solr/ExtendedDisMax
//
// ******************************************************************
// Escape chars
//
// Because query parsers and other values reserve certain characters, 
// you'll need to escape certain special characters for arguments to Solr
//
// See here for more info:
// http://e-mats.org/2010/01/escaping-characters-in-a-solr-query-solr-url/

// EXAMPLES
//  These are some examples as to how you'll likely want to search
//  the laws


// search term/definition for "motor vehicle"
$params = array("q" => "motor vehicle", # search query
    "rows" => "5"); # number of rows
$respJson = $searchReq->execute($params);
print_r($respJson);

// Page through the results with the "start" parameter 
$params = array("q" => "motor vehicle", # search query
    "rows" => "5", # number of rows from the matches
    "start" => "5"); # return starting with the 5th most relevant result
$respJson = $searchReq->execute($params);
print_r($respJson);

// Search 
// You can eliminate duplicates by grouping query 
// results with a "group by" over the term field
// You might want to do this as there's many terms with
// "motor vehicle"
//
// http://wiki.apache.org/solr/FieldCollapsing
$params = array("q" => "motor vehicle", # search query
    "rows" => "5",
    "group" => "true",
    "group.field" => "term"); # number of rows
$respJson = $searchReq->execute($params);
print_r($respJson);



//  search the text field for no child left behind
//  return fields catch_line,text
//  return only 5 rows
$params = array("q" => "definition:no child left behind", # search query
    "fl" => "catch_line,text", # field list to return
    "rows" => "5"); # number of rows
$respJson = $searchReq->execute($params);
print_r($respJson);

?>
