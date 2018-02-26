#!/usr/bin/php
<?php

require_once 'config.php';
require_once 'vendor/autoload.php';
include 'processed.php';

use Httpful\Request;
use Elasticsearch\ClientBuilder;
use StanfordTagger\POSTagger;
use StanfordTagger\StanfordTagger;

// Initialize Stanford POS Tagger.
$pos = new POSTagger();
$pos->setModel(STANFORD_TAGGER_MODEL);
$pos->setJarArchive(STANFORD_TAGGER_JAR);
$pos->setOutputFormat(StanfordTagger::OUTPUT_FORMAT_TSV);

// Create Elasticsearch client.
$client = ClientBuilder::create()
            ->setHosts(['http://'.ES_HOST.':'.ES_PORT])
            ->build();

// Elasticsearch search parameters.            
$search_params_abstracts = [
    'index' => ES_INDEX,
    'type' => ES_TYPE,
    'size' => ES_BATCH_SIZE, // Per batch
    'sort' => ['_doc'],
    'scroll' => ES_SCROLL_TIME,
    '_source_include' => ['id', 'abstract'],
];

$get_params_annotations = [
    'index' => ES_INDEX_ANNOTATIONS_ENGLISH,
    'type' => ES_TYPE_ANNOTATIONS_ENGLISH,
];


$annotations_sentences_spanish = array();

if (!isset($processed)) {
  $processed = array();  
}
         
