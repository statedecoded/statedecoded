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
function searchStateDecoded($query, $solrUrl, $pageNo, $dict, $structureFacetFilters, $facetdepth) {

    $resultsPerPage = 3;
    $start = $resultsPerPage * $pageNo;

    $filters = array();
    // Create a list of multiple fq= parameters to 
    // pass in the query string. For more on fq see below
    if ($dict) {
        $filters[] = "type:dict";
    }
    else {
        $filters[] = "type:law";
    }
    
    // As facets are enabled, a typical option to give users
    // is the ability to click on a facet and filter the results
    // in that only match that facet
    //
    // We're exposing this as a command line option expecting
    // that in this demo you might copy/paste the displayed
    // facets into a command line argument to simulate a click
    // in an application
    foreach ($structureFacetFilters AS $structureFacetFilter) {
        $filters[] = "structure_descendent:\"" . $structureFacetFilter . "\"";
    }


    // Run a search query against the search request handler,
    // and use these parameters instead of what is specified
    // in the search request handler
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
    $params = array("q" => $query,
        "fq"   => $filters,
        "rows" => "$resultsPerPage", // retreive this many rows
        "start" => "$start", // Start at resurt $start
        "indent" => "true",  // pretty print the resulting json
        "facet.prefix" => "$facetdepth"); // use the facet prefix to only return results 
                                          // tagged with a specific depth
                                          // ie only facets of 2 structure deep (Health/Disease Prevention and Control)
                                          // facet.prefix = 2 

    // *****************************************************************
    // An HTTP get request to the request handler for law
    // for the state decoded Solr core
    $searchReq = new GetRequest($solrUrl);
    $respJson = $searchReq->get($params);
    // Some error checking, did Solr return an error?
    //  Does it look like Solr is even up?
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


// Display a subset of the law response Json
// only shown when --dict not present
// http://wiki.apache.org/solr/SolJSON#JSON_Query_Response_Format
//
function lawSearchResultsCommandLineDisplay($respJson) {
    // Important notes:
    // (1) {response { docs // contains the search results
    //                          // and values for each result
    //                          // Control what fields get returned with the
    //                          // fl parameter
    // (2) {highlighting // contains highlighted values for
    //                                  // each search result
    //                                  // control which fields get highlighted
    //                                  // with the hl.fl result
    // (3) {facet_counts { facet_fields
    //                             // Contain counts for each legal section
    //                             // to filter on a section, 
    $decodedResults = json_decode($respJson);
    // Show full catch_line with highlighted 
    // text
    $docs = $decodedResults->response->docs;
    var_dump($respJson);
    $highlights = $decodedResults->highlighting;
    $resultNumber = 0;
    echo "\n";
    echo "-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-\n" ;
    echo "      SEARCH  RESULTS \n" ;
    echo "-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-\n" ;
    foreach ($docs as $doc) {
        $resultId = $doc->id;
        $textHl = $highlights->$resultId->text[0];
        echo "Result #" . $resultNumber ."\n";
        echo "__ $doc->catch_line __\n";
        echo "HIGHLIGHTED SNIPPET: $textHl\n";
        echo "-------------------------------------------\n";
        ++$resultNumber;
    }

    // Show faceting options
    echo "STRUCTURE FACETS, filter on these be rerunning script\n";
    echo "with --structfilter=\"value\"\n";
    $facet_fields = $decodedResults->facet_counts->facet_fields->structure_facet_hierarchical;
    $currFacetPair = 0;
    for ($currFacetPair = 0; $currFacetPair < count($facet_fields); $currFacetPair += 2) {
        $count = $facet_fields[$currFacetPair + 1];
        $currIter = $currFacetPair / 2;
        echo "--" . $facet_fields[$currFacetPair] . "($count)\n";

    }
}

// Parse through command line options
$longopts = array("solrUrl:", "query:", "pageNo", "structfilter:", "dict::", "facetdepth:");
$opts = getopt("", $longopts);
if (!in_array('solrUrl', array_keys($opts))) {
    echo "Usage:\n";
    echo "php search.php \\\n";
    echo "    --solrUrl='http://localhost:8983/solr/statedecoded/search' \\\n";
    echo "   [--query=\"no child left behind\" \\\n";
    echo "    --pageNo=2 \\ \n";
    echo "    --structfilter=\"Health/Disease Prevention and Control\" \\ \n";
    echo "    --facetdepth=2 \\ \n";
    echo "    --dict] \n";
    echo "Returns 10 search results for the specified query\n";
    echo "If pageNo is specified goes to that page of the \n";
    echo "results. IE pageNo == 0, first 3, pageNo ==1 next 3\n";
    echo "Specify --dict to search dictionary items\n";
    echo "Query should be parsable by edismax query parser\n";
    echo "";
    echo "ie: http://wiki.apache.org/solr/ExtendedDisMax";
    echo "Default query is *:* and default pageNo is 0";
    die();
}

$solrUrl = $opts['solrUrl'];
$query = '*:*';
$pageNo = 0;
$facetdepth = 1;

if (in_array('query', array_keys($opts))) {
    $query = $opts['query'];
}
if (in_array('pageNo', array_keys($opts))) {
    $pageNo = $opts['pageNo'];
}
$dict = FALSE;
$structfilters = array();
if (in_array('dict', array_keys($opts))) {
    $dict = TRUE;
}
else { // cause structure only applies to searching laws
    if (in_array("structfilter", array_keys($opts))) {
        $sfOption = $opts["structfilter"];
        $structfilters = $opts["structfilter"];
        if (is_string($structfilters)) {
            $structfilters = array($structfilters);
        }
    }
}

if (in_array('facetdepth', array_keys($opts))) {
    $facetdepth = $opts['facetdepth'];
}

print_r($facetdepth);

$respJson = searchStateDecoded($query, $solrUrl, $pageNo, $dict, $structfilters, $facetdepth);

if (!$dict) {
    lawSearchResultsCommandLineDisplay($respJson);
}
else {
    echo $respJson;
}


?>
