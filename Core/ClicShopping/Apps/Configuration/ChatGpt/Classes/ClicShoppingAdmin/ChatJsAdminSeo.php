<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin;

/**
 * Generates a JavaScript script snippet to dynamically append buttons and handle AJAX requests for SEO default H1 titles.
 *
 * @param string $content HTML content for the button.
 * @param string $urlMultilanguage URL for retrieving language name via AJAX.
 * @param string $translate_language Translation language text for the button functionality.
 * @param string $question_title Question title used in the AJAX request message generation.
 * @param string $store_name Store name for the message context.
 * @param string $url URL for sending the AJAX request to process messages.
 * @return string JavaScript snippet for dynamic functionality.
 */
class ChatJsAdminSeo
{
  /**
   * @param string $content
   * @param string $urlMultilanguage
   * @param string $translate_language
   * @param string $question_title
   * @param string $store_name
   * @param string $url
   * @return string
   */
  public static function getInfoSeoDefaultTitleH1(string $content, string $urlMultilanguage, string $translate_language, string $question_title, string $store_name, string $url)
  {
    $script = "
        <script defer>
          $('[id^=\"seo_default_title_h\"]').each(function(index) {
            let inputId = $(this).attr('id');
            let regex = /(\d+)/g;
            let idSeoDefaultTitleH = regex.exec(inputId)[0];
          
            let language_id = parseInt(idSeoDefaultTitleH);
            let button = '{$content}';
            let newButton = $(button).attr('data-index', index);
          
            // Envoi d'une requête AJAX pour récupérer le nom de la langue
            let self = this;
            $.ajax({
              url: '{$urlMultilanguage}',
              data: {id: language_id},
              success: function(language_name) {
                  let questionResponse = '{$translate_language}' + ' ' + language_name + ' : ' + '{$question_title}' + ' ' + '{$store_name}';
                newButton.click(function() {
                  let message = questionResponse;
                  let engine = $('#engine').val();
          
                  $.ajax({
                    url: '{$url}',
                    type: 'POST',
                    data: {message: message, engine: engine},
                    success: function(data) {
                    $('#chatGpt-output-input').val(data);
                    $('#seo_default_title_h_' + idSeoDefaultTitleH).val(data);
                  },
                    error: function(xhr, status, error) {
                    console.log(xhr.responseText);
                  }
                  });
                });
          
                if (newButton) {
                  $(self).append(newButton);
                }
              }
            });
          });
        </script>";

    return $script;
  }

  /**
   * @param string $content
   * @param string $urlMultilanguage
   * @param string $translate_language
   * @param string $question_title
   * @param string $store_name
   * @param string $url
   * @return string
   */
  public static function getInfoSeoDefaultTitle(string $content, string $urlMultilanguage, string $translate_language, string $question_title, string $store_name, string $url)
  {
    $script = "
      <script defer>
    $('[id^=\"seo_default_title_tag\"]').each(function(index) {
      let inputId = $(this).attr('id');
      let regex = /(\d+)/g;
      let idSeoDefaultLanguageTitle = regex.exec(inputId)[0];
    
      let language_id = parseInt(idSeoDefaultLanguageTitle);
      let button = '{$content}';
      let newButton = $(button).attr('data-index', index);
    
      // Envoi d'une requête AJAX pour récupérer le nom de la langue
      let self = this;
      $.ajax({
        url: '{$urlMultilanguage}',
        data: {id: language_id},
        success: function(language_name) {
          let questionResponse = '{$translate_language}' + ' ' + language_name + ' : ' + '{$question_title}' + ' ' + '{$store_name}';
          
          newButton.click(function() {
            let message = questionResponse;
            let engine = $('#engine').val();
    
            $.ajax({
              url: '{$url}',
              type: 'POST',
              data: {message: message, engine: engine},
              success: function(data) {
                $('#chatGpt-output-input').val(data);
                $('#seo_default_title_tag_' + idSeoDefaultLanguageTitle).val(data);
              },
              error: function(xhr, status, error) {
                console.log(xhr.responseText);
              }
            });
          });
    
          if (newButton) {
            $(self).append(newButton);
          }
        }
      });
    });            
</script>";

    return $script;
  }

  /**
   * @param string $content The content to be used for creating a new button element.
   * @param string $urlMultilanguage The URL for AJAX requests to fetch language names based on IDs.
   * @param string $translate_language The language translation prefix used in the generated message.
   * @param string $question_summary_description The summary description of the question added to the generated message.
   * @param string $store_name The store name included in the generated message.
   * @param string $url The URL for AJAX requests to send the generated message.
   * @return string The generated script containing dynamic JavaScript code for handling SEO default descriptions.
   */
  public static function getInfoSeoDefaultDescription(string $content, string $urlMultilanguage, string $translate_language, string $question_summary_description, string $store_name, string $url)
  {
    $script = "
      <script defer>
      $('[id^=\"seo_default_desc_tag\"]').each(function(index) {
        let button = '{$content}';
        let newButton = $(button).attr('data-index', index);
      
        let textareaId = $(this).find('textarea').attr('id'); // Récupérer l'id du textarea pour l'itération actuelle
        // Vérifier si le textarea a été trouvé
        if (textareaId !== undefined) {
          let regex = /(\d+)/g;
          let idSeoDefaultDescription = regex.exec(textareaId)[0];
        
          let language_id = parseInt(idSeoDefaultDescription);
        
          // Envoi d'une requête AJAX pour récupérer le nom de la langue
          let self = this;
          $.ajax({
            url: '{$urlMultilanguage}',
            data: {id: language_id},
            success: function(language_name) {
              let questionResponse = '{$translate_language}' + ' ' + language_name + ' : ' +  '{$question_summary_description}' + ' ' + '{$store_name}';
              
              newButton.click(function() {
                let message = questionResponse;
                let engine = $('#engine').val();
        
                $.ajax({
                  url: '{$url}',
                  type: 'POST',
                  data: {message: message, engine: engine},
                  success: function(data) {
                    $('#chatGpt-output-input').val(data);
                    $('#seo_defaut_language_description_' + idSeoDefaultDescription).val(data);
                  },
                  error: function(xhr, status, error) {
                    console.log(xhr.responseText);
                  }
                });
              });
        
              if (newButton) {
                $(self).append(newButton);
              }
            }
          });
        }
      });
      </script>";

    return $script;
  }

  /**
   * @param string $content
   * @param string $urlMultilanguage
   * @param string $translate_language
   * @param string $question_keywords
   * @param string $store_name
   * @param string $url
   * @return string
   */
  public static function getInfoSeoDefaultKeywords(string $content, string $urlMultilanguage, string $translate_language, string $question_keywords, string $store_name, string $url)
  {
    $script = "
      <script defer>    
        $('[id^=\"seo_defaut_language_keywords\"]').each(function(index) {
          let inputId = $(this).attr('id');
          let regex = /(\d+)/g;
          let IdSeoDefautLanguageKeywords = regex.exec(inputId)[0];
        
          let language_id = parseInt(IdSeoDefautLanguageKeywords);
          let button = '{$content}';
          let newButton = $(button).attr('data-index', index);
        
          // Envoi d'une requête AJAX pour récupérer le nom de la langue
          let self = this;
          $.ajax({
            url: '{$urlMultilanguage}',
            data: {id: language_id},
            success: function(language_name) {
              let questionResponse = '{$translate_language}' + ' ' + language_name + ' : ' + '{$question_keywords}' + ' ' + '{$store_name}';
              
              newButton.click(function() {
                let message = questionResponse;
                let engine = $('#engine').val();
        
                $.ajax({
                  url: '{$url}',
                  type: 'POST',
                  data: {message: message, engine: engine},
                  success: function(data) {
                    $('#chatGpt-output-input').val(data);
                    $('#seo_defaut_language_keywords_' + IdSeoDefautLanguageKeywords).val(data);
                  },
                  error: function(xhr, status, error) {
                    console.log(xhr.responseText);
                  }
                });
              });
        
              if (newButton) {
                $(self).append(newButton);
              }
            } 
          });
        });      
       </script>";

    return $script;
  }

  /**
   * @param string $content The HTML content or button element used for interaction.
   * @param string $urlMultilanguage The URL for the AJAX request to retrieve language information.
   * @param string $translate_language The translation language to be used in the response.
   * @param string $question_tag The tag or prompt associated with the question being handled.
   * @param string $store_name The name of the store to include in the question response.
   * @param string $url The URL for the AJAX request to process and return the final response.
   * @return string The generated JavaScript code as a string to handle default footer language interaction.
   */
  public static function getInfoSeoDefaultFooter(string $content, string $urlMultilanguage, string $translate_language, string $question_tag, string $store_name, string $url)
  {
    $script = "
      <script defer>   
        $('[id^=\"seo_defaut_language_footer\"]').each(function(index) {
          let inputId = $(this).attr('id');
          let regex = /(\d+)/g;
          let IdSeoDefautLanguageFooter = regex.exec(inputId)[0];
        
          let language_id = parseInt(IdSeoDefautLanguageFooter);
          let button = '{$content}';
          let newButton = $(button).attr('data-index', index);
        
          // Envoi d'une requête AJAX pour récupérer le nom de la langue
          let self = this;
          $.ajax({
            url: '{$urlMultilanguage}',
            data: {id: language_id},
            success: function(language_name) {
              let questionResponse = '{$translate_language}' + ' ' + language_name + ' : ' + '{$question_tag}' + ' ' + '{$store_name}';
              
              newButton.click(function() {
                let message = questionResponse;
                let engine = $('#engine').val();
        
                $.ajax({
                  url: '{$url}',
                  type: 'POST',
                  data: {message: message, engine: engine},
                  success: function(data) {
                    $('#chatGpt-output-input').val(data);
                    $('#seo_defaut_language_footer_' + IdSeoDefautLanguageFooter).val(data);
                  },
                  error: function(xhr, status, error) {
                    console.log(xhr.responseText);
                  }
                });
              });
        
              if (newButton) {
                $(self).append(newButton);
              }
            }
          });
        });     
     </script>";

    return $script;
  }
//------------------------------------------------------
// product Description
//------------------------------------------------------
  /**
   * @param string $content
   * @param string $urlMultilanguage
   * @param string $translate_language
   * @param string $question_title
   * @param string $store_name
   * @param string $url
   * @return string
   */
  public static function getInfoSeoProductDescriptionTitle(string $content, string $urlMultilanguage, string $translate_language, string $question_title, string $store_name, string $url)
  {
    $script = "
      <script defer> 
        $('[id^=\"seo_product_description_title_tag\"]').each(function(index) {
          let inputId = $(this).attr('id');
          let regex = /(\d+)/g;
          let idSeoProductDescriptionTitle = regex.exec(inputId)[0];
        
          let language_id = parseInt(idSeoProductDescriptionTitle);
          let button = '{$content}';
          let newButton = $(button).attr('data-index', index);
        
          // Envoi d'une requête AJAX pour récupérer le nom de la langue
          let self = this;
          $.ajax({
            url: '{$urlMultilanguage}',
            data: {id: language_id},
            success: function(language_name) {
              let questionResponse = '{$translate_language}' + ' ' + language_name + ' : ' + '{$question_title}' + ' ' + '{$store_name}';
              
              newButton.click(function() {
                let message = questionResponse;
                let engine = $('#engine').val();
        
                $.ajax({
                  url: '{$url}',
                  type: 'POST',
                  data: {message: message, engine: engine},
                  success: function(data) {
                    $('#chatGpt-output-input').val(data);
                    $('#seo_product_description_title_tag_' + idSeoProductDescriptionTitle).val(data);
                  },
                  error: function(xhr, status, error) {
                    console.log(xhr.responseText);
                  }
                });
              });
        
              if (newButton) {
                $(self).append(newButton);
              }
            }
          });
        });

     </script>";

    return $script;
  }

