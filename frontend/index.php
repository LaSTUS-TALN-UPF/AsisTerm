<?php
require_once('functions.php');

// Default message
$default_message_title = 'Bienvenidos a AsisTerm';
$default_message_body = 'Comience relizando una búsqueda en el corpus de ScieLO.<br>De esta forma, podrá recuperar resúmenes en español e inglés anotados con conceptos de UMLS y visualizar sus definiciones.';

// Initialize
$search_results = '';
$abstract = array();

// Save already retrieved SNOMED codes/information in order to avoid connecting again to MedlinePlus.
$retrieved_medline_concepts = array();

// Capture input

if (isset($_POST['action'])) {
  $action = $_POST['action'];
}
elseif (isset($_GET['action'])) {
  $action = $_GET['action'];
}
else {
  $action = '';
}

if (isset($_POST['search'])) {
  $search_string = $_POST['search'];
}
elseif (isset($_GET['search'])) {
  $search_string = $_GET['search'];
}
else {
  $search_string = '';
}

if (isset($_POST['lang'])) {
  $lang = $_POST['lang'];
}
elseif (isset($_GET['lang'])) {
  $lang = $_GET['lang'];
}
else {
  $lang = 'es';
}

$lang_abstract = isset($_GET['lang_abstract']) ? $_GET['lang_abstract'] : $lang;

$message_title = $default_message_title;  
$message_body = $default_message_body;
  
// Perform search
if ($search_string) {
  $message_title = '';  
  if (!$search_results = search_scielo($search_string, $lang, 10)) {
    $message_body = 'No se obtuvieron resultados para la búsqueda: <i>"'.$search_string.'"</i>';
  }
  else {
    $message_body = '';
  }
}

// Show annotations  
if ($action == 'annotations') {
  $document_id  = isset($_GET['id']) ? $_GET['id'] : '';
  $message_title = '';    
  if ($document_id) {
    $abstract = get_annotated_abstracts_scielo($document_id);
    if (empty($abstract['annotations'])) {
      $message_body = 'No se encontr&oacute; el resumen para el documento seleccionado';
    }
    else {
      $message_body = '';
    }  
  }
  else {
    $message_body = 'Identificador de documento no v&aacute;lido';
  }
}

?>
<html lang="en">
<head>
	<meta charset="utf-8">
	<title>AsisTerm</title>
	<!-- Google Fonts -->
	<link href="https://fonts.googleapis.com/css?family=Montserrat:300,300i,400,400i,500,500i,600,600i,700" rel="stylesheet">
	<!-- Template Styles -->
	<link rel="stylesheet" href="css/font-awesome.min.css">
	<!-- CSS Reset -->
	<link rel="stylesheet" href="css/normalize.css">
	<!-- Milligram CSS minified -->
	<link rel="stylesheet" href="css/milligram.min.css">
	<!-- Main Styles -->
	<link rel="stylesheet" href="css/styles.css">
  <!-- JQuery -->
	<!--script src="js/jquery.js"></script--> 
  <!-- Tooltipster -->
  <link rel="stylesheet" type="text/css" href="tooltipster/dist/css/tooltipster.bundle.min.css" />
  <link rel="stylesheet" type="text/css" href="tooltipster/dist/css/plugins/tooltipster/sideTip/themes/tooltipster-sideTip-light.min.css" />  
  <script type="text/javascript" src="http://code.jquery.com/jquery-1.10.0.min.js"></script>
  <script type="text/javascript" src="tooltipster/dist/js/tooltipster.bundle.min.js"></script>   
  <script type="text/javascript" src="tooltipster-scrollableTip/tooltipster-scrollableTip.min.js"></script>  
  <!-- ScieLO JS functions and CSS styles -->  
	<script src="js/scielo.js"></script>  
	<link rel="stylesheet" href="css/scielo.css">    

	<!--[if lt IE 9]>
	<script src="http://html5shim.googlecode.com/svn/trunk/html5.js"></script>
	<![endif]-->
  <script>
  var lang_abstract = '<?php echo $lang_abstract; ?>';
  var have_definitions = {'es': [], 'en': []};
  $(document).ready( function(){
      $(".cb-enable").click(function(){
          var parent = $(this).parents('.switch');
          $('.cb-disable',parent).removeClass('selected');
          $(this).addClass('selected');
          $('.checkbox',parent).attr('checked', true);
      });
      $(".cb-disable").click(function(){
          var parent = $(this).parents('.switch');
          $('.cb-enable',parent).removeClass('selected');
          $(this).addClass('selected');
          $('.checkbox',parent).attr('checked', false);
      });
      $('.tooltip').tooltipster({
          side: 'bottom',
          maxWidth: 400,
          theme: 'tooltipster-light',
          plugins: ['sideTip', 'scrollableTip'],
          animation: 'grow',
          delay: 100,
          //contentAsHTML: true,
          interactive: true,    
          repositionOnScroll: false,          
          trigger: 'custom',
          triggerOpen: {
            click: true,
            tap: true
          },
          triggerClose: {
              click: true,
              tap: true,              
              scroll: false
          }          
      });
      changeLanguage();
      changeColorDefinitions();      
  });  
  </script>
  
