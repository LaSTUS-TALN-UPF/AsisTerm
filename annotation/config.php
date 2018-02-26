<?php

define('ABSTRACTS_DIR', '/mnt/vmdata/scipub/data/hacknlp2018/SciELO/SciELO_corpus_parallel/dublin_core');
define('ES_HOST', 'elastic-taln.s.upf.edu');
define('ES_PORT', '9200');
define('ES_BATCH_SIZE', 30);
define('ES_SCROLL_TIME', '5m');
define('ES_INDEX', 'scielo');
define('ES_TYPE', 'abstract');
define('ES_INDEX_UMLS', 'umls');
define('ES_TYPE_UMLS', 'concept');
define('ES_INDEX_ANNOTATIONS_ENGLISH', 'annotated');
define('ES_TYPE_ANNOTATIONS_ENGLISH', 'annotation');
define('ES_INDEX_ANNOTATIONS_SPANISH', 'annotated_spanish');  //TODO: Merge English and Spanish annotations in the same index.
define('ES_TYPE_ANNOTATIONS_SPANISH', 'annotation');
define('DB_DATABASE', 'umls');
define('DB_HOST', 'localhost');
define('DB_USER', '');
define('DB_PASS', '');
define('BECAS_URL', 'http://bioinformatics.ua.pt/becas/api/text/export');
define('BECAS_EMAIL', '');
define('BECAS_TOOL', '');
define('STANFORD_TAGGER_MODEL', '/homedtic/paccuosto/pablo/stanford/stanford-postagger-full-2017-06-09/models/spanish-ud.tagger');
define('STANFORD_TAGGER_JAR', '/homedtic/paccuosto/pablo/stanford/stanford-postagger-full-2017-06-09/stanford-postagger-3.8.0.jar');
define('REL_DISTANCE_CANDIDATE_TERMS', 0.05);
define('VERBOSE', FALSE);





