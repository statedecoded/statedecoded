<?php

function checkForSolrError($solrRespJson) {
    if (!is_string($solrRespJson)) {
        return "Error connecting to Solr, no response detected\n";
    }
    $solrResp = json_decode($solrRespJson);
    if ($solrResp == NULL) {
        return "Error: Solr response does not appear to be parsable json\n";
    }
    if (isset($solrResp->error)) {
       return $solrResp->error->msg;
    }
    return FALSE;
}


?>