</head>

<body>
	<div class="navbar">
		<div class="row">
			<div class="column column-30 col-site-title"><a href="/scielo" class="site-title float-left">AsisTerm</a></div>
			<div class="column column-40 col-search">
        <form method="post" id="search-form" action="/scielo/index.php">
          <a class="search-btn fa fa-search" onclick="submitSearchForm();"></a>
          <input type="hidden" name="action" value="search"/>
          <input id="search-field" type="text" name="search" value="<?php echo $search_string; ?>" placeholder="Buscar documentos en ScieLO..." />
          <!--p class="field switch"-->
          <div id="language-selection">          
              <input type="radio" id="radio-es" name="lang" value="es" <?php echo ($lang=='es') ? 'checked' : ''; ?> />Espa&ntilde;ol
              <input type="radio" id="radio-en" name="lang" value="en" <?php echo ($lang=='en') ? 'checked' : ''; ?>/>Ingl&eacute;s
              <!--label for="radio-es" class="cb-enable <?php echo ($lang=='es') ? 'selected' : ''; ?>"><span>Espa&ntilde;ol</span></label>
              <label for="radio-en" class="cb-disable <?php echo ($lang=='en') ? 'selected' : ''; ?>"><span>Ingl&eacute;s</span></label-->
          </div>
        </form>
			</div>
			<div class="column column-30">
          <a target="_new" href="http://taln.upf.edu/"><img id="logo-taln" src="images/taln.jpg" alt="TALN-UPF" class="circle float-left profile-photo" width="80" height="auto"></a>           
			</div>
		</div>
	</div>
	<div class="row">
		<div id="sidebar" class="column">
			<h5>Navegaci&oacute;n</h5>
        <?php
          //var_dump($_POST);
          //var_dump($_GET);          
        ?>      
			<ul>
				<li><a href="/scielo"><em class="fa fa-home"></em> Inicio</a></li>
				<li><a href="/scielo/acerca.htm"><em class="fa fa-pencil-square-o"></em> Acerca</a></li>
      </ul>
      <hr>
      <div id="about">
