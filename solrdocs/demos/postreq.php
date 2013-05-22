<?php

require_once('httputils.php');

class PostFilesRequest {
    private $urlEndpoint;

    function __construct($urlEndpoint) {
        $this->urlEndpoint = $urlEndpoint;
    }

    
    function fullUrl($getParams) {
        return $this->urlEndpoint . "?" . httputils\buildQueryString($getParams);
    }

    // Takes a glob pattern and posts 
    // those files up
    function executeGlob($getParams, $globPattern, $contentType) {
        $files = array();
        foreach (glob($globPattern) as $filename) {
            $files[] = $filename;
            $this->execute($getParams, $filename, $contentType);
        }
    }


    // Takes get parameters + an array of file paths 
    // to post. May also specify a single file
    function execute($getParams, $files, $contentType) {
        // Modified from: 
        // http://stackoverflow.com/a/3892820/8123

        $data="";
        if (!is_array($files)) {
            //Convert to array
            $files=array($files);
        }
        $n=sizeof($files);
        print_r($files);
        for ($i=0;$i<$n;$i++) {
            $data .= file_get_contents($files[$i]);
        }

        $contentType = array($contentType);

        $ch = curl_init();
        $url = $this->fullUrl($getParams);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $contentType); 
        $response = curl_exec($ch);
        return $response;
    }
}

// Test only if run directly from CLI
if (basename($argv[0]) == basename(__FILE__)) {
    $queryParams = array('tr' => 'stateDecodedXml.xsl');
    $lawSearcher = new PostFilesRequest("http://localhost:8983/solr/statedecoded/update/xslt");
    $file = "lawsamples/31-45.xml";
    print $lawSearcher->execute($queryParams, $file, "Content-Type: application/xml; charset=US-ASCII");
}

?>
