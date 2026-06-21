<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\AI\Ecommerce\Module\Hooks\ClicShoppingAdmin\Categories;

use ClicShopping\Apps\AI\Ecommerce\Ecommerce as EcommerceApp;
use ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\ChatJsAdminSeo;
use ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\Gpt;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Interfaces\HooksInterface;
use ClicShopping\OM\Registry;

class SeoChatGpt implements HooksInterface
{
  public mixed $app;

  /**
   * Constructor method for initializing the ChatGpt application.
   *
   * @return void
   */
  public function __construct()
  {
    if (!Registry::exists('Ecommerce')) {
      Registry::set('Ecommerce', new EcommerceApp());
    }

    $this->app = Registry::get('Ecommerce');
  }

  /**
   * Displays the UI for managing SEO settings and GPT integrations for categories in the admin interface.
   *
   * @return string|bool Returns the rendered HTML output for the SEO management interface if successful;
   *                     otherwise returns false if GPT integration is unavailable or disabled.
   */
  public function display()
  {
    $CLICSHOPPING_CategoriesAdmin = Registry::get('CategoriesAdmin');

    if (Gpt::checkGptStatus() === false) {
      return false;
    }

    if (!isset($_GET['cID'])) {
      return false;
    }

    $this->app->loadDefinitions('Module/Hooks/ClicShoppingAdmin/Categories/seo_chat_gpt');

    if (isset($_GET['cID'])) {
      $id = HTML::sanitize($_GET['cID']);
      $categories_name = $CLICSHOPPING_CategoriesAdmin->getCategoryName($id);

      $expertise = $this->app->getDef('text_seo_expertise');
      $question = $expertise . ' ' . $this->app->getDef('text_seo_page_title_question', ['category_name' => $categories_name]);
      $question_summary_description = $expertise . ' ' . $this->app->getDef('text_seo_page_summary_description_question', ['category_name'  => $categories_name]);
      $translate_language = $this->app->getDef('text_seo_page_translate_language');
      $question_keywords = $expertise . ' ' . $this->app->getDef('text_seo_page_keywords_question', ['category_name'  => $categories_name]);

      $url = Gpt::getAjaxUrl(false);
      $urlMultilanguage = Gpt::getAjaxSeoMultilanguageUrl();

      $content = '<button type="button" class="btn btn-primary btn-sm submit-button" data-index="0">';
      $content .= '<i class="bi-chat-square-dots" title="' . $this->app->getDef('text_seo_action') . '"></i>';
      $content .= '</button>';

      $getCategoriesSeoTitle = ChatJsAdminSeo::getCategoriesSeoTitle($content, $urlMultilanguage, $translate_language, $question, $url);


      $getCategoriesSeoDescription = ChatJsAdminSeo::getCategoriesSeoDescription($content, $urlMultilanguage, $translate_language, $question_summary_description, $url);
      $getCategoriesSeoKeywords = ChatJsAdminSeo::getCategoriesSeoKeywords($content, $urlMultilanguage, $translate_language, $question_keywords, $url);

      $output = <<<EOD
      <!------------------>
      <!-- ChatGpt start tag-->
      <!------------------>
           
      <!-- categories seo meta title -->
          {$getCategoriesSeoTitle}
      <!-- categories meta description -->
          {$getCategoriesSeoDescription}
      <!-- categories seo meta keyword -->
          {$getCategoriesSeoKeywords}
      <!-- categories seo tag -->
      EOD;
    } else {
      $tab_title = $this->app->getDef('tab_gpt_options');
      $title = $this->app->getDef('text_gpt_options');

      $content = '
            <div class="mt-1"></div>     
            <div class="row" id="productOptionGptDescription">
              <div class="col-md-9">
                <div class="form-group row">
                  <label for="' . $this->app->getDef('text_options_gpt_description') . '"
                         class="col-7 col-form-label">' . $this->app->getDef('text_options_gpt_description') . '</label>
                  <div class="col-md-2">
                    <ul class="list-group-slider list-group-flush">
                      <li class="list-group-item-slider">
                        <label class="switch">
                          ' . HTML::checkboxField('option_gpt_description', '1', true, 'class="success"') . '
                          <span class="slider"></span>
                        </label>
                      </li>
                    </ul>
                  </div>
                </div>
              </div>
            </div>
           

            <div class="mt-1"></div>     
            <div class="row" id="productOptionGptSeoTitle">
              <div class="col-md-9">
                <div class="form-group row">
                  <label for="' . $this->app->getDef('text_options_gpt_seo_title') . '"
                         class="col-7 col-form-label">' . $this->app->getDef('text_options_gpt_seo_title') . '</label>
                  <div class="col-md-2">
                    <ul class="list-group-slider list-group-flush">
                      <li class="list-group-item-slider">
                        <label class="switch">
                          ' . HTML::checkboxField('option_gpt_seo_title', '1', true, 'class="success"') . '
                          <span class="slider"></span>
                        </label>
                      </li>
                    </ul>
                  </div>
                </div>
              </div>
            </div>
            
            <div class="mt-1"></div>     
            <div class="row" id="productOptionGptSeoDescription">
              <div class="col-md-9">
                <div class="form-group row">
                  <label for="' . $this->app->getDef('text_options_gpt_seo_description') . '"
                         class="col-7 col-form-label">' . $this->app->getDef('text_options_gpt_seo_description') . '</label>
                  <div class="col-md-2">
                    <ul class="list-group-slider list-group-flush">
                      <li class="list-group-item-slider">
                        <label class="switch">
                          ' . HTML::checkboxField('option_gpt_seo_description', '1', true, 'class="success"') . '
                          <span class="slider"></span>
                        </label>
                      </li>
                    </ul>
                  </div>
                </div>
              </div>
            </div>
            
            <div class="mt-1"></div>     
            <div class="row" id="productOptionGptSeokeywords">
              <div class="col-md-9">
                <div class="form-group row">
                  <label for="' . $this->app->getDef('text_options_gpt_seo_keywords') . '"
                         class="col-7 col-form-label">' . $this->app->getDef('text_options_gpt_seo_keywords') . '</label>
                  <div class="col-md-2">
                    <ul class="list-group-slider list-group-flush">
                      <li class="list-group-item-slider">
                        <label class="switch">
                          ' . HTML::checkboxField('option_gpt_seo_keywords', '1', true, 'class="success"') . '
                          <span class="slider"></span>
                        </label>
                      </li>
                    </ul>
                  </div>
                </div>
              </div>
            </div>
<!--            
            <div class="mt-1"></div>
            <div class="row" id="productOptionGptCreateImage">
              <div class="col-md-9">
                <div class="form-group row">
                  <label for="' . $this->app->getDef('text_options_gpt_image') . '"
                         class="col-7 col-form-label">' . $this->app->getDef('text_options_gpt_image') . '</label>
                  <div class="col-md-2">
                    <ul class="list-group-slider list-group-flush">
                      <li class="list-group-item-slider">
                        <label class="switch">
                          ' . HTML::checkboxField('option_gpt_create_image', '1', false, 'class="success"') . '
                          <span class="slider"></span>
                        </label>
                      </li>
                    </ul>
                  </div>
                </div>
              </div>
            </div>
-->            
            <div class="mt-1"></div>
            <div class="alert alert-info" role="alert">
              <div><h4><i class="bi bi-question-circle" title="' . $this->app->getDef('title_help_seo') . '"></i></h4> ' . $this->app->getDef('title_help_seo') . '</div>
              <div class="mt-1"></div>
              <div>' . $this->app->getDef('text_help_seo') . '</div>
            </div>
            ';

      $output = <<<EOD
<!-- ######################## -->
<!--  Start OptionsGptApp  -->
<!-- ######################## -->
<div class="tab-pane" id="section_OptionsGptApp_content">
  <div class="mainTitle">
    <span class="col-md-2">{$title}</span>
  </div>
  {$content}
</div>
<script>
$('#section_OptionsGptApp_content').appendTo('#categoriesTabs .tab-content');
$('#categoriesTabs .nav-tabs').append('    <li class="nav-item"><a data-bs-target="#section_OptionsGptApp_content" role="tab" data-bs-toggle="tab" class="nav-link">{$tab_title}</a></li>');
</script>
<!-- ######################## -->
<!-- End OptionsGptApp  -->
<!-- ######################## -->
EOD;
    }

    return $output;
  }
}
