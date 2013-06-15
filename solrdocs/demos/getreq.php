<?php

require_once('httputils.php');
require_once('solrerrorcheck.php');

class GetRequest {

    private $urlEndpoint;

    function __construct($urlEndpoint) {
        $this->urlEndpoint = $urlEndpoint;
    }

    function fullUrl($getParams) {
        return $this->urlEndpoint . "?" . httputils\buildQueryString($getParams);
    }

    function get($getParams) {
        $url = $this->fullUrl($getParams);
        echo "GET $url\n";
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


?>