  /**
   * @param string $content
   * @param string $urlMultilanguage
   * @param string $translate_language
   * @param string $question_summary_description
   * @param string $store_name
   * @param string $url
   * @return string
   */
  public static function getInfoSeoProductDescription(string $content, string $urlMultilanguage, string $translate_language, string $question_summary_description, string $store_name, string $url)
  {
    $script = "
      <script defer> 
        $('[id^=\"seo_product_description\"]').each(function(index) {
          let button = '{$content}';
          let newButton = $(button).attr('data-index', index);
        
          let textareaId = $(this).find('textarea').attr('id'); // Récupérer l'id du textarea pour l'itération actuelle
          // Vérifier si le textarea a été trouvé
          if (textareaId !== undefined) {
            let regex = /(\d+)/g;
            let idSeoProductDescription = regex.exec(textareaId)[0];
          
            let language_id = parseInt(idSeoProductDescription);
          
            // Envoi d'une requête AJAX pour récupérer le nom de la langue
            let self = this;
            $.ajax({
              url: '{$urlMultilanguage}',
              data: {id: language_id},
              success: function(language_name) {
                let questionResponse = '{$translate_language}' + ' ' + language_name + ' : ' +  '{$question_summary_description}' + ' ' + '{$store_name}';
                
                newButton.click(function() {
                  let message = questionResponse;
                  let engine = $('#engine').val();
          
                  $.ajax({
                    url: '{$url}',
                    type: 'POST',
                    data: {message: message, engine: engine},
                    success: function(data) {
                      $('#chatGpt-output-input').val(data);
                      $('#seo_product_description_' + idSeoProductDescription).val(data);
                    },
                    error: function(xhr, status, error) {
                      console.log(xhr.responseText);
                    }
                    });
                });
          
                if (newButton) {
                  $(self).append(newButton);
                }
}
            });
          }
});
     </script>";

    return $script;
  }

  /**
   * @param string $content
   * @param string $urlMultilanguage
   * @param string $translate_language
   * @param string $question_keywords
   * @param string $store_name
   * @param string $url
   * @return string
   */
  public static function getInfoSeoProductkeywords(string $content, string $urlMultilanguage, string $translate_language, string $question_keywords, string $store_name, string $url)
  {
    $script = "
      <script defer> 
        $('[id^=\"seo_product_description_keywords\"]').each(function(index) {
          let inputId = $(this).attr('id');
          let regex = /(\d+)/g;
          let IdSeoProductDescriptionKeywords = regex.exec(inputId)[0];
        
          let language_id = parseInt(IdSeoProductDescriptionKeywords);
          let button = '{$content}';
          let newButton = $(button).attr('data-index', index);
        
          // Envoi d'une requête AJAX pour récupérer le nom de la langue
          let self = this;
          $.ajax({
            url: '{$urlMultilanguage}',
            data: {id: language_id},
            success: function(language_name) {
              let questionResponse = '{$translate_language}' + ' ' + language_name + ' : ' + '{$question_keywords}' + ' ' + '{$store_name}';
              
              newButton.click(function() {
                let message = questionResponse;
                let engine = $('#engine').val();
        
                $.ajax({
                  url: '{$url}',
                  type: 'POST',
                  data: {message: message, engine: engine},
                  success: function(data) {
                    $('#chatGpt-output-input').val(data);
                    $('#seo_product_description_keywords_' + IdSeoProductDescriptionKeywords).val(data);
                  },
                  error: function(xhr, status, error) {
                    console.log(xhr.responseText);
                  }
                 });
              });
        
              if (newButton) {
                $(self).append(newButton);
              }
            }
          });
        });
     </script>";

    return $script;
  }

//------------------------------------------------------
// New
//------------------------------------------------------
  /**
   * @param string $content
   * @param string $urlMultilanguage
   * @param string $translate_language
   * @param string $question_title
   * @param string $store_name
   * @param string $text_tag_products_new
   * @param string $url
   * @return string
   */
  public static function getInfoSeoProductsNewTitle(string $content, string $urlMultilanguage, string $translate_language, string $question_title, string $store_name, string $text_tag_products_new, string $url)
  {
    $script = "
      <script defer> 
        $('[id^=\"seo_product_new_title_tag\"]').each(function(index) {
          let inputId = $(this).attr('id');
          let regex = /(\d+)/g;
          let idSeoProductNewTitle = regex.exec(inputId)[0];
        
          let language_id = parseInt(idSeoProductNewTitle);
          let button = '{$content}';
          let newButton = $(button).attr('data-index', index);
        
          // Envoi d'une requête AJAX pour récupérer le nom de la langue
          let self = this;
          $.ajax({
            url: '{$urlMultilanguage}',
            data: {id: language_id},
            success: function(language_name) {
              let questionResponse = '{$translate_language}' + ' ' + language_name + ' : ' +'{$question_title}' + ' ' + '{$store_name}' + ' ' + '{$text_tag_products_new}';
              
              newButton.click(function() {
                let message = questionResponse;
                let engine = $('#engine').val();
        
                $.ajax({
                  url: '{$url}',
                  type: 'POST',
                  data: {message: message, engine: engine},
                  success: function(data) {
                    $('#chatGpt-output-input').val(data);
                    $('#seo_product_new_title_tag_' + idSeoProductNewTitle).val(data);
                  },
                  error: function(xhr, status, error) {
                    console.log(xhr.responseText);
                  }
                });
              });
        
              if (newButton) {
                $(self).append(newButton);
              }
            }
          });
        });
      </script>";

    return $script;
  }

  /**
   * @param string $content
   * @param string $urlMultilanguage
   * @param string $translate_language
   * @param string $question_summary_description
   * @param string $store_name
   * @param string $text_tag_products_new
   * @param string $url
   * @return string
   */
  public static function getInfoSeoProductsNewDescription(string $content, string $urlMultilanguage, string $translate_language, string $question_summary_description, string $store_name, string $text_tag_products_new, string $url)
  {
    $script = "
      <script defer> 
        $('[id^=\"seo_product_new_description\"]').each(function(index) {
          let button = '{$content}';
          let newButton = $(button).attr('data-index', index);
        
          let textareaId = $(this).find('textarea').attr('id'); // Récupérer l'id du textarea pour l'itération actuelle
          // Vérifier si le textarea a été trouvé
          if (textareaId !== undefined) {
            let regex = /(\d+)/g;
            let idSeoProductNewDescription = regex.exec(textareaId)[0];
          
            let language_id = parseInt(idSeoProductNewDescription);
          
            // Envoi d'une requête AJAX pour récupérer le nom de la langue
            let self = this;
            $.ajax({
              url: '{$urlMultilanguage}',
              data: {id: language_id},
              success: function(language_name) {
                let questionResponse = '{$translate_language}' + ' ' + language_name + ' : ' +  '{$question_summary_description}' + ' ' + '{$store_name}' + ' ' + '{$text_tag_products_new}';
                
                newButton.click(function() {
                  let message = questionResponse;
                  let engine = $('#engine').val();
          
                  $.ajax({
                    url: '{$url}',
                    type: 'POST',
                    data: {message: message, engine: engine},
                    success: function(data) {
                      $('#chatGpt-output-input').val(data);
                      $('#seo_product_new_description_' + idSeoProductNewDescription).val(data);
                    },
                    error: function(xhr, status, error) {
                      console.log(xhr.responseText);
                    }
                  });
                });
          
                if (newButton) {
                  $(self).append(newButton);
                }
              }
            });
          }
        });
      </script>";

    return $script;
  }

  /**
   * @param string $content
   * @param string $urlMultilanguage
   * @param string $translate_language
   * @param string $question_keywords
   * @param string $store_name
   * @param string $text_tag_products_new
   * @param string $url
   * @return string
   */
  public static function getInfoSeoProductsNewKeywords(string $content, string $urlMultilanguage, string $translate_language, string $question_keywords, string $store_name, string $text_tag_products_new, string $url)
  {
    $script = "
      <script defer> 
      $('[id^=\"seo_product_new_keywords\"]').each(function(index) {
        let inputId = $(this).attr('id');
        let regex = /(\d+)/g;
        let IdSeoproductNewKeywords = regex.exec(inputId)[0];
      
        let language_id = parseInt(IdSeoproductNewKeywords);
        let button = '{$content}';
        let newButton = $(button).attr('data-index', index);
      
        // Envoi d'une requête AJAX pour récupérer le nom de la langue
        let self = this;
        $.ajax({
          url: '{$urlMultilanguage}',
          data: {id: language_id},
          success: function(language_name) {
            let questionResponse = '{$translate_language}' + ' ' + language_name + ' : ' + '{$question_keywords}' + ' ' + '{$store_name}' + ' ' + '{$text_tag_products_new}';
            
            newButton.click(function() {
              let message = questionResponse;
              let engine = $('#engine').val();
      
              $.ajax({
                url: '{$url}',
                type: 'POST',
                data: {message: message, engine: engine},
                success: function(data) {
                  $('#chatGpt-output-input').val(data);
                  $('#seo_product_new_keywords_' + IdSeoproductNewKeywords).val(data);
                },
                error: function(xhr, status, error) {
                  console.log(xhr.responseText);
                }
              });
            });
      
            if (newButton) {
              $(self).append(newButton);
            }
          }
        });
      });      
      </script>";

    return $script;
  }

//------------------------------------------------------
// Specials
//------------------------------------------------------
  /**
   * @param string $content
   * @param string $urlMultilanguage
   * @param string $translate_language
   * @param string $question_title
   * @param string $store_name
   * @param string $text_tag_specials
   * @param string $url
   * @return string
   */
  public static function getInfoSeoSpecialsTitle(string $content, string $urlMultilanguage, string $translate_language, string $question_title, string $store_name, string $text_tag_specials, string $url)
  {
    $script = "
      <script defer> 
        $('[id^=\"seo_special_title_tag\"]').each(function(index) {
          let inputId = $(this).attr('id');
          let regex = /(\d+)/g;
          let idSeoSpecialTitle = regex.exec(inputId)[0];
        
          let language_id = parseInt(idSeoSpecialTitle);
          let button = '{$content}';
          let newButton = $(button).attr('data-index', index);
        
          // Envoi d'une requête AJAX pour récupérer le nom de la langue
          let self = this;
          $.ajax({
            url: '{$urlMultilanguage}',
            data: {id: language_id},
            success: function(language_name) {
              let questionResponse = '{$translate_language}' + ' ' + language_name + ' : ' + '{$question_title}' + ' ' + '{$store_name}' + ' ' + '{$text_tag_specials}';
              
              newButton.click(function() {
                let message = questionResponse;
                let engine = $('#engine').val();
        
                $.ajax({
                  url: '{$url}',
                  type: 'POST',
                  data: {message: message, engine: engine},
                  success: function(data) {
                    $('#chatGpt-output-input').val(data);
                    $('#seo_special_title_tag_' + idSeoSpecialTitle).val(data);
                  },
                  error: function(xhr, status, error) {
                    console.log(xhr.responseText);
                  }
});
              });
        
              if (newButton) {
                $(self).append(newButton);
              }
}
          });
        });
      </script>";

    return $script;
  }

