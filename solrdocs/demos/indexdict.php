<?php
require_once('postreq.php');
require_once('getreq.php');

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
$url = "http://localhost:8983/solr/statedecoded/update";
$contentType = "Content-Type: application/json; charset=US-ASCII";

# Load the dictionary Json
#
# One concern about this example, in the case
# of a dictionary json file thats very big, this
# might not fit into memory.
$dictFile = file_get_contents($argv[1]);
$dictJson = json_decode($dictFile);


# Create a unique id for an item in the dictionary 
# -- currently each item is not tagged with a 
# unique id. We need to make sure we give each
# dictionary item a unique id for certain Solr
# features such as highlighting 
function createDictId($filename, $counter) {
    return "d_$filename" . "_$counter";
}



$dict_id = 0;
foreach ($dictJson as $dictObj) {
    $dictObj->id = createDictId($argv[1], $dict_id);
    # unsed some unsupported fields
    unset($dictObj->scope);
    unset($dictObj->section);
    $dict_id++;
}

$encoded = json_encode($dictJson);

# json_encode again and Post
$postReq = new PostRequest($url);
$noParams = array();
$postReq->postData($noParams, $encoded, $contentType);

$req = new GetRequest($url);
$req->execute(array('commit' => 'true'));

?>
