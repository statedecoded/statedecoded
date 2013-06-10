<?php

require_once('getreq.php');

// More Like This passes a single id to the singledoc
// request handler and retrieves three documents that are
// more like this
//
// Little customization needs to be done on mlt, for 
// more information see:
// http://wiki.apache.org/solr/MoreLikeThis
//
function getMoreLikeThis($solrUrl, $documentId) {

    // A simple Get Request with the id field set to the 
    // id of the document we want to "more like this"
    // ie: http://localhost:8983/solr/statedecoded/singledoc?id=l_1234
    $params = array('id' => "$documentId",
                    'wt' => 'json');
    $mltReq = new GetRequest($solrUrl);
    $respJson = $mltReq->get($params);

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


function displayMoreLikeThis($respJson) {
    $mltDecoded = json_decode($respJson);

    var_dump($mltDecoded->moreLikeThis);
}



$longopts = array("solrUrl:", "id:");
$opts = getopt("", $longopts);
if (!in_array('solrUrl', array_keys($opts)) or
    !in_array('id', array_keys($opts))) {
    echo "Usage:\n";
    echo "php mlt.php \\\n";
    echo "    --solrUrl='http://localhost:8983/solr/statedecoded/singledoc' \\\n";
    echo "    --id=l_17533 \\\n";
    echo " Returns more documents like the document specified by \n";
    echo " the id argument\n";
    die();
}

$solrUrl = $opts['solrUrl'];
$id = $opts['id'];
$respJson = getMoreLikeThis($solrUrl, $id);
displayMoreLikeThis($respJson);
?>
