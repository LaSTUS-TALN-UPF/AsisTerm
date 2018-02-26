<?php

require_once 'config.php';
require_once 'vendor/autoload.php';
require_once 'umls_source_names.php';

use Httpful\Request;
use Elasticsearch\ClientBuilder;

$hosts = ['http://'.ES_HOST.':'.ES_PORT];
$client = ClientBuilder::create()
            ->setHosts($hosts)
            ->build();

// Full-text search in the ScieLO corpus of abstracts
function search_scielo($search_string, $lang, $number=10) {
  global $client;  
  $retrieved_documents = array();
  $search_params = [
      'index' => ES_INDEX,
      'type' => ES_TYPE,
      'size' => $number,      
      '_source_include' => ['id', 'date', 'source', 'authors', 'title', 'keywords'],
      'body' => [
        'query' => [
          'multi_match' => [
            'query' => "$search_string", 
            'type' => 'best_fields',
            'fields' => [ 'title.'.$lang, 'abstract.'.$lang, 'keywords' ] 
          ]
        ]
      ]
  ];  
  try {
    $response = $client->search($search_params);
    $retrieved_documents = isset($response['hits']['hits']) ? $response['hits']['hits'] : array();
  } catch (Elasticsearch\Common\Exceptions\Missing404Exception $e) {
      $error = json_decode($e->getMessage());  
      printf("<!-- The elasticsearch search request failed with code: %s. Reason: \"%s\".-->\n", $e->getCode(), isset($error->error->reason) ? $error->error->reason : '');
  } catch (Elasticsearch\Common\Exceptions\BadRequest400Exception $e) {
      $error = json_decode($e->getMessage());    
      printf("<!-- The elasticsearch search request failed with code: %s. Reason: \"%s\".-->\n", $e->getCode(), isset($error->error->reason) ? $error->error->reason : '');  
  } 
  return $retrieved_documents;
}

// Get annotated sentences in abstract (in English or Spanish) by document id.
// We retrieve the original abstract - not annotated to display as there might be some differences with the texts in the sentences.
function get_annotated_abstracts_scielo($document_id) {
  global $client;  
  $annotated_sentences = array();
  $annotations = array();
  $title = array();
  $text_abstract = array();
  $get_params_annotations_en = [
      'index' => ES_INDEX_ANNOTATIONS_ENGLISH,
      'type' => ES_TYPE_ANNOTATIONS_ENGLISH,
      'id' => "$document_id"
  ];  
  $get_params_annotations_es = [
      'index' => ES_INDEX_ANNOTATIONS_SPANISH,
      'type' => ES_TYPE_ANNOTATIONS_SPANISH,
      'id' => "$document_id"
  ];    
  $get_params_document = [
      'index' => ES_INDEX,
      'type' => ES_TYPE,
      'id' => "$document_id"
  ];  
  try {
    // Get annotations in English
    $response = $client->get($get_params_annotations_en);
    $annotated_sentences['en'] = isset($response['_source']['sentences']) ? $response['_source']['sentences'] : array();
    // Get annotations in Spanish    
    $response = $client->get($get_params_annotations_es);
    $annotated_sentences['es'] = isset($response['_source']['sentences']) ? $response['_source']['sentences'] : array();    
    // Get title and texts of abstracts in English and Spanish    
    $response = $client->get($get_params_document);
    $text_abstract['en'] = isset($response['_source']['abstract']['en']) ? $response['_source']['abstract']['en'] : ''; 
    // In Spanish we show the annotated text to avoid discrepancies with filtered characters, etc.
    $text_abstract['es'] = get_text_from_sentences($annotated_sentences['es']);    
    $title['en'] = isset($response['_source']['title']['en']) ? $response['_source']['title']['en'] : '';    
    $title['es'] = isset($response['_source']['title']['es']) ? $response['_source']['title']['es'] : '';        
  } catch (Elasticsearch\Common\Exceptions\Missing404Exception $e) {
      $error = json_decode($e->getMessage());  
      printf("<!-- The elasticsearch search request failed with code: %s. Reason: \"%s\".-->\n", $e->getCode(), isset($error->error->reason) ? $error->error->reason : '');
  } catch (Elasticsearch\Common\Exceptions\BadRequest400Exception $e) {
      $error = json_decode($e->getMessage());    
      printf("<!-- The elasticsearch search request failed with code: %s. Reason: \"%s\".-->\n", $e->getCode(), isset($error->error->reason) ? $error->error->reason : '');  
  } 
  if ($text_abstract && $annotated_sentences) {
    $annotations = annotate_text($text_abstract, $annotated_sentences);
  }
  return array('title' => $title, 'annotations' => $annotations);  
}