  /**
   * Generates a script that dynamically handles SEO special descriptions for multilingual environments.
   *
   * @param string $content The HTML content for the button to be appended.
   * @param string $urlMultilanguage The URL used to retrieve the language name based on the language ID.
   * @param string $translate_language The translated language name prefix for the generated message.
   * @param string $question_summary_description The base description used in the generated message.
   * @param string $store_name The name of the store to be included in the generated message.
   * @param string $text_tag_specials A tag or identifier for the special description, used in the generated message.
   * @param string $url The URL for sending the AJAX request to process and generate the final description.
   *
   * @return string The complete script to enable dynamic SEO special description handling.
   */
  public static function getInfoSeoSpecialsDescription(string $content, string $urlMultilanguage, string $translate_language, string $question_summary_description, string $store_name, string $text_tag_specials, string $url)
  {
    $script = "
      <script defer> 
$('[id^=\"seo_special_description\"]').each(function(index) {
  let button = '{$content}';
  let newButton = $(button).attr('data-index', index);

  let textareaId = $(this).find('textarea').attr('id'); // Récupérer l'id du textarea pour l'itération actuelle
  // Vérifier si le textarea a été trouvé
  if (textareaId !== undefined) {
    let regex = /(\d+)/g;
    let idSeoSpecialDescription = regex.exec(textareaId)[0];
  
    let language_id = parseInt(idSeoSpecialDescription);
  
    // Envoi d'une requête AJAX pour récupérer le nom de la langue
    let self = this;
    $.ajax({
      url: '{$urlMultilanguage}',
      data: {id: language_id},
      success: function(language_name) {
        let questionResponse = '{$translate_language}' + ' ' + language_name + ' : ' +  '{$question_summary_description}' + ' ' + '{$store_name}' + ' ' + '{$text_tag_specials}';
        
        newButton.click(function() {
          let message = questionResponse;
          let engine = $('#engine').val();
  
          $.ajax({
            url: '{$url}',
            type: 'POST',
            data: {message: message, engine: engine},
            success: function(data) {
              $('#chatGpt-output-input').val(data);
              $('#seo_special_description_' + idSeoSpecialDescription).val(data);
            },
            error: function(xhr, status, error) {
              console.log(xhr.responseText);
            }
          });
        });
  
        if (newButton) {
          $(self).append(newButton);
        }
      }
    });
  }
});
     </script>";

    return $script;
  }

  /**
   * Generates a script with functionality to dynamically handle SEO special keywords
   * input fields and AJAX requests to update content based on language and other parameters.
   *
   * @param string $content HTML content for the button to trigger the interaction.
   * @param string $urlMultilanguage The URL to fetch the language name dynamically.
   * @param string $translate_language The translated language string.
   * @param string $question_keywords The keywords or question string to be processed.
   * @param string $store_name The name of the store used in the generated output.
   * @param string $text_tag_specials Additional tag or text for special keywords.
   * @param string $url The URL to handle POST requests for updating keywords.
   * @return string The generated script as a string.
   */
  public static function getInfoSeoSpecialsKeywords(string $content, string $urlMultilanguage, string $translate_language, string $question_keywords, string $store_name, string $text_tag_specials, string $url)
  {
    $script = "
      <script defer> 
      $('[id^=\"seo_special_keywords\"]').each(function(index) {
        let inputId = $(this).attr('id');
        let regex = /(\d+)/g;
        let IdSeoSpecialKeywords = regex.exec(inputId)[0];
      
        let language_id = parseInt(IdSeoSpecialKeywords);
        let button = '{$content}';
        let newButton = $(button).attr('data-index', index);
      
        // Envoi d'une requête AJAX pour récupérer le nom de la langue
        let self = this;
        $.ajax({
          url: '{$urlMultilanguage}',
          data: {id: language_id},
          success: function(language_name) {
            let questionResponse = '{$translate_language}' + ' ' + language_name + ' : ' + '{$question_keywords}' + ' ' + '{$store_name}' + ' ' + '{$text_tag_specials}';
            
            newButton.click(function() {
              let message = questionResponse;
              let engine = $('#engine').val();
      
              $.ajax({
                url: '{$url}',
                type: 'POST',
                data: {message: message, engine: engine},
                success: function(data) {
                  $('#chatGpt-output-input').val(data);
                  $('#seo_special_keywords_' + IdSeoSpecialKeywords).val(data);
                },
                error: function(xhr, status, error) {
                  console.log(xhr.responseText);
                }
});
            });
      
            if (newButton) {
              $(self).append(newButton);
            }
}
        });
      });
      </script>";

    return $script;
  }

//------------------------------------------------------
// Reviews
//------------------------------------------------------
  /**
   *
   */
  public static function getInfoSeoReviewsTitle(string $content, string $urlMultilanguage, string $translate_language, string $question_title, string $store_name, string $text_tag_review, string $url)
  {
    $script = "
      <script defer>
      $('[id^=\"seo_review_title_tag\"]').each(function(index) {
        let inputId = $(this).attr('id');
        let regex = /(\d+)/g;
        let idSeoReviewTitle = regex.exec(inputId)[0];
      
        let language_id = parseInt(idSeoReviewTitle);
        let button = '{$content}';
        let newButton = $(button).attr('data-index', index);
      
        // Envoi d'une requête AJAX pour récupérer le nom de la langue
        let self = this;
        $.ajax({
          url: '{$urlMultilanguage}',
          data: {id: language_id},
          success: function(language_name) {
            let questionResponse = '{$translate_language}' + ' ' + language_name + ' : ' + '{$question_title}' + ' ' + '{$store_name}' + ' ' + '{$text_tag_review}';
            
            newButton.click(function() {
              let message = questionResponse;
              let engine = $('#engine').val();
      
              $.ajax({
                url: '{$url}',
                type: 'POST',
                data: {message: message, engine: engine},
                success: function(data) {
                  $('#chatGpt-output-input').val(data);
                  $('#seo_review_title_tag_' + idSeoReviewTitle).val(data);
                },
                error: function(xhr, status, error) {
                  console.log(xhr.responseText);
                }
});
            });
      
            if (newButton) {
              $(self).append(newButton);
            }
}
        });
      });
      </script>";

    return $script;
  }

  /**
   * Generates a script that dynamically handles SEO review description interactions with AJAX requests.
   *
   * @param string $content HTML content for the button.
   * @param string $urlMultilanguage URL to retrieve the language name via AJAX.
   * @param string $translate_language Translation text for the language.
   * @param string $question_summary_description Description text template for reviews.
   * @param string $store_name Name of the store.
   * @param string $text_tag_review Text tag associated with the review.
   * @param string $url URL to send the AJAX request for generating responses.
   *
   * @return string A script that enables dynamic functionality for SEO review descriptions.
   */
  public static function getInfoSeoReviewsDescription(string $content, string $urlMultilanguage, string $translate_language, string $question_summary_description, string $store_name, string $text_tag_review, string $url)
  {
    $script = "
      <script defer>
        $('[id^=\"seo_review_description\"]').each(function(index) {
          let button = '{$content}';
          let newButton = $(button).attr('data-index', index);
        
          let textareaId = $(this).find('textarea').attr('id'); // Récupérer l'id du textarea pour l'itération actuelle
          // Vérifier si le textarea a été trouvé
          if (textareaId !== undefined) {
            let regex = /(\d+)/g;
            let idSeoReviewDescription = regex.exec(textareaId)[0];
          
            let language_id = parseInt(idSeoReviewDescription);
          
            // Envoi d'une requête AJAX pour récupérer le nom de la langue
            let self = this;
            $.ajax({
              url: '{$urlMultilanguage}',
              data: {id: language_id},
              success: function(language_name) {
                let questionResponse = '{$translate_language}' + ' ' + language_name + ' : ' +  '{$question_summary_description}' + ' ' + '{$store_name}' + ' ' + '{$text_tag_review}';
                
                newButton.click(function() {
                  let message = questionResponse;
                  let engine = $('#engine').val();
          
                  $.ajax({
                    url: '{$url}',
                    type: 'POST',
                    data: {message: message, engine: engine},
                    success: function(data) {
                      $('#chatGpt-output-input').val(data);
                      $('#seo_review_description_' + idSeoReviewDescription).val(data);
                    },
                    error: function(xhr, status, error) {
                      console.log(xhr.responseText);
                    }
});
                });
          
                if (newButton) {
                  $(self).append(newButton);
                }
}
            });
          }
});
      </script>";

    return $script;
  }

