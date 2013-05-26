<?php
require_once('postreq.php');
require_once('getreq.php');

# ***************************************************************
# The code here indexes each law XML document one at a time
#  
# Solr comes prepackaged with several update request processors
# that take updates/inserts/deletes to documents. We're using
# the XSLT update request processor.
#
# More on update request processors can be found here:
#   http://wiki.apache.org/solr/UpdateRequestProcessor
#
#
# How this works is Solr knows that the content is XML from
# the content type passed in. Solr takes the XML file and 
# refers to the xslt specified in the tr parameter. Solr then
# executes the xslt on the passed in xml and the xml should 
# get translated into Solr Update XML -- the standard Solr XML
# format for specifying a document.
#
$url = "http://localhost:8983/solr/statedecoded/update";
$contentType = "Content-Type: application/xml; charset=US-ASCII";

# First we POST to statedecoded/update endpoint and then
# we commit the changes
$postFilesReq = new PostFilesRequest($url);
$queryParams = array('tr' => 'stateDecodedXml.xsl');

# A note on performance, this takes about 60 seconds to index all 
# of Virginias laws. This could be improved dramatically, but 
# the effort is likely not worth it. Areas of improvement include
#  - Each law is sent in its own blocking POST request, if 
#    either each law could be posted asynchronously OR if all
#    the laws could be sent in one POST request, the time could
#    be dramatically reduced
#$postFilesReq->executeGlob($queryParams, 'lawsamples/*.xml', $contentType);
$postFilesReq->executeGlob($queryParams, $argv[1], $contentType);

# Once files are posted, they are not searchable
# until a commit is done.
#
# For some applications where there's a lot of real time 
# updates to documents that need to be searched in real time,
# it may be important to commit often. For state decoded,
# this is less important as all data is imported at once
# with a big batch update.
$req = new GetRequest($url);
$req->execute(array('commit' => 'true'));

?>
