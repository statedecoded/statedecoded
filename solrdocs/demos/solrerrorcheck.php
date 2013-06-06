<?php

function checkForSolrError($solrRespJson) {
    $solrResp = json_decode($solrRespJson);
    if (isset($solrResp->error)) {
        trigger_error("Solr has reported an error: " . $solrResp->error->msg);
    }
}


?>