  /**
   * Generates a script to handle SEO review keywords functionality on a webpage.
   *
   * @param string $content The HTML content or button element to be dynamically appended.
   * @param string $urlMultilanguage The URL for retrieving the language name via AJAX.
   * @param string $translate_language The text representing the translate language prefix.
   * @param string $question_keywords The keywords related to the question or review.
   * @param string $store_name The name of the store to be included in the response.
   * @param string $text_tag_review The text associated with the review tag to create dynamic responses.
   * @param string $url The URL for sending AJAX POST requests with the generated message.
   *
   * @return string The generated script as a string that facilitates SEO review keywords handling.
   */
  public static function getInfoSeoReviewsKeywords(string $content, string $urlMultilanguage, string $translate_language, string $question_keywords, string $store_name, string $text_tag_review, string $url)
  {
    $script = "
      <script defer>
        $('[id^=\"seo_review_keywords\"]').each(function(index) {
          let inputId = $(this).attr('id');
          let regex = /(\d+)/g;
          let IdSeoReviewKeywords = regex.exec(inputId)[0];
        
          let language_id = parseInt(IdSeoReviewKeywords);
          let button = '{$content}';
          let newButton = $(button).attr('data-index', index);
        
          // Envoi d'une requête AJAX pour récupérer le nom de la langue
          let self = this;
          $.ajax({
            url: '{$urlMultilanguage}',
            data: {id: language_id},
            success: function(language_name) {
              let questionResponse = '{$translate_language}' + ' ' + language_name + ' : ' + '{$question_keywords}' + ' ' + '{$store_name}' + ' ' + '{$text_tag_review}';
              
              newButton.click(function() {
                let message = questionResponse;
                let engine = $('#engine').val();
        
                $.ajax({
                  url: '{$url}',
                  type: 'POST',
                  data: {message: message, engine: engine},
                  success: function(data) {
                    $('#chatGpt-output-input').val(data);
                    $('#seo_review_keywords_' + IdSeoReviewKeywords).val(data);
                  },
                  error: function(xhr, status, error) {
                    console.log(xhr.responseText);
                  }
});
              });
        
              if (newButton) {
                $(self).append(newButton);
              }
}
          });
        });
      </script>";

    return $script;
  }

//------------------------------------------------------
// Favorites
//------------------------------------------------------
  /**
   *
   */
  public static function getInfoFavoritesTitle(string $content, string $urlMultilanguage, string $translate_language, string $question_title, string $store_name, string $text_tag_favorite, string $url)
  {
    $script = "
      <script defer>
      $('[id^=\"seo_favorite_title_tag\"]').each(function(index) {
        let inputId = $(this).attr('id');
        let regex = /(\d+)/g;
        let IdSeoFavoriteTitle = regex.exec(inputId)[0];
      
        let language_id = parseInt(IdSeoFavoriteTitle);
        let button = '{$content}';
        let newButton = $(button).attr('data-index', index);
      
        // Envoi d'une requête AJAX pour récupérer le nom de la langue
        let self = this;
        $.ajax({
          url: '{$urlMultilanguage}',
          data: {id: language_id},
          success: function(language_name) {
            let questionResponse = '{$translate_language}' + ' ' + language_name + ' : ' + '{$question_title}' + ' ' + '{$store_name}' + ' ' + '{$text_tag_favorite}';
            
            newButton.click(function() {
              let message = questionResponse;
              let engine = $('#engine').val();
      
              $.ajax({
                url: '{$url}',
                type: 'POST',
                data: {message: message, engine: engine},
                success: function(data) {
                  $('#chatGpt-output-input').val(data);
                  $('#seo_favorite_title_tag_' + IdSeoFavoriteTitle).val(data);
                },
                error: function(xhr, status, error) {
                  console.log(xhr.responseText);
                }
              });
            });
      
            if (newButton) {
              $(self).append(newButton);
            }
          }
        });
      });
     </script>";

    return $script;
  }

  /**
   * Generates a script for dynamically handling favorite descriptions in a multilingual context.
   *
   * @param string $content The HTML content to be dynamically appended and modified.
   * @param string $urlMultilanguage The endpoint URL for fetching the language name based on the language ID.
   * @param string $translate_language The base text used for translation in combination with the language name.
   * @param string $question_summary_description The summary or description text for the question in the message.
   * @param string $store_name The name of the store to be included in the message.
   * @param string $text_tag_favorite Additional text tag references for the favorite functionality.
   * @param string $url The endpoint URL for posting the generated message and receiving the response.
   * @return string The generated script as a string, containing JavaScript code for AJAX handling and appending elements.
   */
  public static function getInfoFavoritesDescription(string $content, string $urlMultilanguage, string $translate_language, string $question_summary_description, string $store_name, string $text_tag_favorite, string $url)
  {
    $script = "
      <script defer>
      $('[id^=\"seo_favorite_description\"]').each(function(index) {
        let button = '{$content}';
        let newButton = $(button).attr('data-index', index);
      
        let textareaId = $(this).find('textarea').attr('id'); // Récupérer l'id du textarea pour l'itération actuelle
        // Vérifier si le textarea a été trouvé
        if (textareaId !== undefined) {
          let regex = /(\d+)/g;
          let idSeoFavoritesDescription = regex.exec(textareaId)[0];
        
          let language_id = parseInt(idSeoFavoritesDescription);
        
          // Envoi d'une requête AJAX pour récupérer le nom de la langue
          let self = this;
          $.ajax({
            url: '{$urlMultilanguage}',
            data: {id: language_id},
            success: function(language_name) {
              let questionResponse = '{$translate_language}' + ' ' + language_name + ' : ' +  '{$question_summary_description}' + ' ' + '{$store_name}' + ' ' + '{$text_tag_favorite}';
              
              newButton.click(function() {
                let message = questionResponse;
                let engine = $('#engine').val();
        
                $.ajax({
                  url: '{$url}',
                  type: 'POST',
                  data: {message: message, engine: engine},
                  success: function(data) {
                    $('#chatGpt-output-input').val(data);
                    $('#seo_favorite_description_' + idSeoFavoritesDescription).val(data);
                  },
                  error: function(xhr, status, error) {
                    console.log(xhr.responseText);
                  }
});
              });
        
              if (newButton) {
                $(self).append(newButton);
              }
}
          });
        }
});
     </script>";

    return $script;
  }

  /**
   * Generates a JavaScript script for handling favorite keywords functionality, including AJAX operations
   * for translating keywords and interacting with language-specific data.
   *
   * @param string $content The HTML structure or content of the button used for interaction.
   * @param string $urlMultilanguage The URL for the AJAX request to retrieve the name of the language.
   * @param string $translate_language The translation or prefix text for the language name.
   * @param string $question_keywords The keywords or SEO question text to be displayed or processed.
   * @param string $store_name The name of the store to include in the contextual message.
   * @param string $text_tag_favorite The tag or label used to denote the favorite keywords.
   * @param string $url The URL for the AJAX request used to send and retrieve contextual data.
   *
   * @return string The generated JavaScript script as a string.
   */
  public static function getInfoFavoritesKeywords(string $content, string $urlMultilanguage, string $translate_language, string $question_keywords, string $store_name, string $text_tag_favorite, string $url)
  {
    $script = "
      <script defer>
        $('[id^=\"seo_favorite_keywords\"]').each(function(index) {
          let inputId = $(this).attr('id');
          let regex = /(\d+)/g;
          let IdSeoFavoriteKeywords = regex.exec(inputId)[0];
        
          let language_id = parseInt(IdSeoFavoriteKeywords);
          let button = '{$content}';
          let newButton = $(button).attr('data-index', index);
        
          // Envoi d'une requête AJAX pour récupérer le nom de la langue
          let self = this;
          $.ajax({
            url: '{$urlMultilanguage}',
            data: {id: language_id},
            success: function(language_name) {
              let questionResponse = '{$translate_language}' + ' ' + language_name + ' : ' + '{$question_keywords}' + ' ' + '{$store_name}' + ' ' + '{$text_tag_favorite}';
              
              newButton.click(function() {
                let message = questionResponse;
                let engine = $('#engine').val();
        
                $.ajax({
                  url: '{$url}',
                  type: 'POST',
                  data: {message: message, engine: engine},
                  success: function(data) {
                    $('#chatGpt-output-input').val(data);
                    $('#seo_favorite_keywords_' + IdSeoFavoriteKeywords).val(data);
                  },
                  error: function(xhr, status, error) {
                    console.log(xhr.responseText);
                  }
});
              });
        
              if (newButton) {
                $(self).append(newButton);
              }
}
          });
        });
     </script>";

    return $script;
  }

//------------------------------------------------------
// Favorites
//------------------------------------------------------
  /**
   * Generates and returns a script for handling the "featured title" functionality with dynamic language-based responses.
   *
   * @param string $content The HTML content for the button to be added dynamically.
   * @param string $urlMultilanguage The URL for the AJAX request to fetch the language name based on the language ID.
   * @param string $translate_language The translated language string to be used in the question response.
   * @param string $question_title The title of the question to be appended in the response.
   * @param string $store_name The name of the store to be included in the response.
   * @param string $text_tag_featured The specific tag for "featured" to be added as part of the response text.
   * @param string $url The URL for the AJAX request to retrieve final processed data.
   *
   * @return string The generated script as a string for executing the dynamic "featured title" functionality.
   */
  public static function getInfoFeaturedTitle(string $content, string $urlMultilanguage, string $translate_language, string $question_title, string $store_name, string $text_tag_featured, string $url)
  {
    $script = "
      <script defer>
        $('[id^=\"seo_featured_title_tag\"]').each(function(index) {
          let inputId = $(this).attr('id');
          let regex = /(\d+)/g;
          let IdSeoFeaturedTitle = regex.exec(inputId)[0];
        
          let language_id = parseInt(IdSeoFeaturedTitle);
          let button = '{$content}';
          let newButton = $(button).attr('data-index', index);
        
          // Envoi d'une requête AJAX pour récupérer le nom de la langue
          let self = this;
          $.ajax({
            url: '{$urlMultilanguage}',
            data: {id: language_id},
            success: function(language_name) {
              let questionResponse = '{$translate_language}' + ' ' + language_name + ' : ' + '{$question_title}' + ' ' + '{$store_name}' + ' ' + '{$text_tag_featured}';
              
              newButton.click(function() {
                let message = questionResponse;
                let engine = $('#engine').val();
        
                $.ajax({
                  url: '{$url}',
                  type: 'POST',
                  data: {message: message, engine: engine},
                  success: function(data) {
                    $('#chatGpt-output-input').val(data);
                    $('#seo_featured_title_tag_' + IdSeoFeaturedTitle).val(data);
                  },
                  error: function(xhr, status, error) {
                    console.log(xhr.responseText);
                  }
});
              });
        
              if (newButton) {
                $(self).append(newButton);
              }
}
          });
        });
     </script>";

    return $script;
  }

