<?php
require_once('postreq.php');
require_once('getreq.php');
require_once('solrerrorcheck.php');

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
function indexLaws($pathOrGlob, $solrUrl) {
    $url = "http://localhost:8983/solr/statedecoded/update";
    $contentType = "Content-Type: application/xml; charset=US-ASCII";

    if (is_dir($pathOrGlob)) {
        echo "Please specify a GLOB for now, IE /path/to/xml/*xml\n";
    }

    # First we POST to statedecoded/update endpoint and then
    # we commit the changes
    $postFilesReq = new PostFilesRequest($url);
    $queryParams = array('wt' => 'json', 
                         'tr' => 'stateDecodedXml.xsl');

    # Post the specified files to Solr
    $batchSize = 10000;
    $files = glob($pathOrGlob);
    for ($i = 0; $i<count($files); $i+=$batchSize) {
        $slice = array_slice($files, $i, $batchSize);
        echo "Posting $batchSize docs starting with $slice[0] \r";
        $resp = $postFilesReq->postFiles($queryParams, $slice, $contentType);
        checkForSolrError($resp);
    }
    $resp = $postFilesReq->executeGlob($queryParams, $pathOrGlob, $contentType);

    # Note -- Waldo, I'm expecting you'll have validated this XML before
    # getting here

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
}

$longopts = array("solrUrl:", "pathToLaws:");

$opts = getopt("", $longopts);
var_dump($opts);

$url = $opts['solrUrl'];
$lawPath = $opts['pathToLaws'];

if ($url === NULL OR $lawPath === NULL) {
    echo "Usage --\n";
    echo "Please specify a path to State Decoded Solr and\n";
    echo "a location locally where state decoded laws can\n";
    echo "be found for ingestion.\n";
    echo "Example --\n";
    echo "php indexlaws.php \\\n";
    echo "     --solrURl=http://localhost:8983/solr/update \\\n";
    echo "     --pathToLaws=/path/to/laws/*.xml\n";
    die();
}
indexLaws($lawPath, $url);


?>