function get_text_from_sentences($sentences) {
  $text_sentences = array();
  foreach ($sentences as $sentence) {
    if (!empty($sentence['text'])) {
      $text_sentences[] = $sentence['text'];
    }
  }
  return implode(' ', $text_sentences);
}

function annotate_text($text_abstract, $annotated_sentences) {
  $span_open = '<span class="tooltip" data-tooltip-content="#tooltip_content';
  $span_close = '</span>'; 
  $annotations = array('es' => '', 'en' => '');
  foreach ($text_abstract as $lang => $abstract) {
    $concepts = array();
    if (!empty($annotated_sentences[$lang])) {
      $sentences = $annotated_sentences[$lang];
      $ann_number = 1;
      $accumulated_padding = 0;
      foreach ($sentences as $sentence) {
        if (isset($sentence['terms'])) {
          foreach ($sentence['terms'] as $annotation) {
            $concept_id = $annotation['ids'];
            $start = $annotation['start'];
            $end = $annotation['end'];
            //$text = $annotation['text'];          
            $annotation_open_pos = $start + $accumulated_padding;
            $annotation_close_pos = $end + $accumulated_padding;
            $span_start = $span_open . '-' . $lang . '-' . $ann_number . '" id="tooltip-' . $lang . '-' . $ann_number .'">';
            $abstract = mb_substr($abstract, 0, $annotation_close_pos, 'UTF-8').$span_close.mb_substr($abstract, $annotation_close_pos, NULL, 'UTF-8');
            $abstract = mb_substr($abstract, 0, $annotation_open_pos, 'UTF-8').$span_start.mb_substr($abstract, $annotation_open_pos, NULL, 'UTF-8');          
            $accumulated_padding += mb_strlen($span_start) + mb_strlen($span_close);
            $concepts[$ann_number] = $concept_id;
            $ann_number++;
          }
        }
      }
    }
    $annotations[$lang] = array('abstract' => $abstract, 'concepts' => $concepts);
  }
  return $annotations;  
}

function get_concept_string_sources_umls($cui, $lang) {
  $language = ($lang == 'es') ? 'SPA' : 'ENG';
  $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_DATABASE);
  $concepts_sources = array();
  if (!$mysqli->connect_errno) {
    mysqli_query($mysqli, "SET NAMES utf8");  
    $query = "select CUI,AUI,CODE,SAB,TS,STR from MRCONSO where LAT='$language' AND CUI='$cui' AND SUPPRESS='N' AND STT='PF' AND ISPREF='Y' ORDER BY TS, SAB";    
    if ($result_db = mysqli_query($mysqli, $query)) {
      while ($row_db = $result_db->fetch_row()) {
        $cui = $row_db[0];
        $aui = $row_db[1];
        $code = $row_db[2];
        $sab = $row_db[3];
        $ts = $row_db[4];
        $str = $row_db[5];
        $concepts_sources[$sab][$code][$aui] = $str;
      }
    }  
  }
  else {
      echo "<!-- Failed to connect to MySQL: (" . $mysqli->connect_errno . ") " . $mysqli->connect_error . "-->";
  }
  return $concepts_sources;
}


function get_source_name($umls_source) {
  global $source_names;
  return isset($source_names[$umls_source]) ? $source_names[$umls_source] : $umls_source;
}