// Get, annotate and index abtract in Spanish
try {
  if (VERBOSE) {
    echo "Retrieving abstracts...\n";
  }
  $response_abstracts = $client->search($search_params_abstracts);
  if (isset($response_abstracts['hits']['hits'])) {
    while (count($response_abstracts['hits']['hits']) > 0) {
      // Retrieve each abstract in Spanish
      foreach ($response_abstracts['hits']['hits'] as $document) {
        $document_id = $document['_id'];
        if (!in_array($document_id, $processed)) {
          echo "Processing document: $document_id\n";                
          if (isset($document['_source']['abstract']['es']) && isset($document['_source']['abstract']['en'])) {
            $abstract_spanish = $document['_source']['abstract']['es'];
            $abstract_english = $document['_source']['abstract']['en'];           
            $length_abstract_spanish = mb_strlen($abstract_spanish, "UTF-8"); 
            $length_abstract_english = mb_strlen($abstract_english, "UTF-8");    
            if ($length_abstract_spanish > 0 && $length_abstract_english > 0) {          
              // POS-tag sentences in Spanish by means of the Stanford POS tagger.
              $tagged_sentences = explode("\n\n", $pos->tag($abstract_spanish));
              $offset_sentence = 0;
              // Get term candidates in Spanish.
              $sentences_spanish = array();
              $term_candidates = array();
              $id_sentence = 0;  
              foreach ($tagged_sentences as $num_sentence => $sentence) {
                $words_sentence = getWordsSentence(explode("\n", $sentence));
                $words_pos = $words_sentence['pos'];
                $words_string = formatSentenceString($words_sentence['string']);  
                $sentence_to_index = array('id'=>$id_sentence, 'start'=>$offset_sentence, 'text'=>"$words_string", 'terms'=>[]);
                $terms_sentence = getTermCandidates($words_pos, $offset_sentence);
                if (!empty($terms_sentence['terms'])) {
                  $term_candidates +=  $terms_sentence['terms'];
                }
                $offset_sentence += mb_strlen($words_string, 'UTF-8');
                $sentence_to_index['end'] = $offset_sentence; 
                $offset_sentence++;
                $sentences_spanish[] = $sentence_to_index;
                $id_sentence++;
              }    
              // Get annotated terms from the abstract in English for this document
              $get_params_annotations['id'] = $document_id;
              $response_annotations = $client->get($get_params_annotations);
              if (isset($response_annotations['_source']['sentences'])) {
                $annotated_sentences_en = $response_annotations['_source']['sentences'];
                $term_candidates_start_offset = 0;                            
                foreach ($annotated_sentences_en as $annotated_sentence_en) {
                  if (!empty($annotated_sentence_en['terms'])) {
                    $annotations_en = $annotated_sentence_en['terms'];
                    // For each annotation in English, find the best matching term in Spanish.
                    foreach ($annotations_en as $annotation_en) {
                      if (VERBOSE) {                    
                        echo "\nFinding best match for annotation: " . $annotation_en['text'] ." - (". $annotation_en['start'] ."-". $annotation_en['end'] . ") *****\n";
                      }
                      $annotation_ids = explode("|", $annotation_en['ids']);
                      foreach ($annotation_ids as $annotation_id) {
                        $best_matching_score = 0;                    
                        if (preg_match('/UMLS:(.+?):/', $annotation_id, $matches)) {
                          $annotation_umls_cui = $matches[1];
                          // Get scores 
                          $relative_offset_annotation = $annotation_en['start']/$length_abstract_english;
                          $term_candidates_start_offset = max($term_candidates_start_offset, floor(($relative_offset_annotation-REL_DISTANCE_CANDIDATE_TERMS) * $length_abstract_spanish));  
                          if ($term_candidates_spanish = getTermCandidatesOffset($term_candidates, $term_candidates_start_offset, $relative_offset_annotation, $length_abstract_spanish)) {
                            foreach ($term_candidates_spanish as $offset_term_candidates => $term_candidates_offset) {
                              foreach ($term_candidates_offset as $term_candidate) {
                                $term_string = implode(" ", $term_candidate['words']); 
                                $score_term = getScoreTermCandidate($term_string, $annotation_umls_cui);
                                if ($score_term > $best_matching_score) {
                                  $best_matching_score = $score_term;
                                  $selected_term = $term_candidate;
                                  $selected_term_string = implode(" ", $term_candidate['words']); // for debugging.
                                  $selected_cui = $annotation_id;                                
                                  $term_start = $offset_term_candidates;
                                  $term_end = $selected_term['end'];
                                  $term_candidates_start_offset = $term_end; // Next time we start looking from here.
                                }
                              }
                            }
                          }
                          else {
                            if (VERBOSE) {                                              
                              echo "No candidate terms found.\n"; 
                            }
                          }
                        }
                        if ($best_matching_score > 0) {
                          if (VERBOSE) {                                                                      
                            echo "===> Annotation spanish: $selected_term_string - $term_start-$term_end - $selected_cui. score: $best_matching_score\n";     
                          }
                          $new_annotation = array('ids'=>$selected_cui, 'start'=>$term_start, 'end'=>$term_end, 'text'=>$selected_term_string, 'terms'=>[]);
                          // We might needt o adjust the offsets based on the real text due to some errors in the segmentation, parser, etc.
                          $new_annotation_fixed_offsets = addAnnotationToSentence($new_annotation, $sentences_spanish);
                          $term_candidates_start_offset = $new_annotation_fixed_offsets['end'];
                        }
                        else {
                          if (VERBOSE) {                                                                                              
                            echo "No matching terms found.\n";
                          }
                        }
                      }
                    }                 
                  }
                }
              }
            }
          }
          echo "Indexing annotations for document $document_id...\n";
          $response_index = indexAnnotatedSentences($sentences_spanish, $document_id);
          if (isset($response_index['result'])) {
            echo "Elasticsearch message: Annotation of document $document_id - " . $response_index['result'] . "\n";
          }         
        }
      }
      if (VERBOSE) {      
        echo "Scrolling...\n";
      }
      $scroll_id = $response_abstracts['_scroll_id'];
      $response_abstracts = $client->scroll([
            'scroll_id' => $scroll_id,
            'scroll' => ES_SCROLL_TIME
        ]
      );    
    }
  }
} catch (Elasticsearch\Common\Exceptions\Missing404Exception $e) {
    $error = json_decode($e->getMessage());  
    //print_r($e);
    printf("Exception Missing404Exception. The elasticsearch request failed with code: %s. Reason: \"%s\".\n", $e->getCode(), isset($error->error->reason) ? $error->error->reason : '');
} catch (Elasticsearch\Common\Exceptions\BadRequest400Exception $e) {
    $error = json_decode($e->getMessage());   
    //print_r($e);    
    printf("Exception BadRequest400Exception. The elasticsearch request failed with code: %s. Reason: \"%s\".\n", $e->getCode(), isset($error->error->reason) ? $error->error->reason : '');  
} catch (Elasticsearch\Common\Exceptions\RuntimeException $e) {
    $error = json_decode($e->getMessage());   
    //print_r($e);    
    printf("Exception RuntimeException. The elasticsearch request failed with code: %s. Reason: \"%s\".\n", $e->getCode(), isset($error->error->reason) ? $error->error->reason : '');  
}


