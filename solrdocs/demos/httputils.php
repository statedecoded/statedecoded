<?php namespace httputils;

# Build an HTTP Query String from
# the passed in associative array
#
# Usage:
#   $queryParams = array('user' => '12', 'lastname' => 'turnbull')
#   echo buildQueryString($queryParams);
#
# Output:
#   user=12&lastname=>turnbull
#
function buildQueryString($queryParams) {
    if (!is_array($queryParams) or count($queryParams) == 0) {
        return "";
    }
    
    $queryString = "";
    foreach ($queryParams as $key => $value) {
        if (gettype($key) != "string" or 
            (gettype($value) != "string" and gettype($value) != "array")) {
            trigger_error("httputils\buildQueryString did not receive strings in its array");
        }
        if (gettype($value) == "string") {
            $value = array($value);
        }

        foreach ($value as $val) {
            $queryString .= urlencode($key) . "=" . urlencode($val) . "&";
        }
    }
    $queryString = substr($queryString, 0, -1);
    return $queryString;
}




# Build an HTTP URL with a query string appended
# 
#
# Usage:
#   $baseUrl = "http://localhost:1234/hello/world"
#   $queryParams = array('user' => '12', 'lastname' => 'turnbull', 'hobbies'=array('fishing', 'coding'))
#   echo appendQueryString($url, $queryParams);
#
# Output:
#   http://localhost:1234/hello/world?user=12&lastname=>turnbull&hobbies=fishing&hobbies=coding
#
function appendQueryString($baseUrl, $queryParams) {
    if (!is_array($queryParams) or count($queryParams) == 0) {
        return $baseUrl;
    }
    return $baseUrl . "?" . buildQueryString($queryParams);
}

// Just used as a convenient sandbox for making sure this works
function tests() {
    $appendResult = appendQueryString("http://localhost:8983/solr",
        array("v1" => "5"));
    echo "$appendResult\n" ;
    $appendResult = appendQueryString("http://localhost:8983/solr",
        array("v1" => array("5", "6")));
    echo "$appendResult\n" ;
    $appendResult = appendQueryString("http://localhost:8983/solr",
        array("v1" => array("5", "6"), "v2"=>"bar"));
    echo "$appendResult\n" ;
}
 

if (basename($argv[0]) == basename(__FILE__)) {
    tests();
}



?>