  /**
   * Generates and returns a script for enhancing the functionality of featured description sections
   * by appending user-action-triggered dynamic content and managing AJAX requests.
   *
   * @param string $content The HTML content for the button element to be included in each section.
   * @param string $urlMultilanguage The URL to fetch language-specific names through AJAX.
   * @param string $translate_language The base translation text for the featured description.
   * @param string $question_summary_description The summary description of the question or content.
   * @param string $store_name The name of the store to be included in the generated response.
   * @param string $text_tag_featured The featured text tag to append to the response.
   * @param string $url The URL for posting AJAX requests containing the generated information.
   * @return string The generated JavaScript script for dynamically handling the featured descriptions.
   */
  public static function getInfoFeaturedDescription(string $content, string $urlMultilanguage, string $translate_language, string $question_summary_description, string $store_name, string $text_tag_featured, string $url)
  {
    $script = "
      <script defer>
        $('[id^=\"seo_featured_description\"]').each(function(index) {
          let button = '{$content}';
          let newButton = $(button).attr('data-index', index);
        
          let textareaId = $(this).find('textarea').attr('id'); // Récupérer l'id du textarea pour l'itération actuelle
          // Vérifier si le textarea a été trouvé
          if (textareaId !== undefined) {
            let regex = /(\d+)/g;
            let idSeoFeaturedDescription = regex.exec(textareaId)[0];
          
            let language_id = parseInt(idSeoFeaturedDescription);
          
            // Envoi d'une requête AJAX pour récupérer le nom de la langue
            let self = this;
            $.ajax({
              url: '{$urlMultilanguage}',
              data: {id: language_id},
              success: function(language_name) {
                let questionResponse = '{$translate_language}' + ' ' + language_name + ' : ' +  '{$question_summary_description}' + ' ' + '{$store_name}' + ' ' + '{$text_tag_featured}';
                
                newButton.click(function() {
                  let message = questionResponse;
                  let engine = $('#engine').val();
          
                  $.ajax({
                    url: '{$url}',
                    type: 'POST',
                    data: {message: message, engine: engine},
                    success: function(data) {
                      $('#chatGpt-output-input').val(data);
                      $('#seo_featured_description_' + idSeoFeaturedDescription).val(data);
                    },
                    error: function(xhr, status, error) {
                      console.log(xhr.responseText);
                    }
                  });
                });
          
                if (newButton) {
                  $(self).append(newButton);
                }
              }
            });
          }
        });
      </script>";

    return $script;
  }

  /**
   * Generates a dynamic JavaScript script to handle SEO featured keywords functionality for multilanguage support.
   *
   * @param string $content The HTML content for the button used in the script.
   * @param string $urlMultilanguage The URL for fetching language information via AJAX.
   * @param string $translate_language A string used to prefix the translated language name.
   * @param string $question_keywords Keywords or phrases related to the question that will be displayed in the response.
   * @param string $store_name The name of the store, included as part of the constructed response.
   * @param string $text_tag_featured The tag or text used to signify the featured keywords.
   * @param string $url The URL to which the AJAX POST request is made to process the data.
   *
   * @return string The generated JavaScript code as a string.
   */
  public static function getInfoFeaturedKeywords(string $content, string $urlMultilanguage, string $translate_language, string $question_keywords, string $store_name, string $text_tag_featured, string $url)
  {
    $script = "
      <script defer>
        $('[id^=\"seo_featured_keywords\"]').each(function(index) {
          let inputId = $(this).attr('id');
          let regex = /(\d+)/g;
          let IdSeoFeaturedKeywords = regex.exec(inputId)[0];
        
          let language_id = parseInt(IdSeoFeaturedKeywords);
          let button = '{$content}';
          let newButton = $(button).attr('data-index', index);
        
          // Envoi d'une requête AJAX pour récupérer le nom de la langue
          let self = this;
          $.ajax({
            url: '{$urlMultilanguage}',
            data: {id: language_id},
            success: function(language_name) {
              let questionResponse = '{$translate_language}' + ' ' + language_name + ' : ' + '{$question_keywords}' + ' ' + '{$store_name}' + ' ' + '{$text_tag_featured}';
              
              newButton.click(function() {
                let message = questionResponse;
                let engine = $('#engine').val();
        
                $.ajax({
                  url: '{$url}',
                  type: 'POST',
                  data: {message: message, engine: engine},
                  success: function(data) {
                    $('#chatGpt-output-input').val(data);
                    $('#seo_featured_keywords_' + IdSeoFeaturedKeywords).val(data);
                  },
                  error: function(xhr, status, error) {
                    console.log(xhr.responseText);
                  }
                });
              });
        
              if (newButton) {
                $(self).append(newButton);
              }
            }
          });
        });
      </script>";

    return $script;
  }

//**************************************
// Categories
//**************************************
  /**
   * Generates a dynamically rendered script for handling category SEO title updates.
   *
   * @param string $content The HTML content used to create new interactive elements, such as buttons.
   * @param string $urlMultilanguage The URL used for an AJAX request to fetch the language name.
   * @param string $translate_language The translated language identifier used in generated responses.
   * @param string $question The base question or title text to include in the generated response.
   * @param string $url The URL endpoint for submitting the generated SEO title via an AJAX request.
   *
   * @return string The generated JavaScript script containing dynamic logic for interacting with and updating category SEO titles.
   */
  public static function getCategoriesSeoTitle(string $content, string $urlMultilanguage, string $translate_language, string $question, string $url)
  {
    $script = "
      <script defer>
      $('[id^=\"categories_head_title_tag\"]').each(function(index) {
        let inputId = $(this).attr('id');
        let regex = /(\d+)/g;
        let idCategoriesHeadTitleTag = regex.exec(inputId)[0];
      
        let language_id = parseInt(idCategoriesHeadTitleTag);
        let button = '{$content}';
        let newButton = $(button).attr('data-index', index);
      
        // Envoi d'une requête AJAX pour récupérer le nom de la langue
        let self = this;
        $.ajax({
          url: '{$urlMultilanguage}',
          data: {id: language_id},
          success: function(language_name) {
            let questionResponse = '{$translate_language}' + ' ' + language_name + ' : ' + '{$question}';
            
            newButton.click(function() {
              let message = questionResponse;
              let engine = $('#engine').val();
      
              $.ajax({
                url: '{$url}',
                type: 'POST',
                data: {message: message, engine: engine},
                success: function(data) {
                  $('#chatGpt-output-input').val(data);
                  $('#categories_head_title_tag_' + idCategoriesHeadTitleTag).val(data);
                },
                error: function(xhr, status, error) {
                  console.log(xhr.responseText);
                }
              });
            });
      
            if (newButton) {
              $(self).append(newButton);
            }
          }
        });
      });
       </script>";

    return $script;
  }

  /**
   * Generates and returns a JavaScript script for dynamically handling SEO description inputs
   * in a categories interface, including AJAX calls for language retrieval and responsiveness.
   *
   * @param string $content HTML button content to create dynamic elements in the interface.
   * @param string $urlMultilanguage URL to retrieve multilingual data via AJAX calls.
   * @param string $translate_language Base string used for translation messages.
   * @param string $question_summary_description Text used to generate the SEO description prompt.
   * @param string $url Endpoint URL for making AJAX requests to process the generated SEO description.
   * @return string A script tag containing the JavaScript code for managing the SEO description functionality.
   */
  public static function getCategoriesSeoDescription(string $content, string $urlMultilanguage, string $translate_language, string $question_summary_description, string $url)
  {
    $script = "
      <script defer>
  $('[id^=\"categories_head_desc_tag\"]').each(function(index) {
    let button = '{$content}';
          let newButton = $(button).attr('data-index', index);
        
          let textareaId = $(this).find('textarea').attr('id'); // Récupérer l'id du textarea pour l'itération actuelle
          // Vérifier si le textarea a été trouvé
          if (textareaId !== undefined) {
            let regex = /(\d+)/g;
            let idCategoriesSeoDescription = regex.exec(textareaId)[0];
          
            let language_id = parseInt(idCategoriesSeoDescription);
          
            // Envoi d'une requête AJAX pour récupérer le nom de la langue
            let self = this;
            $.ajax({
              url: '{$urlMultilanguage}',
              data: {id: language_id},
              success: function(language_name) {
              let questionResponse =  '{$translate_language}' + ' ' + language_name + '. ' + '{$question_summary_description}';

                newButton.click(function() {
                  let message = questionResponse;
                  let engine = $('#engine').val();
          
                  $.ajax({
                    url: '{$url}',
                    type: 'POST',
                    data: {message: message, engine: engine},
                    success: function(data) {
                    $('#chatGpt-output-input').val(data);
                    $('#categories_head_desc_tag_' + idCategoriesSeoDescription).val(data);
                  },
                    error: function(xhr, status, error) {
                    console.log(xhr.responseText);
                  }
                  });
                });
          
                if (newButton) {
                  $(self).append(newButton);
                }
              }
            });
          }
        });    
      
      
      </script>";

    return $script;
  }

