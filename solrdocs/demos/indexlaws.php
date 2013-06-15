<?php
require_once('postreq.php');
require_once('getreq.php');
require_once('solrerrorcheck.php');

# Utility function
# Check to see if string ends with
# Adapted from http://stackoverflow.com/a/834355
function stringEndsWith($needle, $haystack) {
    $len = strlen($needle);
    if ($len == 0) {
        return TRUE;
    }
    return (substr($haystack, -$len) === $needle);
}

# Retreive arary of XML Files from the specified
# Path or GLOB input from the command line
function getXmlFilesFromPathOrGlob($pathOrGlob) {
    $files = array();

    if (is_file($pathOrGlob) and stringEndsWith(".xml", $pathOrGlob)) {
        return array($pathOrGlob);
    }
    else if (is_dir($pathOrGlob)) {
        // code adapted from
        // http://www.php.net/manual/en/class.recursivedirectoryiterator.php
        $objects = new RecursiveIteratorIterator(
                        new RecursiveDirectoryIterator($pathOrGlob),
                        RecursiveIteratorIterator::SELF_FIRST);
        foreach($objects as $name => $unused) {
            if (stringEndsWith(".xml", $name)) {
                $files[] = $name;
            }
        }
    }
    else if (stringEndsWith("*.xml", $pathOrGlob)) {
        $files = glob($pathOrGlob);
    }
    else {
        return FALSE;
    }
    return $files;
 
}

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
function indexLaws($pathOrGlob, $solrUrl, $batchSize=10000) {
    $url = "http://localhost:8983/solr/statedecoded/update";
    $contentType = "Content-Type: application/xml; charset=US-ASCII";

    $files = getXmlFilesFromPathOrGlob($pathOrGlob);
    if ($files === FALSE) {
        echo "Cannot recognize $pathOrGlob as either a valid XML glob\n";
        echo "pattern (ie /path/to/xml/*.xml) nor can I recognize it \n";
        echo "as a file (/path/to/law.xml) or directory (/path/to/xml)\n";
        die();
    }


    # First we POST to statedecoded/update endpoint and then
    # we commit the changes
    $postFilesReq = new PostFilesRequest($url);
    $queryParams = array('wt' => 'json', 
                         'tr' => 'stateDecodedXml.xsl');

    # Post the specified files to Solr
    $numFiles = count($files);
    for ($i = 0; $i<$numFiles; $i+=$batchSize) {
        $slice = array_slice($files, $i, $batchSize);
        echo "Posting $i($batchSize)/$numFiles docs starting with $slice[0] \r";
        $resp = $postFilesReq->postFiles($queryParams, $slice, $contentType);
        $error = checkForSolrError($resp);
        if ($error != FALSE) {
            echo "ERROR!\n";
            echo "Solr Error while processing batch $slice[0]\n";
            echo "$error\n";
            echo "TO GET ERRING XML -- RERUN with --batchSize=1\n";
        }
    }

    # Once files are posted, they are not searchable
    # until a commit is done.
    #
    # For some applications where there's a lot of real time 
    # updates to documents that need to be searched in real time,
    # it may be important to commit often. For state decoded,
    # this is less important as all data is imported at once
    # with a big batch update.
    $req = new GetRequest($url);
    $req->get(array('commit' => 'true'));
}

# Parse command line options (see printout below for more info)
$longopts = array("solrUrl:", "pathToLaws:", "batchSize:");
$opts = getopt("", $longopts);
$url = $lawPath = NULL;
$batchSize = 10000;
if (in_array('batchSize', array_keys($opts))) {
    $batchSize = $opts['batchSize'];
}
if (!in_array('solrUrl', array_keys($opts)) ||
    !in_array('pathToLaws', array_keys($opts))) {
    echo "Usage --\n";
    echo "Please specify a path to State Decoded Solr and\n";
    echo "a location locally where state decoded laws can\n";
    echo "be found for ingestion.\n";
    echo "Example --\n";
    echo "php indexlaws.php \\\n";
    echo "     --solrUrl=http://localhost:8983/solr/statedecoded/update \\\n";
    echo "     --pathToLaws=/path/to/laws/*.xml\\\n";
    echo "     [--batchSize=1000]\n";
    echo "\n";
    echo "This script will then treat all XML files found at\n";
    echo "pathToLaws as State Decoded XML files and attempt\n";
    echo "to index them\n";
    die();
}
$url = $opts['solrUrl'];
$lawPath = $opts['pathToLaws'];
indexLaws($lawPath, $url, $batchSize);


?>
