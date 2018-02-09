#!/usr/bin/php
<?php

require_once 'config.php';
require_once 'vendor/autoload.php';

use Httpful\Request;
use Elasticsearch\ClientBuilder;

$client = ClientBuilder::create()
            ->setHosts(['http://'.ES_HOST.':'.ES_PORT])
            ->build();
            
$search_params = [
    'index' => ES_INDEX,
    'type' => ES_TYPE,
    'size' => ES_BATCH_SIZE, // Per batch
    'sort' => ['_doc'],
    'scroll' => ES_SCROLL_TIME,
    '_source_include' => ['id', 'abstract'],
    'body' => [
    /*    
      'query' => [
        'ids' => [
          'values' => ['S0212-16112010000900009'] // For testing
        ]
      ]
      */
    ]
];
         
// Get, annotate and index abtract in English


try {
  $response = $client->search($search_params);
  while (count($response['hits']['hits']) > 0) {
    foreach ($response['hits']['hits'] as $document) {
      $document_id = $document['_id'];
      if (isset($document['_source']['abstract']['en'])) {
        $abstract = $document['_source']['abstract']['en'];
        if ($annotated_abstract = annotateTerms($abstract)) {
          $response_index = indexAnnotatedAbstract($annotated_abstract, $document_id);
          if (isset($response_index['result'])) {
            echo "Elasticsearch message: Annotation of document $document_id - " . $response_index['result'] . "\n";
          }            
        }
      }
    }
    $scroll_id = $response['_scroll_id'];
    $response = $client->scroll([
          'scroll_id' => $scroll_id,
          'scroll' => ES_SCROLL_TIME
      ]
    );    
  }
} catch (Elasticsearch\Common\Exceptions\Missing404Exception $e) {
    $error = json_decode($e->getMessage());  
    printf("The elasticsearch search request failed with code: %s. Reason: \"%s\".\n", $e->getCode(), isset($error->error->reason) ? $error->error->reason : '');
} catch (Elasticsearch\Common\Exceptions\BadRequest400Exception $e) {
    $error = json_decode($e->getMessage());    
    printf("The elasticsearch search request failed with code: %s. Reason: \"%s\".\n", $e->getCode(), isset($error->error->reason) ? $error->error->reason : '');  
} 

// Annotate terms by means of BECAS' API
function annotateTerms($abstract) {
  $emails = explode(',', BECAS_EMAIL);
  $tools = explode(',', BECAS_TOOL);  
  $pos = mt_rand(0,count($emails)-1);
  $annotated_sentences = array();
  $parameters = array('email' => $emails[$pos], 'tool' => $tools[$pos]);
  try {    
    $resp_becas = Request::post(BECAS_URL . '?' . http_build_query($parameters), '{"groups": {"DISO": true}, "format": "json", "text": "'.$abstract.'"}')
      ->addHeaders(array('Content-Type' => 'application/json'))
      ->send();
    if ($resp_becas->hasBody()) {
      if (!empty($resp_becas->body->entities)) {
        $annotated_sentences = $resp_becas->body->entities; // We now get the first one.
      }
      else {
        $annotated_sentences = $resp_becas->body;       
      }     
    }
  }
  catch (Httpful\Exception\ConnectionErrorException $e) {
    printf("The connection to the BECAS annotation API failed with code: %s.\n", $e->getCode());        
  }    
  return $annotated_sentences;
}

// Index annotated abstract
function indexAnnotatedAbstract($annotated_abstract, $document_id) {
  global $client;
  $index_params = array(
      'index' => ES_INDEX_ANNOTATIONS,
      'type' => ES_TYPE_ANNOTATIONS,
      'id' => $document_id,  
      'body' => array('doc' => ['sentences' => $annotated_abstract], 'doc_as_upsert' => true)
  );    
  return $client->update($index_params);
}



            