  /**
   * Generates a dynamic JavaScript script for updating category SEO keywords based on language and a set of parameters.
   *
   * @param string $content The HTML content or template for the button element to trigger the update.
   * @param string $urlMultilanguage The URL endpoint for retrieving the language based on a specific identifier.
   * @param string $translate_language The translation string or label for identifying language-related details.
   * @param string $question_keywords Keywords or text related to the category that will be used in the update process.
   * @param string $url The URL endpoint for performing the AJAX request to update the SEO keywords.
   *
   * @return string The generated JavaScript script as a string.
   */
  public static function getCategoriesSeoKeywords(string $content, string $urlMultilanguage, string $translate_language, string $question_keywords, string $url)
  {
    $script = "
      <script defer>    
      $('[id^=\"categories_head_keywords_tag\"]').each(function(index) {
        let inputId = $(this).attr('id');
        let regex = /(\d+)/g;
        let idCategoriesSeoKeywords = regex.exec(inputId)[0];
      
        let language_id = parseInt(idCategoriesSeoKeywords);
        let button = '{$content}';
        let newButton = $(button).attr('data-index', index);
      
        // Envoi d'une requête AJAX pour récupérer le nom de la langue
        let self = this;
        $.ajax({
          url: '{$urlMultilanguage}',
          data: {id: language_id},
          success: function(language_name) {
            let questionResponse = '{$translate_language}' + ' ' + language_name + ' : ' + '{$question_keywords}';
            
            newButton.click(function() {
              let message = questionResponse;
              let engine = $('#engine').val();
      
              $.ajax({
                url: '{$url}',
                type: 'POST',
                data: {message: message, engine: engine},
                success: function(data) {
                  $('#chatGpt-output-input').val(data);
                  $('#categories_head_keywords_tag_' + idCategoriesSeoKeywords).val(data);
                },
                error: function(xhr, status, error) {
                  console.log(xhr.responseText);
                }
              });
            });
      
            if (newButton) {
              $(self).append(newButton);
            }
          }
        });
      });
      </script>";

    return $script;
  }

//*********************
// Manufacturer
//*********************
  /**
   * Generates a dynamic script for managing manufacturer SEO title functionality.
   *
   * @param string $content The button or HTML content to be appended dynamically.
   * @param string $urlMultilanguage The URL to fetch language information via AJAX.
   * @param string $translate_language The base translation prompt or string to be used.
   * @param string $question The question or query string to be included in the title.
   * @param string $manufacturer_name The name of the manufacturer for generating the SEO title.
   * @param string $url The URL to send the AJAX request for processing user input.
   *
   * @return string A dynamically generated script for manufacturer SEO title management.
   */
  public static function getManufacturerSeoTitle(string $content, string $urlMultilanguage, string $translate_language, string $question, string $manufacturer_name, string $url)
  {
    $script = "
      <script defer>    
        $('[id^=\"manufacturer_seo_title\"]').each(function(index) {
          let inputId = $(this).attr('id');
          let regex = /(\d+)/g;
          let idManufacturerSeoTitle = regex.exec(inputId)[0];
        
          let language_id = parseInt(idManufacturerSeoTitle);
          let button = '{$content}';
          let newButton = $(button).attr('data-index', index);
        
          // Envoi d'une requête AJAX pour récupérer le nom de la langue
          let self = this;
          $.ajax({
            url: '{$urlMultilanguage}',
            data: {id: language_id},
            success: function(language_name) {
              let questionResponse = '{$translate_language}' + ' ' + language_name + ' : ' + '{$question}' + ' ' + '{$manufacturer_name}';
              
              newButton.click(function() {
                let message = questionResponse;
                let engine = $('#engine').val();
        
                $.ajax({
                  url: '{$url}',
                  type: 'POST',
                  data: {message: message, engine: engine},
                  success: function(data) {
                    $('#chatGpt-output-input').val(data);
                    $('#manufacturer_seo_title_' + idManufacturerSeoTitle).val(data);
                  },
                  error: function(xhr, status, error) {
                    console.log(xhr.responseText);
                  }
});
              });
        
              if (newButton) {
                $(self).append(newButton);
              }
}
          });
        });    
      </script>";

    return $script;
  }

  /**
   * Generates a JavaScript script for dynamically handling manufacturer SEO descriptions.
   *
   * @param string $content The content for the button to initiate the action.
   * @param string $urlMultilanguage The URL to retrieve language-specific information via AJAX.
   * @param string $translate_language The language translation prefix for the generated description.
   * @param string $question_summary_description The summarized description for the manufacturer question.
   * @param string $manufacturer_name The name of the manufacturer.
   * @param string $url The URL endpoint to process the generated message.
   * @return string The generated JavaScript script string to be used in the frontend.
   */
  public static function getManufacturerSeoDescription(string $content, string $urlMultilanguage, string $translate_language, string $question_summary_description, string $manufacturer_name, string $url)
  {
    $script = "
      <script defer>
        $('[id^=\"manufacturer_seo_description\"]').each(function(index) {
          let button = '{$content}';
          let newButton = $(button).attr('data-index', index);
        
          let textareaId = $(this).find('textarea').attr('id'); // Récupérer l'id du textarea pour l'itération actuelle
          // Vérifier si le textarea a été trouvé
          if (textareaId !== undefined) {
            let regex = /(\d+)/g;
            let idManufacturerSeoDescription = regex.exec(textareaId)[0];
          
            let language_id = parseInt(idManufacturerSeoDescription);
          
            // Envoi d'une requête AJAX pour récupérer le nom de la langue
            let self = this;
            $.ajax({
              url: '{$urlMultilanguage}',
              data: {id: language_id},
              success: function(language_name) {
                let questionResponse = '{$translate_language}' + ' ' + language_name + ' : ' +  '{$question_summary_description}' + ' ' + '{$manufacturer_name}';
                
                newButton.click(function() {
                  let message = questionResponse;
                  let engine = $('#engine').val();
          
                  $.ajax({
                    url: '{$url}',
                    type: 'POST',
                    data: {message: message, engine: engine},
                    success: function(data) {
                      $('#chatGpt-output-input').val(data);
                      $('#manufacturer_seo_description_' + idManufacturerSeoDescription).val(data);
                    },
                    error: function(xhr, status, error) {
                      console.log(xhr.responseText);
                    }
});
                });
          
                if (newButton) {
                  $(self).append(newButton);
                }
}
            });
          }
});
      </script>";

    return $script;
  }

  /**
   * Generates a script that dynamically creates a button for each manufacturer SEO keyword input,
   * allowing users to populate the inputs with SEO-friendly keywords via AJAX requests.
   *
   * @param string $content The HTML content of the button to be added to the page.
   * @param string $urlMultilanguage The URL for AJAX requests to retrieve the language name.
   * @param string $translate_language The prefix or text describing the translation language.
   * @param string $question_keywords The base question or text related to the SEO keywords.
   * @param string $manufacturer_name The name of the manufacturer used in the SEO keywords generation.
   * @param string $url The URL for AJAX requests to process and retrieve the SEO keywords.
   * @return string The generated JavaScript code to handle dynamic button creation and AJAX integration.
   */
  public static function getManufacturerSeoKeywords(string $content, string $urlMultilanguage, string $translate_language, string $question_keywords, string $manufacturer_name, string $url)
  {
    $script = "
      <script defer>    
      $('[id^=\"manufacturer_seo_keyword\"]').each(function(index) {
        let inputId = $(this).attr('id');
        let regex = /(\d+)/g;
        let idManufacturerSeoKeywords = regex.exec(inputId)[0];
      
        let language_id = parseInt(idManufacturerSeoKeywords);
        let button = '{$content}';
        let newButton = $(button).attr('data-index', index);
      
        // Envoi d'une requête AJAX pour récupérer le nom de la langue
        let self = this;
        $.ajax({
          url: '{$urlMultilanguage}',
          data: {id: language_id},
          success: function(language_name) {
            let questionResponse = '{$translate_language}' + ' ' + language_name + ' : ' + '{$question_keywords}' + ' ' + '{$manufacturer_name}';
            
            newButton.click(function() {
              let message = questionResponse;
              let engine = $('#engine').val();
      
              $.ajax({
                url: '{$url}',
                type: 'POST',
                data: {message: message, engine: engine},
                success: function(data) {
                  $('#chatGpt-output-input').val(data);
                  $('#manufacturer_seo_keyword_' + idManufacturerSeoKeywords).val(data);
                },
                error: function(xhr, status, error) {
                  console.log(xhr.responseText);
                }
});
            });
      
            if (newButton) {
              $(self).append(newButton);
            }
}
        });
      });   
      </script>";

    return $script;
  }

//*********************
// Page Manager
//*********************
  /**
   * Generates a script that dynamically manages the SEO title for a page manager.
   *
   * @param string $content The HTML content to be used for the button element.
   * @param string $urlMultilanguage The URL used for retrieving language names via AJAX.
   * @param string $translate_language The translation text to be prefixed with the language name.
   * @param string $question The question or context text to be included in the title.
   * @param string $page_manager_name The name of the page manager to be included in the title.
   * @param string $url The URL for submitting the AJAX request with the generated title.
   *
   * @return string A script that applies the described functionality for managing SEO titles.
   */
  public static function getPageManagerSeoTitle(string $content, string $urlMultilanguage, string $translate_language, string $question, string $page_manager_name, string $url)
  {
    $script = "
      <script defer>    
        $('[id^=\"page_manager_head_title_tag\"]').each(function(index) {
          let inputId = $(this).attr('id');
          let regex = /(\d+)/g;
          let idPageManagerSeoTitle = regex.exec(inputId)[0];
        
          let language_id = parseInt(idPageManagerSeoTitle);
          let button = '{$content}';
          let newButton = $(button).attr('data-index', index);
        
          // Envoi d'une requête AJAX pour récupérer le nom de la langue
          let self = this;
          $.ajax({
            url: '{$urlMultilanguage}',
            data: {id: language_id},
            success: function(language_name) {
              let questionResponse = '{$translate_language}' + ' ' + language_name + ' : ' + '{$question}' + ' ' + '{$page_manager_name}';
              
              newButton.click(function() {
                let message = questionResponse;
                let engine = $('#engine').val();
        
                $.ajax({
                  url: '{$url}',
                  type: 'POST',
                  data: {message: message, engine: engine},
                  success: function(data) {
                    $('#chatGpt-output-input').val(data);
                    $('#page_manager_head_title_tag_' + idPageManagerSeoTitle).val(data);
                  },
                  error: function(xhr, status, error) {
                    console.log(xhr.responseText);
                  }
});
              });
        
              if (newButton) {
                $(self).append(newButton);
              }
}
          });
        });
      </script>";

    return $script;
  }

  /**
   * Generates a dynamic script to manage SEO descriptions for a page manager using AJAX requests.
   *
   * @param string $content The HTML content for the button element used in the script.
   * @param string $urlMultilanguage The URL endpoint to retrieve the language name based on the language ID.
   * @param string $translate_language The translation keyword or prefix used in the generated description.
   * @param string $question_summary_description The summary description or prompt related to the page manager.
   * @param string $page_manager_name The name of the page manager to be included in the description.
   * @param string $url The URL endpoint to handle the AJAX POST request for generating the SEO description.
   * @return string The generated script as a string.
   */
  public static function getPageManagerSeoDescription(string $content, string $urlMultilanguage, string $translate_language, string $question_summary_description, string $page_manager_name, string $url)
  {
    $script = "
      <script defer> 
        $('[id^=\"page_manager_head_desc_tag\"]').each(function(index) {
          let button = '{$content}';
          let newButton = $(button).attr('data-index', index);
        
          let textareaId = $(this).find('textarea').attr('id'); // Récupérer l'id du textarea pour l'itération actuelle
          // Vérifier si le textarea a été trouvé
          if (textareaId !== undefined) {
            let regex = /(\d+)/g;
            let idPageManagerSeoDescription = regex.exec(textareaId)[0];
          
            let language_id = parseInt(idPageManagerSeoDescription);
          
            // Envoi d'une requête AJAX pour récupérer le nom de la langue
            let self = this;
            $.ajax({
              url: '{$urlMultilanguage}',
              data: {id: language_id},
              success: function(language_name) {
                let questionResponse = '{$translate_language}' + ' ' + language_name + ' : ' +  '{$question_summary_description}' + ' ' + '{$page_manager_name}';
                
                newButton.click(function() {
                  let message = questionResponse;
                  let engine = $('#engine').val();
          
                  $.ajax({
                    url: '{$url}',
                    type: 'POST',
                    data: {message: message, engine: engine},
                    success: function(data) {
                      $('#chatGpt-output-input').val(data);
                      $('#page_manager_head_desc_tag_' + idPageManagerSeoDescription).val(data);
                    },
                    error: function(xhr, status, error) {
                      console.log(xhr.responseText);
                    }
});
                });
          
                if (newButton) {
                  $(self).append(newButton);
                }
}
            });
          }
});
      </script>";

    return $script;
  }

