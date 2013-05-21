<?php

$url = "http://localhost:8983/solr/statedecoded/update";
require_once('postreq.php');
require_once('getreq.php');


$postFilesReq = new PostFilesRequest($url);

$queryParams = array('tr' => 'stateDecodedXml.xsl');

$postFilesReq->executeGlob($queryParams, 'lawsamples/*.xml');

$req = new GetRequest($url);
$req->execute(array('commit' => 'true'));

?>
