<?php

require_once('httputils.php');


// PostRequest
// Post data to somewhere 
class PostRequest {
    protected $urlEndpoint;
    protected $ch; // curl handler
 
    function __construct($urlEndpoint) {
        $this->urlEndpoint = $urlEndpoint;
        $this->ch = curl_init(); # To reuse the HTTP connection
    }
    
    function fullUrl($queryParams) {
        return httputils\appendQueryString($this->urlEndpoint, $queryParams); 
    }

    function handleResponse($response) {
        if ($response === FALSE) {
            echo "POST FAILED!!";
            trigger_error(curl_error($this->ch));
            return FALSE;
        }
        return $response;
    }

    function postData($queryParams, $data, $contentType) {
        $contentType = array($contentType);
        $url = $this->fullUrl($queryParams);
        curl_setopt($this->ch, CURLOPT_URL, $url);
        curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($this->ch, CURLOPT_POST, true);
        curl_setopt($this->ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($this->ch, CURLOPT_HTTPHEADER, $contentType); 
        $response = $this->handleResponse(curl_exec($this->ch));
        return $response;
    }
}


// Post a series of files directly
// on one multipart request
//
//
class PostFilesRequest extends PostRequest {

    function __construct($urlEndpoint) {
        parent::__construct($urlEndpoint);
    }

    // Return either the HTTP response text or
    // FALSE on failure
    function postFiles($queryParams, $files, $contentType) {
        if (!is_array($files)) {
            //Convert to array
            $files=array($files);
        }
        $contentType = array("Content-Type: multipart/form; charset=US-ASCII");
        $numFiles = 0;
        $url = $this->fullUrl($queryParams);
        curl_setopt($this->ch, CURLOPT_URL, $url);
        curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($this->ch, CURLOPT_HTTPHEADER, $contentType);
        $params = array();
        foreach ($files as $key=>$filename) {
            $params[$filename] = '@' . realpath($filename) . ";type=application/xml";
            ++$numFiles;
        }
        curl_setopt($this->ch, CURLOPT_POSTFIELDS, $params);
        $response = $this->handleResponse(curl_exec($this->ch));
        return $response;
    }

}

// Test only if run directly from CLI
if (basename($argv[0]) == basename(__FILE__)) {
    $queryParams = array('tr' => 'stateDecodedXml.xsl');
    $lawSearcher = new PostFilesRequest("http://localhost:8983/solr/statedecoded/update/xslt");
    $file = "lawsamples/31-45.xml";
    print $lawSearcher->postFiles($queryParams, $file, "Content-Type: application/xml; charset=US-ASCII");
}

?>
