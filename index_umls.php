#!/usr/bin/php
<?php

require_once 'config.php';
require_once 'vendor/autoload.php';

use Elasticsearch\ClientBuilder;

$hosts = ['http://'.ES_HOST.':'.ES_PORT];
$client = ClientBuilder::create()
            ->setHosts($hosts)
            ->build();

$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_DATABASE);
if (!$mysqli->connect_errno) {
  // Get from MySQL concept IDs for concepts with strings in Spanish and index in Elasticsearch.
  mysqli_query($mysqli, "SET NAMES utf8");  
  $query = "select distinct CUI, STR from MRCONSO where LAT='SPA'";
  //$query = "select distinct CUI, STR from MRCONSO where CUI='C0000833'";  
  if ($result_db = mysqli_query($mysqli, $query)) {
    while ($row_db = $result_db->fetch_row()) {
      $cui = $row_db[0];
      $str = $row_db[1];
      // Index in Elasticsearch
      $index_params = [
          'index' => ES_INDEX_UMLS,
          'type' => ES_TYPE_UMLS,
          'id' => $cui,
          'body' => [
                      'upsert' => ['str' => [$str]],
                      'script' => [
                                    'source' => 'ctx._source.str.add(params.str)',
                                    'params' => ['str' => $str]
                                  ]
                    ]
        ];
      try {
        $response_index = $client->update($index_params);
        if (isset($response_index['result'])) {
          echo "Indexing concept $cui - $str: " . $response_index['result'] . "\n";
        }          
      } catch (Elasticsearch\Common\Exceptions\Missing404Exception $e) {
        $error = json_decode($e->getMessage());  
        printf("The elasticsearch indexing request failed with code: %s. Reason: \"%s\".\n", $e->getCode(), $error->{'error'}->{'reason'});
      }      
    }
  }
}
else {
    echo "Failed to connect to MySQL: (" . $mysqli->connect_errno . ") " . $mysqli->connect_error;
}

      
