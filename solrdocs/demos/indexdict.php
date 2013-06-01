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



# We can actually just post the json object directly
# but we actually need to go through and give the 
# objects an id and remove unsupported fields
$dict_id = 0;
foreach ($dictJson as $dictObj) {
    $dictObj->id = createDictId($argv[1], $dict_id);
    # unsed some unsupported fields
    unset($dictObj->scope);
    unset($dictObj->section);
    $dict_id++;
}


# json_encode again and Post
$encoded = json_encode($dictJson);
$postReq = new PostRequest($url);
$queryParams = array("wt" => "json");
$response = $postReq->postData($queryParams, $encoded, $contentType);
checkForSolrError($response);


# Once files are posted, they are not searchable
# until a commit is done.
#
# For some applications where there's a lot of real time 
# updates to documents that need to be searched in real time,
# it may be important to commit often. For state decoded,
# this is less important as all data is imported at once
# with a big batch update.
$req = new GetRequest($url);
$req->execute(array('commit' => 'true', 'wt' => 'json'));

?>
