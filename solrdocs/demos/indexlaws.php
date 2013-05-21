<?php


# The code here indexes each XML document one at a time
# The update request handler is standard, and not configured
# specially for state decoded
#
# The update request handler in use in the 
$url = "http://localhost:8983/solr/statedecoded/update";
require_once('postreq.php');
require_once('getreq.php');

# First we POST to statedecoded/update endpoint and then
# we commit the changes
$postFilesReq = new PostFilesRequest($url);
$queryParams = array('tr' => 'stateDecodedXml.xsl');

$postFilesReq->executeGlob($queryParams, 'lawsamples/*.xml');

# This commit makes everything searchable
$req = new GetRequest($url);
$req->execute(array('commit' => 'true'));

?>
