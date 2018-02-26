function submitSearchForm() {
    document.getElementById("search-form").submit();
}

function changeLanguage() {
    if (lang_abstract == 'en') {
        hide_lang = 'es';
        show_lang = 'en';  
        lang_abstract = 'es';
    }
    else {
        hide_lang = 'en';
        show_lang = 'es';  
        lang_abstract = 'en';        
    }
    $('#abstract-'+hide_lang).hide();    
    $('#abstract-'+show_lang).show();
    $('#download-json-'+hide_lang).hide();
    $('#download-json-'+show_lang).show();    
    $('#button-'+hide_lang).addClass('button-outline');   
    $('#button-'+show_lang).removeClass('button-outline');  
}

function changeColorDefinitions() {
    $.each(['es', 'en'], function(j, lang) {    
        concept_nums = have_definitions[lang];
        $.each(concept_nums, function(i, concept_num) {
            $('#tooltip-'+lang+'-'+concept_num).addClass('has-definition');    
        }); 
    });
}

function showMoreLess(divId) {
    $('#'+divId).toggle();
}