function getScoreTermCandidate($term_string, $annotation_umls_cui) {
  global $client;  
  $score = 0;
  $score_params_term_candidates = [
      'index' => ES_INDEX_UMLS,
      'type' => ES_TYPE_UMLS,  
      'id' => $annotation_umls_cui,
      'analyzer' => 'spanish', 
      'q' => 'str:"' . $term_string . '"'
  ];
  try {
    $response_score = $client->explain($score_params_term_candidates);  
    if (isset($response_score['explanation']['value'])) {
      $score = $response_score['explanation']['value'];
    }
  } catch (Elasticsearch\Common\Exceptions\Missing404Exception $e) {
    // Do nothing if the concept is not indexed.
  }
  return $score;  
}

// Get first term candidates found from a particular offset.
// We search for a term in Spanish beginning after the previous one and not to far from the annotation in English - in relation to their respective relative positions in the abstracts.
function getTermCandidatesOffset($term_candidates, $term_candidates_start_offset, $relative_offset_annotation, $length_abstract_spanish) {
  $found_term_candidates = array();
  end($term_candidates);
  $last_term_offset = key($term_candidates);
  $term_candidates_offset = $term_candidates_start_offset;
  $relative_offset_candidate = $term_candidates_offset > 0 ? $term_candidates_offset/$length_abstract_spanish : 0;  
  while ($term_candidates_offset <= $last_term_offset && $relative_offset_candidate < $relative_offset_annotation + REL_DISTANCE_CANDIDATE_TERMS) {
    if (isset($term_candidates[$term_candidates_offset])) {
      $found_term_candidates[$term_candidates_offset] = $term_candidates[$term_candidates_offset];
    }
    $term_candidates_offset++;
    $relative_offset_candidate = $term_candidates_offset/$length_abstract_spanish;
  }
  return $found_term_candidates;
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

function getWordsSentence($tokens) {
  $words_pos = array();
  $words_string = array();  
  foreach ($tokens as $token) {
    $word = explode("\t", $token);
    switch ($word[0]) {
        case "-":
            $word[1] = "DASH";
            break;            
        case "o":
            $word[1] = "CONJ";
            break;                        
        case "-LRB-":
            $word[0] = "(";        
            $word[1] = "PUNCT";
            break;
        case "-RRB-":
            $word[0] = ")";                
            $word[1] = "PUNCT";
            break;
        case "-LSB-":
            $word[0] = "[";                
            $word[1] = "PUNCT";
            break;
        case "-RSB-":
            $word[0] = "]";                
            $word[1] = "PUNCT";
            break;
        case "-LCB-":
            $word[0] = "{";                
            $word[1] = "PUNCT";
            break;
        case "-RCB-":
            $word[0] = "}";                
            $word[1] = "PUNCT";
            break;    
        case "-RCB-":
            $word[0] = "}";                
            $word[1] = "PUNCT";
            break;
        case "-RCB-":
            $word[0] = "}";                
            $word[1] = "PUNCT";
            break;            
    }    
    if ($word[1] == "X") {
      if (preg_match('/\w{3,}/', $word[0])) {
        $word[1] = "PROPN"; // Treat unknown POS as proper nouns if token has length of 3 or more.
      }
      elseif (preg_match('/^\W$/', $word[0])) {
        $word[1] = "SYM";      
      }
    }
    // Hack to fix parser problem with numbers with commas.  
    if (preg_match('/^,(\d+)/', $word[0], $match)) {
      $num_words = count($words_string);
      if ($num_words>0 && preg_match('/\d$/', $words_string[$num_words-1])) {
        $words_string[$num_words-1] .= ',' . $match[1];
        $words_pos[$num_words-1] = [$words_string[$num_words-1], 'NUM'];        
      }
    }
    else {
      $words_string[] = $word[0];    
      $words_pos[] = $word;      
    }
  }  
  return array('string' => $words_string, 'pos' => $words_pos);
}

function getTermCandidates($words_pos, $offset) {
  $np_chunks = array();
  foreach ($words_pos as $i => $word_pos) {
    $word = $word_pos[0];
    $pos = $word_pos[1];
    if ($term_candidates_position = getTermCandidatesPosition($i, $offset, $words_pos)) {
      $np_chunks[$offset] = $term_candidates_position;
    }
    if ($pos !== "PUNCT" && $pos !== "SYM" && $pos !== "DASH") {
      $offset += mb_strlen($word, "UTF-8");
    }
    $offset++; //We add one for the space or symbol/punctuation.           
  }
  return array('terms' => $np_chunks, 'offset' => $offset);
}

function getTermCandidatesPosition($position, $offset, $words_pos) {
  $np_chunks = array();
  $array_string = array();
  $array_pos = array();
  $end = false;
  $valid_pos = array("NOUN","PROPN","ADJ","DASH","CONJ","ADP","DET");  
  $noun = "(NOUN|PROPN)";
  $noun_chunk = "($noun|ADJ#$noun|$noun#ADJ|$noun#ADJ#ADJ|$noun#ADJ#CONJ#ADJ|$noun#DASH#$noun|$noun#CONJ$noun)";
  $valid_subterm = "($noun_chunk#CONJ|$noun_chunk#ADP|$noun_chunk#ADP#DET)";
  $valid_term = "($noun_chunk|$valid_subterm#$noun_chunk)";
  $i = $position;
  while (isset($words_pos[$i]) && !$end) {
    $word_string = $words_pos[$i][0];
    $word_pos = $words_pos[$i][1];    
    $array_string[] = $word_string;
    $array_pos[] = $word_pos;
    if ($word_pos !== "PUNCT" && $word_pos !== "SYM" && $word_pos !== "DASH") {
      $offset += mb_strlen($word_string, "UTF-8");      
    } 
    $offset++;
    $end = !in_array($word_pos, $valid_pos);
    if (!$end) {
      $pos = implode("#", $array_pos);
      // If it's a valid term we add it.
      if (preg_match("/^$valid_term$/", $pos)) {
        $end_offset = $offset-1;
        $np_chunks[] = array('words' => $array_string, 'end' => $end_offset, 'pos' => $pos);
      }
    }
    $i++;    
  }
  return $np_chunks;
}

function formatSentenceString($array_words) {
  $sentence_string = implode(" ", $array_words);
  // No spaces within decimal numbers
  $sentence_string = preg_replace('/(\d+) ([\.\,]) (\d+)/', '${1}${2}${3}', $sentence_string);
  // No spaces
  $sentence_string = preg_replace('/ ([\-\+\/\*=\@<>]) /', '${1}', $sentence_string);     
  // No spaces after  
  $sentence_string = preg_replace('/([\.\(\[\{]) /', '${1}', $sentence_string);
  // No spaces before  
  $sentence_string = preg_replace('/ ([\.\)\]\{,;:\$\€%\?!])/', '${1}', $sentence_string);
  // No space within quotes
  $sentence_string = preg_replace('/["«\'] (.+) [\'"»]/', '"${1}"', $sentence_string);  
  $sentence_string = preg_replace('/[\x{2580}-\x{259F}\x{2190}-\x{21FF}\x{2500}-\x{257F}\x{25A0}-\x{25FF}\x{2600}-\x{26FF}\x{2700}-\x{27BF}]/u', '', $sentence_string);  
  return $sentence_string;
}

// Index annotated abstract
function indexAnnotatedSentences($annotated_sentences, $document_id) {
  global $client;
  $index_params = array(
      'index' => ES_INDEX_ANNOTATIONS_SPANISH,
      'type' => ES_TYPE_ANNOTATIONS_SPANISH,
      'id' => $document_id,  
      'body' => array('doc' => ['sentences' => $annotated_sentences], 'doc_as_upsert' => true)
  );    
  return $client->update($index_params);
}

function addAnnotationToSentence($new_annotation, &$sentences_spanish) {
  foreach ($sentences_spanish as $sentence_id => $sentence) {
    if ($sentence['start'] <= $new_annotation['start'] && $sentence['end'] >= $new_annotation['end']) {
        $num_terms = count($sentence['terms']); 
        $new_annotation['id'] = $num_terms+1; // IDs start in 1.
        $prev_annotation_end_offset = ($num_terms > 0) ? $sentence['terms'][$num_terms-1]['end']-$sentence['start'] : 0;
        $start = mb_strpos($sentence['text'], $new_annotation['text'], $prev_annotation_end_offset, 'UTF-8')+$sentence['start'];
        $end = $start + mb_strlen($new_annotation['text'], 'UTF-8'); 
        $new_annotation['start'] = $start;
        $new_annotation['end'] = $end;        
        $sentence['terms'][] = $new_annotation;
        $sentences_spanish[$sentence_id] = $sentence;
        break;
    }
  }
  return $new_annotation;
}

            