<a href="https://www.upf.edu/web/taln/large-scale-text-understanding-systems" target="_new">Large-Scale Text Understanding Systems Lab (LaSTUS)</a><br>
<a href="https://www.upf.edu/web/taln" target="_new">TALN-UPF</a>      
      </div>
		</div>
		<section id="main-content" class="column column-offset-20">
    <?php
    // ------------------------------------- Message -------------------------------------        
    if ($message_body) {
    ?>       
			<div class="row grid-responsive">
				<div class="column page-heading">
					<div class="large-card">
              <p class="text-large"><?php echo $message_title; ?></p>
              <p><?php echo $message_body; ?></p>
					</div>
				</div>
			</div> 
    <?php      
    }
    ?>     

    <?php          
    // ------------------------------------- Annotations -------------------------------------    
    if ($action=='annotations' && !empty($abstract['annotations'])) { 
    ?>   
    
    <div class="tooltip_templates">
          <?php        
            $have_definitions = array();          
            foreach (['en', 'es'] as $lang) {
              $have_definitions[$lang] = array();
              $concepts = $abstract['annotations'][$lang]['concepts'];
              foreach ($concepts as $concept_number => $concept_ids) {
          ?>                
                <span id="tooltip_content-<?php echo "$lang-$concept_number"; ?>">
                  <b>Conceptos</b>
                  <div class="list-umls-concepts">
                  <?php        
                    //$more_info 
                    foreach (explode("|", $concept_ids) as $concept_id) {                  
                      if (preg_match('/UMLS:(.+?):/', $concept_id, $matches)) {
                        $cui = $matches[1];
                        echo '<p class="umls-cui"> UMLS:' . $cui . '</p>';
                        $concept_strings_sources = get_concept_string_sources_umls($cui, $lang);
                        foreach ($concept_strings_sources as $source => $source_concepts) {
                          $source_name = get_source_name($source);
                          echo '<p class="source-name">' . $source_name . '</p>';                          
                          echo '<div class="source-concept">';
                          // Now we display only the first one.
                          $first_source_code = key($source_concepts);
                          $source_synonyms = current($source_concepts);
                          $source_string = current($source_synonyms);
                          $definition = get_definition_umls($source_synonyms);
                          echo '<div class="concept-link">' . get_concept_link($source_name, $lang, $first_source_code, $source_string) . '</div>';
                          if (!empty($definition['short_def'])) {
                            if (!in_array($concept_number, $have_definitions[$lang])) {
                              $have_definitions[$lang][] = $concept_number;
                            }
                            echo '<div class="concept-definition">';
                            echo $definition['short_def'];
                            if ($definition['read_more']) {
                                echo '<a class="read-more" onclick="showMoreLess(\''.$concept_number.'-'.$cui.'-'.$source.'-'.$lang.'\')">+/-</a>';
                                echo '<div id="'.$concept_number.'-'.$cui.'-'.$source.'-'.$lang.'" style="display: none">';
                                  echo $definition['read_more'];
                                echo '</div>';
                            }
                            echo '</div>';
                          }                          
                          echo '</div>';    
                          if ($lang == 'es') { // For the time being, in English Medline information is already retrieved from UMLS.
                            // TODO: Generalize.
                            if (!isset($retrieved_medline_concepts[$source][$lang][$first_source_code])) {
                              $retrieved_medline_concepts[$source][$lang][$first_source_code] = get_additional_sources($source_name, $first_source_code, $lang);   
                            }
                            if ($medline_concepts = $retrieved_medline_concepts[$source][$lang][$first_source_code]) {
                              echo '<p class="source-name">MedlinePlus</p>';   
                              foreach ($medline_concepts as $concept_num => $medline_concept) {
                                echo '<div class="source-concept">';
                                if (isset($medline_concept['title'])) {
                                  if (isset($medline_concept['link'])) {
                                    echo '<div class="concept-link"><a target="_new" href="' . $medline_concept['link'] . '">' . $medline_concept['title'] . ' &nearr;</a></div>';
                                  }
                                  else {
                                    echo '<div class="concept-link">' . $medline_concept['title'] . '</div>';
                                  }
                                }
                                if (!empty($medline_concept['definition'])) {
                                  if (!in_array($concept_number, $have_definitions[$lang])) {
                                    $have_definitions[$lang][] = $concept_number;
                                  }                                  
                                  echo '<div class="concept-definition">';
                                  echo $medline_concept['definition']['short_def'];
                                  if ($medline_concept['definition']['read_more']) {
                                      echo '<a class="read-more" onclick="showMoreLess(\''.$concept_number.'-'.$cui.'-medlineplus-'.$concept_num.'-'.$lang.'\')">+/-</a>';
                                      echo '<div id="'.$concept_number.'-'.$cui.'-medlineplus-'.$concept_num.'-'.$lang.'" style="display: none">';
                                      echo $medline_concept['definition']['read_more'];
                                      echo '</div>';
                                  }
                                  echo '</div>';
                                }                          
                                echo '</div>'; 
                              }                              
                            }
                          }
                        }
                      }
                    }                      
                  ?>   
                  </div>                  
                </span>
          <?php                        
              }                
            }
            echo "<script>\n";
            foreach (['en', 'es'] as $lang) {
              foreach ($have_definitions[$lang] as $concept_number) {
                echo 'have_definitions.'.$lang.'.push("'.$concept_number.'");' . "\n";
              }
            }
            echo "</script>\n";            
          
          ?>   
        
    </div>       
          <h5 class="mt-2">Res&uacute;menes anotados</h5><a class="anchor" name="tables"></a>            
          <div id="texto-anotado" class="row grid-responsive">
            <div class="column page-heading">
              <div class="large-card">
                <div id="abstract-es">
                  <div class="card-title">
                      <h4><i><?php echo $abstract['title']['es']; ?></i></h4>
                  </div>              
                  <div id="abstract">
                    <?php echo $abstract['annotations']['es']['abstract']; ?>
                  </div>
                  <br>           
                  <i><a target="_new" href="http://scielo.isciii.es/scielo.php?script=sci_abstract&pid=<?php echo $document_id;?>&lng=es&nrm=iso&tlng=es">Documento en ScieLO (espa&ntilde;ol)</a></i>
                </div>                  
                <div id="abstract-en">
                  <div class="card-title">
                      <h4><i><?php echo $abstract['title']['en']; ?></i></h4>
                  </div>              
                  <div id="abstract">
                    <?php echo $abstract['annotations']['en']['abstract']; ?>                  
                  </div>
                  <br>
                  <i><a target="_new" href="http://scielo.isciii.es/scielo.php?script=sci_abstract&pid=<?php echo $document_id;?>&lng=en&nrm=iso&tlng=en">Documento en ScieLO (ingl&eacute;s)</a></i>                     
                </div>                                  
                <br><br>
                <div class="download-json" id="download-json-es" <?php if ($lang_abstract=='en') echo 'style="display:none;"'; ?>>
                  <a href="get_json.php?id=<?php echo $document_id;?>&lang=es" download><img class="image-json" src="images/json.png"></a>
                </div>
                <div class="download-json" id="download-json-en" <?php if ($lang_abstract=='es') echo 'style="display:none;"'; ?>>
                  <a href="get_json.php?id=<?php echo $document_id;?>&lang=en" download><img class="image-json" src="images/json.png"></a>
                </div>                
                <div align="right">
                    <a id="button-es" class="button <?php if ($lang_abstract=='en') echo 'button-outline'; ?>" onClick="changeLanguage();">Espa&ntilde;ol</a>
                    <a id="button-en" class="button <?php if ($lang_abstract=='es') echo 'button-outline'; ?>" onClick="changeLanguage();">Ingl&eacute;s</a>
                </div>
              </div>
            </div>
          </div>
    <?php
    }
    
    // ------------------------------------- Search results -------------------------------------
    if ($search_results) {
    ?>         
			<!--Tables-->
          <div id="search-results">
            <h5 class="mt-2">Resultados de b&uacute;squeda: <i><?php echo $search_string; ?></i></h5><a class="anchor" name="tables"></a>
                <div id="scielo-docs" class="row grid-responsive">
                    <div class="column ">
                        <div class="card">
                            <div class="card-title">
                                <h4>Seleccione un documento para ver sus res&uacute;menes...</h4>
                            </div>
                            <div class="card-block">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>T&iacute;tulo</th>
                                            <th>Autores</th>
                                            <th>Fuente</th>
                                            <th>Fecha</th>
                                            <th>Palabras clave</th>                                            
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php                                    
                                    foreach ($search_results as $document) {
                                      $document_id = $document['_id'];
                                      $date = isset($document['_source']['date']) ? $document['_source']['date'] : '';
                                      $source = isset($document['_source']['source']) ? $document['_source']['source'] : '';
                                      $authors = isset($document['_source']['authors']) ? $document['_source']['authors'] : array();        
                                      $keywords = isset($document['_source']['keywords']) ? $document['_source']['keywords'] : array();                
                                      $title = isset($document['_source']['title'][$lang]) ? $document['_source']['title'][$lang] : '';   
                                    ?> 
                                      <tr>
                                          <td><a class="document-title" href="index.php?action=annotations&id=<?php echo $document_id;?>&lang=<?php echo $lang;?>&search=<?php echo urlencode($search_string); ?>"><?php echo $title; ?></td>
                                          <td><?php echo implode('; ', $authors); ?></td>
                                          <td><?php echo $source; ?></td>
                                          <td><?php echo $date; ?></td>
                                          <td><?php echo implode(', ', $keywords); ?></td>                                          
                                      </tr>
                                    <?php                                    
                                    }
                                    ?>                                     
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>  
            </div>            
    <?php
    }
    ?> 						
			<br><br><br><br><br>
			<p class="credit"><small>HTML5 Admin Template by <a href="https://www.medialoot.com">Medialoot</a><small></p>
		</section>
	</div>

</body>
</html> 