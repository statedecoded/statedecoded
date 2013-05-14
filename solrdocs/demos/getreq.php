<?php


class GetRequest {

    private $urlEndpoint;

    function __construct($urlEndpoint) {
        $this->urlEndpoint = $urlEndpoint;
    }

    static function buildQueryString($getParams) {
        $queryString = "";
        print_r($getParams);
        foreach ($getParams as $key => $value) {
            echo "HERE\n";
            $queryString .= "&" . urlencode($key) . "=" . urlencode($value);
            echo $queryString . "\n";
        }
        return $queryString;
    }

    function fullUrl($getParams) {
        return $this->urlEndpoint . "?" . $this->buildQueryString($getParams);
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
            echo "Error Getting $url";
        }
        else {
            print $content;
        }
    }

    function executeCurl($url) {
        $c = curl_init();
        $fullUrl = $url; 
        $curl_setopt($c, CURLOPT_URL, $fullUrl); 

        $results = curl_exec($c); 
        if (curl_errno($c) > 0) {
            echo "Curl Error: " + curl_error($c);
            return null;
        }
        else {
            return json_decode($results, true);
        }

    }

}

// Test
if (basename($argv[0]) == basename(__FILE__)) {
    $getParams = array('a' => 'hello', 'b'=>'world');
    print GetRequest::buildQueryString($getParams);

    $lawSearcher = new GetRequest("http://localhost:8983/solr/statedecoded/law");
    $searchRes = $lawSearcher->execute( array('q'=>'no child left behind') );

    print_r($searchRes);
}

?>
