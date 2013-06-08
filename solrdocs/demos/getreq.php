<?php

require_once('httputils.php');

class GetRequest {

    private $urlEndpoint;

    function __construct($urlEndpoint) {
        $this->urlEndpoint = $urlEndpoint;
    }

    function fullUrl($getParams) {
        return $this->urlEndpoint . "?" . httputils\buildQueryString($getParams);
    }

    function execute($getParams) {
        $url = $this->fullUrl($getParams);
        print "Getting $url";
        if(is_callable('curl_init')) {
            return $this->executeCurl($url);
        }    
        else {
            return $this->executeFGetContents($url);
        }
    }

    function executeFGetContents($url) {
        $content = file_get_contents($url);
        if ($content === FALSE) {
            return FALSE;
        }
        else {
            return $content;
        }
    }

    function executeCurl($url) {
        $c = curl_init();
        $fullUrl = $url; 
        curl_setopt($c, CURLOPT_URL, $fullUrl); 
        curl_setopt($c, CURLOPT_RETURNTRANSFER, true);

        $results = curl_exec($c); 
        if (curl_errno($c) > 0) {
            trigger_error("Curl Error: " . curl_error($c));
            return FALSE;
        }
        else {
            return $results;
        }

    }

}

// Test only if run directly from CLI
if (basename($argv[0]) == basename(__FILE__)) {
    $getParams = array('a' => 'hello', 'b'=>'world');
    $lawSearcher = new GetRequest("http://localhost:8983/solr/statedecoded/law");
    $searchRes = $lawSearcher->execute( array('q'=>'no child left behind') );

    print_r($searchRes);
}

?>
