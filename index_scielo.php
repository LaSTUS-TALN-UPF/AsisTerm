#!/usr/bin/php
<?php

require_once 'config.php';
require_once 'vendor/autoload.php';

use Elasticsearch\ClientBuilder;

$hosts = ['http://'.ES_HOST.':'.ES_PORT];
$client = ClientBuilder::create()
            ->setHosts($hosts)
            ->build();
   
//------------------------------------------- Get directory names ----------------------------------------
$directories = scandir(ABSTRACTS_DIR);

//-------------------------------------- Read and index abstracts -----------------------------------
foreach ($directories as $directory) {
  echo "------------ Directory: $directory -------------\n";
  // Get xml files
  $files = array_filter(scandir(ABSTRACTS_DIR . '/' . $directory), function($f) { return preg_match('/\.xml$/', $f); });
  foreach ($files as $file) {
    if ($xmlstring = file_get_contents(ABSTRACTS_DIR . '/' . $directory . '/' . $file)) {
      echo "Processing $file\n";
      $index_abstract = array();                
      $doc_id = str_replace('.xml', '', $file);
      $xml = simplexml_load_string($xmlstring);
      $xml->registerXPathNamespace('dc', 'http://purl.org/dc/elements/1.1/');    
      $xml->registerXPathNamespace('oai-dc', 'http://www.openarchives.org/OAI/2.0/oai_dc/');    
      $date = $xml->record->header->datestamp->__toString();
      $index_abstract['date'] = $date;
      $publisher = $xml->xpath('//dc:publisher')[0];
      $index_abstract['publisher'] = $publisher->__toString();
      $source = $xml->xpath('//dc:source')[0];
      $index_abstract['source'] = $source->__toString();
      $type = $xml->xpath('//dc:type')[0];
      $index_abstract['type'] = $type->__toString();
      $format = $xml->xpath('//dc:format')[0];
      $index_abstract['format'] = $format->__toString();
      $language = $xml->xpath('//dc:language')[0];
      $index_abstract['language'] = $language->__toString();
      $authors = $xml->xpath('//dc:creator');   
      foreach ($authors as $author) {    
          $index_abstract['authors'][] = $author->__toString();        
      }
      $keywords = $xml->xpath('//dc:subject');    
      foreach ($keywords as $keyword) {        
          $index_abstract['keywords'][] = $keyword->__toString();        
      }    
      $titles = $xml->xpath('//dc:title');   
      foreach ($titles as $title) {            
          if (!$title_lang = (string) $title->attributes('xml', TRUE)['lang']) {
            $title_lang = 'undef';
          }
          $index_abstract['title'][$title_lang] = $title->__toString();
      }    
      $abstracts = $xml->xpath('//dc:description');    
      foreach ($abstracts as $abstract) {                
          if (!$abstract_lang = (string) $abstract->attributes('xml', TRUE)['lang']) {
            $abstract_lang = 'undef';
          }
          $index_abstract['abstract'][$abstract_lang] = $abstract->__toString();
      }    
      //print_r($index_abstract);
      //echo "\n";

      // Index in Elasticsearch
      $index_params = [
          'index' => ES_INDEX,
          'type' => ES_TYPE,
          'id' => $doc_id,
          'body' => $index_abstract,
        ];
      try {
        $response_index = $client->index($index_params);
        if (isset($response_index['result'])) {
          echo "Indexing abstract with id $doc_id: " . $response_index['result'] . "\n";
        }          
      } catch (Elasticsearch\Common\Exceptions\Missing404Exception $e) {
        $error = json_decode($e->getMessage());  
        printf("The elasticsearch indexing request failed with code: %s. Reason: \"%s\".\n", $e->getCode(), $error->{'error'}->{'reason'});
      }
    } else {
      echo "The file $file could not be opened. Skipped.\n";
    }     
  }
}

            