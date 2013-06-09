<?php

require_once('getreq.php');


// ******************************************************************
// Escape chars
//
// Because query parsers and other values reserve certain characters, 
// you'll need to escape certain special characters for arguments to Solr
//
// Adapted from 
// http://e-mats.org/2010/01/escaping-characters-in-a-solr-query-solr-url/
/*function escapeSolrValue($string)
{
    $match = array('\\', '+', '-', '&', '|', '!', '(', ')', '{', '}', '[', ']', '^', '~', '*', '?', ':', '"', ';', ' ');
    $replace = array('\\\\', '\\+', '\\-', '\\&', '\\|', '\\!', '\\(', '\\)', '\\{', '\\}', '\\[', '\\]', '\\^', '\\~', '\\*', '\\?', '\\:', '\\"', '\\;', '\\ ');
    $string = str_replace($match, $replace, $string);
    return $string;
}*/


// *****************************************************************
// Search for laws by passing the parameters into the Query String
// 
// State decoded search is done by the search request handler--
// Each RequestHandler has a series of default parameters as
// specified in the solr_home/conf/solrconfig.xml file based on
// careful tuning. To modify default params, the line in solrconfig.xml 
// defining the requestHandler can be found and modified. 
//
// In this case, 
//      
//      <requestHandler name="/search" class="solr.SearchHandler">
//
// You can override anything specified in the requestHandler
// by passing it in the query string.
//
// HOWEVER--
// For the most part you'll want to avoid tweaking solrconfig.xml 
// without the aid of a Solr expert instead pass a subset of the
// params listed below
//
// 
function searchLaws($query, $solrUrl, $pageNo) {

    $resultsPerPage = 3;
    $start = $resultsPerPage * $pageNo;

    // Run a search query against the search request handler,
    // and use these parameters instead of what is specified
    // in the search request handler
    $params = array("q" => $query,
        "fq" => "type:law", // apply a filter query, only get laws
        "rows" => "$resultsPerPage", // retreive this many rows
        "start" => "$start", // Start at resurt $start
        "indent" => "true"); // pretty print the resulting json
                                

    // Parameters you're most likely going to want
    // to customize
    // http://wiki.apache.org/solr/CommonQueryParameters
    //
    // fl -- what fields come back from the query
    //       defaults to all
    // fq -- set to type:law to search only laws
    //       set to type:dict to search only dict
    // rows  -- number of search results to 
    //          return
    // start -- which row number should the results
    //          start on?
    // indent -- set to true for pretty printed 
    //           output (useful for debugging)
    // hl.fl -- which fields to return highlighting 
    //          results for
    //
    // Many of the other parameters have been carefully

    // *****************************************************************
    // An HTTP get request to the request handler for law
    // for the state decoded Solr core
    $searchReq = new GetRequest($solrUrl);
    $respJson = $searchReq->get($params);
    $error = checkForSolrError($respJson);
    if ($error !== FALSE) {
        echo "Failure Executing Search:\n";
        echo $error;
        die();
    }
    else {
        return $respJson;
    }
}


$longopts = array("solrUrl:", "query:", "pageNo");
$opts = getopt("", $longopts);
if (!in_array('solrUrl', array_keys($opts))) {
    echo "Usage:\n";
    echo "php lawsearch.php \\\n";
    echo "    --solrUrl='http://localhost:8983/solr/statedecoded/search \\\n";
    echo "   [--query=\"no child left behind\" \\\n";
    echo "    --pageNo=2] \n";
    echo "Returns 10 search results for the specified query\n";
    echo "If pageNo is specified goes to that page of the \n";
    echo "results. IE pageNo == 0, first 10, pageNo ==1 next 10\n";
    echo "Query should be parsable by edismax query parser\n";
    echo "";
    echo "ie: http://wiki.apache.org/solr/ExtendedDisMax";
    echo "Default query is *:* and default pageNo is 0";
    die();
}

$solrUrl = $opts['solrUrl'];
$query = '*:*';
$pageNo = 0;

if (in_array('query', array_keys($opts))) {
    $query = $opts['query'];
}
if (in_array('pageNo', array_keys($opts))) {
    $query = $opts['pageNo'];
}

echo searchLaws($query, $solrUrl, $pageNo);


?>
