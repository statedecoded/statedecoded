<?php

function checkForSolrError($solrRespJson) {
    $solrResp = json_decode($solrRespJson);
    if (isset($solrResp->error)) {
       return $solrResp->error->msg;
    }
    return FALSE;
}


?>
