<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\AI\Ecommerce\Module\Hooks\ClicShoppingAdmin\Products;

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
   * Displays the SEO-related configurations and options for products using ChatGPT functionality.
   *
   * This method identifies the current context based on the product ID parameter. If a product ID is provided in the request,
   * it processes and populates SEO attributes such as product name, page titles, tags, summaries, keywords, and translations.
   * These attributes are then rendered via dynamically generated HTML for the product's SEO meta information.
   * If no product ID is provided, it outputs form controls with options to toggle various ChatGPT SEO settings for products.
   *
   * @return bool|string Returns false if the GPT status is not enabled; otherwise, returns HTML content for displaying SEO-related functionality.
   */
  public function display()
  {
    $CLICSHOPPING_ProductsAdmin = Registry::get('ProductsAdmin');

    if (Gpt::checkGptStatus() === false) {
      return false;
    }

    if (!isset($_GET['pID'])) {
      return false;
    }

    $this->app->loadDefinitions('Module/Hooks/ClicShoppingAdmin/Products/seo_chat_gpt');

    if (isset($_GET['pID'])) {
      $id = HTML::sanitize($_GET['pID']);
      $product_name = $CLICSHOPPING_ProductsAdmin->getProductsName($id);
      $product_name_array = ['products_name' => $product_name];

      $expertise = $this->app->getDef('text_seo_expertise');
      $question = $expertise . ' ' . $this->app->getDef('text_seo_page_title_question', $product_name_array);
      $question_tag = $expertise . ' ' . $this->app->getDef('text_seo_page_tag_question', $product_name_array);
      $question_summary_description = $expertise . ' ' . $this->app->getDef('text_seo_page_summary_description_question', $product_name_array);
      $translate_language = $expertise . ' ' . $this->app->getDef('text_seo_page_translate_language', $product_name_array);
      $question_keywords = $expertise . ' ' . $this->app->getDef('text_seo_page_keywords_question', $product_name_array);

      $url = Gpt::getAjaxUrl(false);
      $urlMultilanguage = Gpt::getAjaxSeoMultilanguageUrl();

      $content = '<button type="button" class="btn btn-primary btn-sm submit-button" data-index="0">';
      $content .= '<i class="bi-chat-square-dots" title="' . $this->app->getDef('text_seo_action') . '"></i>';
      $content .= '</button>';

      $getProductsSeoTitle = ChatJsAdminSeo::getProductsSeoTitle($content, $urlMultilanguage, $translate_language, $question, $product_name, $url);
      $getProductsSeoSummaryDescription = ChatJsAdminSeo::getProductsSummaryDescription($content, $urlMultilanguage, $translate_language, $question_summary_description, $product_name, $url);
      $getProductsSeoDescription = ChatJsAdminSeo::getProductsSeoDescription($content, $urlMultilanguage, $translate_language, $question_summary_description, $product_name, $url);
      $getProductsSeoKeywords = ChatJsAdminSeo::getProductsSeoKeywords($content, $urlMultilanguage, $translate_language, $question_keywords, $product_name, $url);
      $getProductsSeoTags = ChatJsAdminSeo::getProductsSeoTags($content, $urlMultilanguage, $translate_language, $question_tag, $product_name, $url);

      $output = <<<EOD
<!------------------>
<!-- ChatGpt start tag-->
<!------------------>
<!-- products seo title meta tag -->
{$getProductsSeoTitle}
<!-- product seo meta summary description -->
{$getProductsSeoSummaryDescription}
<!-- product seo meta description -->
{$getProductsSeoDescription}
<!-- product seo  meta keyword -->
{$getProductsSeoKeywords}
<!-- product seo tag -->
{$getProductsSeoTags}

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
            <div class="row" id="productOptionGptSummary">
              <div class="col-md-9">
                <div class="form-group row">
                  <label for="' . $this->app->getDef('text_options_gpt_summary_description') . '"
                         class="col-7 col-form-label">' . $this->app->getDef('text_options_gpt_summary_description') . '</label>
                  <div class="col-md-2">
                    <ul class="list-group-slider list-group-flush">
                      <li class="list-group-item-slider">
                        <label class="switch">
                          ' . HTML::checkboxField('option_gpt_summary_description', '1', true, 'class="success"') . '
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
            
            <div class="mt-1"></div>     
            <div class="row" id="productOptionGptSeotags">
              <div class="col-md-9">
                <div class="form-group row">
                  <label for="' . $this->app->getDef('text_options_gpt_seo_tags') . '"
                         class="col-7 col-form-label">' . $this->app->getDef('text_options_gpt_seo_tags') . '</label>
                  <div class="col-md-2">
                    <ul class="list-group-slider list-group-flush">
                      <li class="list-group-item-slider">
                        <label class="switch">
                          ' . HTML::checkboxField('option_gpt_seo_tags', '1', true, 'class="success"') . '
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
$('#section_OptionsGptApp_content').appendTo('#productsTabs .tab-content');
$('#productsTabs .nav-tabs').append('    <li class="nav-item"><a data-bs-target="#section_OptionsGptApp_content" role="tab" data-bs-toggle="tab" class="nav-link">{$tab_title}</a></li>');
</script>
<!-- ######################## -->
<!-- End OptionsGptApp  -->
<!-- ######################## -->
EOD;
    }

    return $output;
  }
}
