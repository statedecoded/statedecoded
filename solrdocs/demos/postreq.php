<?php

require_once('httputils.php');

class PostFilesRequest {
    private $urlEndpoint;
    private $ch; // curl handler

    function __construct($urlEndpoint) {
        $this->urlEndpoint = $urlEndpoint;
        $this->ch = curl_init(); # To reuse the HTTP connection
    }

    
    function fullUrl($getParams) {
        return $this->urlEndpoint . "?" . httputils\buildQueryString($getParams);
    }

    // Takes a glob pattern and posts 
    // those files up
    function executeGlob($getParams, $globPattern, $contentType) {
        $files = array();
        $numFiles = 0;
        $url = $this->fullUrl($getParams);
        $contentType = array($contentType);
        curl_setopt($this->ch, CURLOPT_URL, $url);
        curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($this->ch, CURLOPT_POST, true);
        curl_setopt($this->ch, CURLOPT_HTTPHEADER, $contentType); 
        foreach (glob($globPattern, GLOB_NOSORT) as $filename) {
            curl_setopt($this->ch, CURLOPT_POSTFIELDS, file_get_contents($filename));
            curl_exec($this->ch); 
            echo "Posted $numFiles -- $filename           \r";
            ++$numFiles;
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
        for ($i=0;$i<$n;$i++) {
            $data .= file_get_contents($files[$i]); 
        }

        $contentType = array($contentType);

        $url = $this->fullUrl($getParams);
        curl_setopt($this->ch, CURLOPT_URL, $url);
        curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($this->ch, CURLOPT_POST, true);
        curl_setopt($this->ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($this->ch, CURLOPT_HTTPHEADER, $contentType); 
        $response = curl_exec($this->ch);
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
