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
        if (gettype($key) != "string" or gettype($value) != "string") {
            trigger_error("httputils\buildQueryString did not receive strings in its array");
        }
        $queryString .= urlencode($key) . "=" . urlencode($value) . "&";
    }
    $queryString = substr($queryString, 0, -1);
    return $queryString;
}




# Build an HTTP URL with a query string appended
# 
#
# Usage:
#   $baseUrl = "http://localhost:1234/hello/world"
#   $queryParams = array('user' => '12', 'lastname' => 'turnbull')
#   echo appendQueryString($url, $queryParams);
#
# Output:
#   http://localhost:1234/hello/world?user=12&lastname=>turnbull
#
function appendQueryString($baseUrl, $queryParams) {
    if (!is_array($queryParams) or count($queryParams) == 0) {
        return $baseUrl;
    }
    return $baseUrl . "?" . buildQueryString($queryParams);
}
 

?>