<?php

require_once 'config.php';
require_once 'vendor/autoload.php';

use Elasticsearch\ClientBuilder;

if (!empty($_GET['id']) && !empty($_GET['lang'])) {
  $document_id = $_GET['id'];
  $lang = $_GET['lang'];
  if ($lang == 'es') {
    $index = ES_INDEX_ANNOTATIONS_SPANISH;
    $type = ES_TYPE_ANNOTATIONS_SPANISH;
  }
  else {
    $index = ES_INDEX_ANNOTATIONS_ENGLISH;
    $type = ES_TYPE_ANNOTATIONS_ENGLISH;    
  }
  $hosts = ['http://'.ES_HOST.':'.ES_PORT];
  $client = ClientBuilder::create()
              ->setHosts($hosts)
              ->build();
  $get_params = [
      'index' => $index,
      'type' => $type,
      'id' => "$document_id"
  ];  
  try {
    $response = $client->get($get_params);
    $sentences = isset($response['_source']['sentences']) ? $response['_source']['sentences'] : '';
    header('Content-disposition: attachment; filename='.$document_id.'.json');    
    header('Content-type: application/json');    
    echo json_encode($sentences, JSON_UNESCAPED_UNICODE);
    echo "\n";
  } catch (Elasticsearch\Common\Exceptions\Missing404Exception $e) {
    $error = json_decode($e->getMessage());  
    printf("<!-- The elasticsearch search request failed with code: %s. Reason: \"%s\".-->\n", $e->getCode(), isset($error->error->reason) ? $error->error->reason : '');
  } catch (Elasticsearch\Common\Exceptions\BadRequest400Exception $e) {
    $error = json_decode($e->getMessage());    
    printf("<!-- The elasticsearch search request failed with code: %s. Reason: \"%s\".-->\n", $e->getCode(), isset($error->error->reason) ? $error->error->reason : '');  
  }     
}



          