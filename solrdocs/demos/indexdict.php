<?php
require_once('postreq.php');
require_once('getreq.php');
require_once('solrerrorcheck.php');

# ***************************************************************
# The code here indexes each dictionary json object one at a time
#
# By specifying content-type Json, we are indicating to Solr to
# index this as Json. Solr is flexible in accepting Json, more 
# information can be found here:
#
# http://wiki.apache.org/solr/UpdateJSON
#
# More on update request processors can be found here:
#   http://wiki.apache.org/solr/UpdateRequestProcessor

# Create a unique id for an item in the dictionary 
# -- currently each item is not tagged with a 
# unique id. We need to make sure we give each
# dictionary item a unique id for certain Solr
# features such as highlighting 
function createDictId($filename, $counter) {
    $baseName = basename($filename);
    return "d_$baseName" . "_$counter";
}


#
# Index the dictionary json file to Solr specified at url
#
function indexDict($solrUrl, $dictJsonFileName) {
        
    $contentType = "Content-Type: application/json; charset=US-ASCII";

    # Load the dictionary Json
    #
    # One concern about this example, in the case
    # of a dictionary json file thats very big, this
    # might not fit into memory.
    $dictFile = file_get_contents($dictJsonFileName);
    $dictJson = json_decode($dictFile);



    # We can actually just post the json object directly
    # but we actually need to go through and give the 
    # objects an id and remove unsupported fields
    $dict_id = 0;
    foreach ($dictJson as $dictObj) {
        $dictObj->id = createDictId($dictJsonFileName, $dict_id);
        $dictObj->type = 'dict'; 
        # unsed some unsupported fields
        unset($dictObj->scope);
        unset($dictObj->section);
        $dict_id++;
        echo "Transforming dictionary item $dict_id from $dictJsonFileName\r";
    }
    echo "\nPosting transformed contents of $dictJsonFileName to $solrUrl\n";


    # json_encode again and Post
    $encoded = json_encode($dictJson);
    $postReq = new PostRequest($solrUrl);
    $queryParams = array("wt" => "json");  # ask for solr to return json with query param wt=json
    $response = $postReq->postData($queryParams, $encoded, $contentType);
    $error = checkForSolrError($response);
    if ($error !== FALSE) {
        echo "SOLR REPORTED Error: \n";
        echo "$error\n";
        return;
    } else {
        echo "SUCCESS! Dictionary terms posted to Solr!\n";
    }


    # Once files are posted, they are not searchable
    # until a commit is done.
    #
    # For some applications where there's a lot of real time 
    # updates to documents that need to be searched in real time,
    # it may be important to commit often. For state decoded,
    # this is less important as all data is imported at once
    # with a big batch update.
    echo "Triggering a commit of dictionary terms...\n";
    // get req to solrUrl/?commit=true&wt=json
    $req = new GetRequest($solrUrl);
    $response = $req->get(array('commit' => 'true', 'wt' => 'json'));
    $error = checkForSolrError($response);
    var_dump($error);
    if ($error !== FALSE) {
        echo "SOLR REPORTED Error: \n";
        echo "$error\n";
        return;
    } else {
        echo "SUCCESS! Dictionary terms are commited and searchable!\n";
    }


}

$longopts = array("solrUrl:", "dictFile:");
$opts = getopt("", $longopts);
$solrUrl = $dictFile = NULL;


if (!in_array('solrUrl', array_keys($opts)) ||
    !in_array('dictFile', array_keys($opts))) {
    echo "Usage --\n";
    echo "Please specify a path to State Decoded Solr and\n";
    echo "a json file containing state decoded dictionary\n";
    echo "terms\n";
    echo "Example --\n";
    echo "php indexdict.php \\\n";
    echo "     --solrUrl=http://localhost:8983/solr/statedecoded/update \\\n";
    echo "     --dictFile=/home/doug/dictionary.json\\\n";
    echo "\n";
    die();
    }

$solrUrl = $opts['solrUrl'];
$dictFile = $opts['dictFile'];
indexDict($solrUrl, $dictFile);





?>
