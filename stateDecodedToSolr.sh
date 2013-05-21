#!/bin/bash

# Pull laws into a tab delimited file


echo "Ingesting Laws..."


echo '
SELECT "law" AS type, CONCAT("l_", lawstruct.id) AS id, lawstruct.section AS section, lawstruct.edition_id AS edition, lawstruct.catch_line AS catch_line, lawstruct.repealed AS repealed, lawstruct.text AS text, lawstruct.structure_full AS structure FROM
(SELECT l.section, l.id, l.catch_line, l.edition_id, l.repealed, l.text, l.structure_id, s1.name AS s1_name, s1.id AS     s1_id, s1.parent_id AS s1_parent_id, s2.name AS s2_name, s2.id AS s2_id, s2.parent_id AS s2_parent_id, s3.name AS s3_name, s3.id AS s3_id, s3.parent_id AS s3_parent_id, CONCAT_WS(  "/", s3.name, s2.name, s1.name ) AS structure_full
FROM  `laws` AS l
LEFT JOIN structure AS s1 ON s1.id = structure_id
LEFT JOIN structure AS s2 ON s2.id = s1.parent_id
LEFT JOIN structure AS s3 ON s3.id = s2.parent_id) lawstruct' | mysql -u$1 -p$2 vadecoded > /tmp/laws.tsv
curl 'http://localhost:8983/solr/statedecoded/update/csv?commit=true&separator=%09&escape=\&stream.file=/tmp/laws.tsv'


echo "Ingesting Dictionary Terms..."
echo 'SELECT "dict" AS type, CONCAT("d_", id) AS id ,term,definition FROM `dictionary`' | mysql -u$1 -p$2 vadecoded > /tmp/law_dict.tsv
curl 'http://localhost:8983/solr/statedecoded/update/csv?commit=true&separator=%09&escape=\&stream.file=/tmp/law_dict.tsv'
