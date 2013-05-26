<?php namespace httputils;

# Build an HTTP Query String from
# the passed in associative array
#
# Usage:
#   $params = array('user' => '12', 'lastname' => 'turnbull')
#   echo buildQueryString($params);
#
# Output:
#   user=12&lastname=>turnbull
#
function buildQueryString($params) {
    $queryString = "";
    foreach ($params as $key => $value) {
        if (gettype($key) != "string" or gettype($value) != "string") {
            trigger_error("httputils\buildQueryString did not receive strings in its array");
        }
        $queryString .= urlencode($key) . "=" . urlencode($value) . "&";
    }
    $queryString = substr($queryString, 0, -1);
    return $queryString;
}
 

?>