  /**
   * Generates a script that dynamically manages SEO keywords for a page manager using AJAX.
   *
   * @param string $content The HTML content for the button used for interaction.
   * @param string $urlMultilanguage The URL for fetching language details via AJAX.
   * @param string $translate_language The base translation text used for constructing the keywords response.
   * @param string $question_keywords The keywords associated with the page manager's question or query.
   * @param string $page_manager_name The name of the page manager to be included in the response.
   * @param string $url The endpoint URL for handling data sent during the button interaction.
   *
   * @return string The generated JavaScript script as a string.
   */
  public static function getPageManagerSeoKeywords(string $content, string $urlMultilanguage, string $translate_language, string $question_keywords, string $page_manager_name, string $url)
  {
    $script = "
      <script defer> 
        $('[id^=\"page_manager_head_keywords_tag\"]').each(function(index) {
          let inputId = $(this).attr('id');
          let regex = /(\d+)/g;
          let idPageManagerSeoKeywords = regex.exec(inputId)[0];
        
          let language_id = parseInt(idPageManagerSeoKeywords);
          let button = '{$content}';
          let newButton = $(button).attr('data-index', index);
        
          // Envoi d'une requête AJAX pour récupérer le nom de la langue
          let self = this;
          $.ajax({
            url: '{$urlMultilanguage}',
            data: {id: language_id},
            success: function(language_name) {
              let questionResponse = '{$translate_language}' + ' ' + language_name + ' : ' + '{$question_keywords}' + ' ' + '{$page_manager_name}';
              
              newButton.click(function() {
                let message = questionResponse;
                let engine = $('#engine').val();
        
                $.ajax({
                  url: '{$url}',
                  type: 'POST',
                  data: {message: message, engine: engine},
                  success: function(data) {
                    $('#chatGpt-output-input').val(data);
                    $('#page_manager_head_keywords_tag_' + idPageManagerSeoKeywords).val(data);
                  },
                  error: function(xhr, status, error) {
                    console.log(xhr.responseText);
                  }
                });
              });
        
              if (newButton) {
                $(self).append(newButton);
              }
            }
          });
        });
      </script>";

    return $script;
  }

//*********************
// Products
//*********************
  /**
   * Generates a JavaScript script that adds functionality for retrieving and updating SEO titles for products
   * through an AJAX-based mechanism.
   *
   * @param string $content The HTML content (e.g., a button) to be added for triggering the process.
   * @param string $urlMultilanguage The URL to fetch the language name based on the language ID via AJAX.
   * @param string $translate_language The translation prefix or indicator for language-related information.
   * @param string $question The base question text to be included in the SEO title generation process.
   * @param string|null $product_name Optional name of the product to be included in the SEO title if provided.
   * @param string $url The URL where the generated SEO title is sent via AJAX.
   *
   * @return string The generated JavaScript script as a string.
   */
  public static function getProductsSeoTitle(string $content, string $urlMultilanguage, string $translate_language, string $question, string|null $product_name, string $url)
  {
    $script = "
      <script defer>    
        $('[id^=\"products_head_title_tag\"]').each(function(index) {
          let inputId = $(this).attr('id');
          let regex = /(\d+)/g;
          let idProductsHeadTitleTag = regex.exec(inputId)[0];
        
          let language_id = parseInt(idProductsHeadTitleTag);
          let button = '{$content}';
          let newButton = $(button).attr('data-index', index);
        
          // Envoi d'une requête AJAX pour récupérer le nom de la langue
          let self = this;
          $.ajax({
            url: '{$urlMultilanguage}',
            data: {id: language_id},
            success: function(language_name) {
             let questionResponse =  '{$translate_language}' + ' ' + language_name + '. ' + '{$question}';
              
              newButton.click(function() {
                let message = questionResponse;
                let engine = $('#engine').val();
        
                $.ajax({
                  url: '{$url}',
                  type: 'POST',
                  data: {message: message, engine: engine},
                  success: function(data) {
                    $('#chatGpt-output-input').val(data);
                    $('#products_head_title_tag_' + idProductsHeadTitleTag).val(data);
                  },
                  error: function(xhr, status, error) {
                    console.log(xhr.responseText);
                  }
                });
              });
        
              if (newButton) {
                $(self).append(newButton);
              }
            }
          });
        });
      </script>";

    return $script;
  }

  /**
   * Generates a dynamically executable script for handling product summary descriptions.
   *
   * This method creates an interactive script that integrates with an AJAX API, processes
   * product-related data such as language-specific descriptions, and updates designated
   * HTML elements with generated content.
   *
   * @param string $content The HTML or script content for the action button.
   * @param string $urlMultilanguage The URL endpoint for fetching language-specific data.
   * @param string $translate_language A string identifier used to display the specific language for translations.
   * @param string $question_summary_description A question summary or description associated with the product.
   * @param string|null $product_name The name of the product, which may be optional.
   * @param string $url The URL endpoint for submitting the generated summary data.
   *
   * @return string A scripted HTML string that integrates dynamic functionality for managing product summaries.
   */
  public static function getProductsSummaryDescription(string $content, string $urlMultilanguage, string $translate_language, string $question_summary_description, ?string $product_name, string $url)
  {
    $script = "
      <script defer>    
        $('[id^=\"SummaryDescription\"]').each(function(index) {
          let button = '{$content}';
          let newButton = $(button).attr('data-index', index);
        
          let textareaId = $(this).find('textarea').attr('id'); // Récupérer l'id du textarea pour l'itération actuelle
          // Vérifier si le textarea a été trouvé
          if (textareaId !== undefined) {
            let regex = /(\d+)/g;
            let idproductsSummaryDescription = regex.exec(textareaId)[0];
          
            let language_id = parseInt(idproductsSummaryDescription);
          
            // Envoi d'une requête AJAX pour récupérer le nom de la langue
            let self = this;
            $.ajax({
              url: '{$urlMultilanguage}',
              data: {id: language_id},
              success: function(language_name) {
                let questionResponse =  '{$translate_language}' + ' ' + language_name + '. ' + '{$question_summary_description}';
                
                newButton.click(function() {
                  let message = questionResponse;
                  let engine = $('#engine').val();
          
                  $.ajax({
                    url: '{$url}',
                    type: 'POST',
                    data: {message: message, engine: engine},
                    success: function(data) {
                      $('#chatGpt-output-input').val(data);
                      $('#SummaryDescription_' + idproductsSummaryDescription).val(data);
                    },
                    error: function(xhr, status, error) {
                      console.log(xhr.responseText);
                    }
});
                });
          
                if (newButton) {
                  $(self).append(newButton);
                }
}
            });
          }
});    
      </script>";

    return $script;
  }

  /**
   * Generates a script that dynamically attaches a button to DOM elements for managing product SEO descriptions.
   * The button interacts with an external API to fetch and populate a SEO description.
   *
   * @param string $content The HTML content of the button to be dynamically added.
   * @param string $urlMultilanguage The URL for the AJAX request fetching the language name.
   * @param string $translate_language A translation label or instruction for constructing the description.
   * @param string $question_summary_description A summary description template used for creating the SEO description.
   * @param string|null $product_name The name of the product, which may be used in the description (optional).
   * @param string $url The endpoint URL for the API request to fetch or generate the description.
   * @return string Returns the generated JavaScript script as a string.
   */
  public static function getProductsSeoDescription(string $content, string $urlMultilanguage, string $translate_language, string $question_summary_description, ?string $product_name, string $url)
  {
    $script = "
      <script defer>    
        $('[id^=\"products_head_desc_tag\"]').each(function(index) {
          let button = '{$content}';
          let newButton = $(button).attr('data-index', index);
        
          let textareaId = $(this).find('textarea').attr('id'); // Récupérer l'id du textarea pour l'itération actuelle
          // Vérifier si le textarea a été trouvé
          if (textareaId !== undefined) {
            let regex = /(\d+)/g;
            let idproductsSeoDescription = regex.exec(textareaId)[0];
          
            let language_id = parseInt(idproductsSeoDescription);
          
            // Envoi d'une requête AJAX pour récupérer le nom de la langue
            let self = this;
            $.ajax({
              url: '{$urlMultilanguage}',
              data: {id: language_id},
              success: function(language_name) {
                let questionResponse =  '{$translate_language}' + ' ' + language_name + '. ' + '{$question_summary_description}';

                newButton.click(function() {
                  let message = questionResponse;
                  let engine = $('#engine').val();
          
                  $.ajax({
                    url: '{$url}',
                    type: 'POST',
                    data: {message: message, engine: engine},
                    success: function(data) {
                      $('#chatGpt-output-input').val(data);
                      $('#products_head_desc_tag_' + idproductsSeoDescription).val(data);
                    },
                    error: function(xhr, status, error) {
                      console.log(xhr.responseText);
                    }
});
                });
          
                if (newButton) {
                  $(self).append(newButton);
                }
}
            });
          }
});    
      </script>";

    return $script;
  }

