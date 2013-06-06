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
class PostFilesRequest extends PostRequest {

    function __construct($urlEndpoint) {
        parent::__construct($urlEndpoint);
    }

    function execute($queryParams, $files, $contentType) {
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

    function executeInBatches($queryParams, $files, $contentType, $batchSize) {
        for ($i = 0; $i<count($files); $i+=$batchSize) {
            $slice = array_slice($files, $i, $batchSize);
            echo "Posting $batchSize docs starting with $slice[0] \n";
            $this->execute($queryParams, $slice, $contentType);
        }
    }


    // Takes a glob pattern and posts 
    // those files up
    function executeGlob($queryParams, $globPattern, $contentType) {
        $files = glob($globPattern);
        $batchSize = 10000;
        return $this->executeInBatches($queryParams, $files, $contentType, $batchSize);
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
