<?php

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
define('DB_HOST', 'scipub-taln.s.upf.edu');
define('DB_USER', '');
define('DB_PASS', '');
define('SHOW_NUM_SENTENCES_DEFINITION', 3);
define('SNOMED_LINK', 'http://browser.ihtsdotools.org/?perspective=full&conceptId1=CODE&edition=LANG-edition&release=v20171031&server=https://prod-browser-exten.ihtsdotools.org/api/snomed');
define('MESH_LINK', 'https://meshb.nlm.nih.gov/record/ui?ui=CODE');
define('MEDLINE_LINK', 'https://apps.nlm.nih.gov/medlineplus/services/mpconnect_service.cfm?mainSearchCriteria.v.cs=2.16.840.1.113883.6.96&mainSearchCriteria.v.c=CODE&informationRecipient.languageCode.c=LANG&knowledgeResponseType=application/json');