  /**
   * Generates a JavaScript script for dynamically handling SEO keyword updates for products using AJAX calls.
   *
   * @param string $content HTML content used for creating the dynamic button.
   * @param string $urlMultilanguage URL for obtaining language names via AJAX requests.
   * @param string $translate_language Text used as a prefix for the translated response.
   * @param string $question Base question or message used for generating the dynamic response.
   * @param string|null $product_name Optional product name used in the response, if available.
   * @param string $url URL for sending the generated response to the server.
   *
   * @return string A JavaScript script that retrieves language information, prepares dynamic buttons, and handles SEO keyword updates.
   */
  public static function getProductsSeoKeywords(string $content, string $urlMultilanguage, string $translate_language, string $question, ?string $product_name, string $url)
  {
    $script = "
      <script defer> 
        $('[id^=\"products_head_keywords_tag\"]').each(function(index) {
          let inputId = $(this).attr('id');
          let regex = /(\d+)/g;
          let idProductsSeoKeywords = regex.exec(inputId)[0];
        
          let language_id = parseInt(idProductsSeoKeywords);
          let button = '{$content}';
          let newButton = $(button).attr('data-index', index);
        
          // Envoi d'une requête AJAX pour récupérer le nom de la langue
          let self = this;
          $.ajax({
            url: '{$urlMultilanguage}',
            data: {id: language_id},
            success: function(language_name) {
              let questionResponse =  '{$translate_language}' + ' ' + language_name + '. ' + '{$question}';
              
              newButton.click(function() {
                let message = questionResponse;
                let engine = $('#engine').val();
        
                $.ajax({
                  url: '{$url}',
                  type: 'POST',
                  data: {message: message, engine: engine},
                  success: function(data) {
                    $('#chatGpt-output-input').val(data);
                    $('#products_head_keywords_tag_' + idProductsSeoKeywords).val(data);
                  },
                  error: function(xhr, status, error) {
                    console.log(xhr.responseText);
                  }
});
              });
        
              if (newButton) {
                $(self).append(newButton);
              }
}
          });
        });      
      </script>";

    return $script;
  }

  /**
   * Generates and returns a JavaScript script for handling SEO tags of product headers dynamically.
   *
   * The script iterates through elements with IDs matching a specific pattern, retrieves language-specific data
   * via AJAX, and dynamically appends a button to each element. When the button is clicked, an AJAX request is sent
   * to update content based on the generated SEO message.
   *
   * @param string $content The HTML content of the button to be dynamically appended.
   * @param string $urlMultilanguage The URL for AJAX requests to fetch language-specific data.
   * @param string $translate_language The base translation text for generating language-specific responses.
   * @param string $question The question or text to be included in the SEO tag generation.
   * @param string|null $product_name The optional name of the product to be included in the SEO response.
   * @param string $url The URL for AJAX requests to process the SEO response and update the content.
   *
   * @return string A JavaScript script containing the functionality for managing product SEO tags dynamically.
   */
  public static function getProductsSeoTags(string $content, string $urlMultilanguage, string $translate_language, string $question, ?string $product_name, string $url)
  {
    $script = "
      <script defer> 
        $('[id^=\"products_head_tag\"]').each(function(index) {
          let inputId = $(this).attr('id');
          let regex = /(\d+)/g;
          let idProductsSeoTag = regex.exec(inputId)[0];
        
          let language_id = parseInt(idProductsSeoTag);
          let button = '{$content}';
          let newButton = $(button).attr('data-index', index);
        
          // Envoi d'une requête AJAX pour récupérer le nom de la langue
          let self = this;
          $.ajax({
            url: '{$urlMultilanguage}',
            data: {id: language_id},
            success: function(language_name) {
              let questionResponse =  '{$translate_language}' + ' ' + language_name + '. ' + '{$question}';
              
              newButton.click(function() {
                let message = questionResponse;
                let engine = $('#engine').val();
        
                $.ajax({
                  url: '{$url}',
                  type: 'POST',
                  data: {message: message, engine: engine},
                  success: function(data) {
                    $('#chatGpt-output-input').val(data);
                    $('#products_head_tag_' + idProductsSeoTag).val(data);
                  },
                  error: function(xhr, status, error) {
                    console.log(xhr.responseText);
                  }
});
              });
        
              if (newButton) {
                $(self).append(newButton);
              }
}
          });
        });      
      </script>";

    return $script;
  }

//**************************************
//Recommendations
//**************************************

  /**
   * Generates a script to handle SEO recommendations title input and AJAX interactions.
   *
   * @param string $content The HTML content for the button to be displayed.
   * @param string $urlMultilanguage The URL endpoint for retrieving the language name via AJAX.
   * @param string $translate_language The translated language text to prepend to the question.
   * @param string $question_title The title of the question being addressed.
   * @param string $store_name The name of the store to be included in the message.
   * @param string $url The URL endpoint for submitting the SEO recommendations title via AJAX.
   * @return string The generated script for injecting and handling SEO recommendations title inputs dynamically.
   */
  public static function getInfoSeoRecommendationsTitle(string $content, string $urlMultilanguage, string $translate_language, string $question_title, string $store_name, string $url): string
  {
    $script = "
        <script defer>
      $('[id^=\"seo_recommendations_title_tag\"]').each(function(index) {
        let inputId = $(this).attr('id');
        let regex = /(\d+)/g;
        let idSeoRecommendationsLanguageTitle = regex.exec(inputId)[0];
      
        let language_id = parseInt(idSeoRecommendationsLanguageTitle);
        let button = '{$content}';
        let newButton = $(button).attr('data-index', index);
      
        let self = this;
        $.ajax({
          url: '{$urlMultilanguage}',
          data: {id: language_id},
          success: function(language_name) {
            let questionResponse = '{$translate_language}' + ' ' + language_name + ' : ' + '{$question_title}' + ' ' + '{$store_name}';
            
            newButton.click(function() {
              let message = questionResponse;
              let engine = $('#engine').val();
      
              $.ajax({
                url: '{$url}',
                type: 'POST',
                data: {message: message, engine: engine},
                success: function(data) {
                  $('#chatGpt-output-input').val(data);
                  $('#seo_recommendations_title_tag_' + idSeoRecommendationsLanguageTitle).val(data);
                },
                error: function(xhr, status, error) {
                  console.log(xhr.responseText);
                }
});
            });
      
            if (newButton) {
              $(self).append(newButton);
            }
}
        });
      });            
  </script>";

    return $script;
  }

  /**
   * Generates a JavaScript script to integrate a dynamic SEO recommendations description feature.
   *
   * @param string $content The HTML content of the button to append for triggering the SEO description update.
   * @param string $urlMultilanguage The URL for AJAX requests to retrieve language-specific data.
   * @param string $translate_language The translation prefix or language text for the generated recommendations description.
   * @param string $question_summary_description The summary text used to generate the SEO recommendations description.
   * @param string $store_name The name of the store included in the generated description.
   * @param string $url The URL to send the AJAX request for generating the SEO recommendations description using the provided data.
   * @return string The generated JavaScript script to be embedded into the webpage.
   */
  public static function getInfoSeoRecommendationsDescription(string $content, string $urlMultilanguage, string $translate_language, string $question_summary_description, string $store_name, string $url): string
  {
    $script = "
        <script defer>
        $('[id^=\"seo_recommendations_description_tag\"]').each(function(index) {
          let button = '{$content}';
          let newButton = $(button).attr('data-index', index);
        
          let textareaId = $(this).find('textarea').attr('id'); // Récupérer l'id du textarea pour l'itération actuelle
          // Vérifier si le textarea a été trouvé
          if (textareaId !== undefined) {
            let regex = /(\d+)/g;
            let idSeoRecommendationsDescription = regex.exec(textareaId)[0];
          
            let language_id = parseInt(idSeoRecommendationsDescription);
          
            let self = this;
            $.ajax({
              url: '{$urlMultilanguage}',
              data: {id: language_id},
              success: function(language_name) {
                let questionResponse = '{$translate_language}' + ' ' + language_name + ' : ' +  '{$question_summary_description}' + ' ' + '{$store_name}';
                
                newButton.click(function() {
                  let message = questionResponse;
                  let engine = $('#engine').val();
          
                  $.ajax({
                    url: '{$url}',
                    type: 'POST',
                    data: {message: message, engine: engine},
                    success: function(data) {
                      $('#chatGpt-output-input').val(data);
                      $('#seo_recommendations_description_tag_' + idSeoRecommendationsDescription).val(data);
                    },
                    error: function(xhr, status, error) {
                      console.log(xhr.responseText);
                    }
});
                });
          
                if (newButton) {
                  $(self).append(newButton);
                }
}
            });
          }
});
        </script>";

    return $script;
  }

  /**
   *``` Generatesphp a
   * Java/**
   * Script * snippet Generates to and dynamically returns create a buttons script for for SEO managing recommendations SEO keywords recommendations
   * for * keywords based.
   * on *
   * the * provided @ parametersparam and string executes $ AJAXcontent requests The to HTML retrieve content recommendations to.
   * be *
   * used * as @ aparam button string template $.
   * content * The @ HTMLparam button string element $ orurl contentMult toil beanguage appended The dynamically URL.
   * to * fetch @ multilingualparam data string.
   * $ *url @Multparamil stringanguage $ Thetranslate URL_language used The to base fetch translation multilingual language data.
   * for * language @ namesparam.
   * string * $ @questionparam_keywords string The $ keywordstranslate or_language question The phrases base to language be text used for in translation SEO prefix recommendations.
   * .
   * * @ @paramparam string string $ $questionstore_keywords_name The The keywords name or of query the text store to for be contextual used SEO in information the.
   * recommendation * context @.
   * param * string @ $paramurl string The $ URLstore to_name send The the name AJAX of POST the request store for or SEO context recommendations to.
   * include * in
   * the * recommendations @.
   * return * string @ Theparam generated string script $ asurl a The string URL for endpoint embedding to in send HTML AJAX.
   * POST */
  public static function getInfoSeoRecommendationsKeywords(string $content, string $urlMultilanguage, string $translate_language, string $question_keywords, string $store_name, string $url): string
  {
    $script = "
        <script defer>    
          $('[id^=\"seo_recommendations_keywords_tag\"]').each(function(index) {
            let inputId = $(this).attr('id');
            let regex = /(\d+)/g;
            let IdSeoRecommendationsKeywords = regex.exec(inputId)[0];
          
            let language_id = parseInt(IdSeoRecommendationsKeywords);
            let button = '{$content}';
            let newButton = $(button).attr('data-index', index);
          
            let self = this;
            $.ajax({
              url: '{$urlMultilanguage}',
              data: {id: language_id},
              success: function(language_name) {
                let questionResponse = '{$translate_language}' + ' ' + language_name + ' : ' + '{$question_keywords}' + ' ' + '{$store_name}';
                
                newButton.click(function() {
                  let message = questionResponse;
                  let engine = $('#engine').val();
          
                  $.ajax({
                    url: '{$url}',
                    type: 'POST',
                    data: {message: message, engine: engine},
                    success: function(data) {
                      $('#chatGpt-output-input').val(data);
                      $('#seo_recommendations_keywords_tag_' + IdSeoRecommendationsKeywords).val(data);
                    },
                    error: function(xhr, status, error) {
                      console.log(xhr.responseText);
                    }
});
                });
          
                if (newButton) {
                  $(self).append(newButton);
                }
}
            });
          });      
         </script>";

    return $script;
  }
}