// TODO: Generalize
function get_concept_link($source_name, $lang, $code, $source_string) {
  $link = '';
  $concept_link = "$source_string";
  if ($code && $code !== 'NOCODE') {
    $concept_link .= " ($code)";
  }
  if (preg_match('/^SNOMED/', $source_name)) {
    $link = str_replace('CODE', $code, SNOMED_LINK);
    $link = str_replace('LANG', $lang, $link);
  }
  elseif (preg_match('/^MeSH/', $source_name)) {
    $link = str_replace('CODE', $code, MESH_LINK);    
  }
  if ($link) {
    $concept_link = '<a target="_new" href="' . $link . '">' . $source_string . ' (' . $code . ')' . ' &nearr;</a>';
  }
  return $concept_link;
}

function get_definition_umls($source_synonyms) {
  $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_DATABASE);
  $definitions = array();
  $definition = array();
  if (!$mysqli->connect_errno) {
    foreach (array_keys($source_synonyms) as $aui) {
      mysqli_query($mysqli, "SET NAMES utf8");  
      $query = "select DEF from MRDEF where AUI='$aui';";    
      if ($result_db = mysqli_query($mysqli, $query)) {
        if ($row_db = $result_db->fetch_row()) {
          $definitions[] = $row_db[0];          
        }
      }  
    }
  } 
  else {
      echo "<!-- Failed to connect to MySQL: (" . $mysqli->connect_errno . ") " . $mysqli->connect_error . "-->";
  }  
  $text_definition = implode("\n", $definitions);
  $definition = get_splitted_definition($text_definition);
  return $definition;
}

function get_splitted_definition($text_definition) {
  $definition = array();  
  $text_definition = str_replace('<p>', '', $text_definition);
  $text_definition = str_replace('</p>', ' ', $text_definition);  
  $splitted_definition = explode('. ', $text_definition);
  if (count($splitted_definition) > SHOW_NUM_SENTENCES_DEFINITION) {
    $num_sent = 0;   
    $definition['short_def'] = '';
    $definition['read_more'] = '';    
    while ($num_sent < SHOW_NUM_SENTENCES_DEFINITION) {
      if ($sentence = $splitted_definition[$num_sent]) {
        $definition['short_def'] .= "$sentence.\n"; 
      }
      $num_sent++;      
    }    
    while ($num_sent < count($splitted_definition)) {
      if ($sentence = $splitted_definition[$num_sent]) {
        $definition['read_more'] .= "$sentence.\n"; 
      }
      $num_sent++;            
    }
  }
  else {
    $definition['short_def'] = $text_definition;
    $definition['read_more'] = '';    
  }
  return $definition;
}

// TODO: Generalize
function get_additional_sources($source_name, $code, $lang) {
  $medline_concepts = array();
  if (preg_match('/^SNOMED/', $source_name)) {
    $link = str_replace('CODE', $code, MEDLINE_LINK);
    $link = str_replace('LANG', $lang, $link);  
    try {
      $resp_medline = Request::get($link)->send();
      if ($resp_medline->hasBody()) {
        if (!empty($resp_medline->body->feed->entry)) {
          $medline_entries = $resp_medline->body->feed->entry;
          foreach ($medline_entries as $entry) {
            $medline_concept = array();
            if (isset($entry->title->_value)) {
              $medline_concept['title'] = $entry->title->_value;
              if (isset($entry->link[0]->href)) {
                $medline_concept['link'] = $entry->link[0]->href;
              }
              if (isset($entry->summary->_value)) {
                $medline_concept['definition'] = get_splitted_definition($entry->summary->_value);
              }
              $medline_concepts[] = $medline_concept;
            }
          }
        }
      }
    }
    catch (Httpful\Exception\ConnectionErrorException $e) {
      printf("<!--The connection to the MedlinePlus Connect service failed with code: %s.-->\n", $e->getCode());
    }
  }
  return $medline_concepts;
}
            
