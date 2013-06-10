<?php

require_once('getreq.php');

// The single doc request handler takes a single document id
// and retreives search components that you'd likely want to
// display on a page displaying just that document.
//
// This includes more extensive highlighting, and More
// Like This:
//
// http://wiki.apache.org/solr/MoreLikeThis
//
function getSingleDoc($solrUrl, $documentId) {

    // A simple Get Request with the id field set to the 
    // id of the document we want to display
    // ie: http://localhost:8983/solr/statedecoded/singledoc?id=l_1234
    $params = array('id' => "$documentId",
                    'wt' => 'json');
    $singleDocReq = new GetRequest($solrUrl);
    $respJson = $singleDocReq->get($params);

    // Some error checking, did Solr return an error?
    //  Does it look like Solr is even up?
    $error = checkForSolrError($respJson);
    if ($error !== FALSE) {
        echo "Failure Executing More Like This:\n";
        echo $error;
        die();
    }
    else {
        return $respJson;
    }
}


$longopts = array("solrUrl:", "id:");
$opts = getopt("", $longopts);
if (!in_array('solrUrl', array_keys($opts)) or
    !in_array('id', array_keys($opts))) {
    echo "Usage:\n";
    echo "php singledoc.php \\\n";
    echo "    --solrUrl='http://localhost:8983/solr/statedecoded/singledoc' \\\n";
    echo "    --id=l_17533 \\\n";
    echo " Returns more documents like the document specified by \n";
    echo " the id argument\n";
    die();
}

$solrUrl = $opts['solrUrl'];
$id = $opts['id'];
$respJson = getSingleDoc($solrUrl, $id);
var_dump(json_decode($respJson));
?>
