<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\AI\Ecommerce\Module\Hooks\ClicShoppingAdmin\Manufacturers;

use ClicShopping\Apps\Catalog\Manufacturers\Classes\ClicShoppingAdmin\ManufacturerAdmin;
use ClicShopping\Apps\Configuration\ChatGpt\ChatGpt as ChatGptApp;
use ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\ChatJsAdminSeo;
use ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\Gpt;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

class SeoChatGpt implements \ClicShopping\OM\Modules\HooksInterface
{
  public mixed $app;

  /**
   * Constructor method that initializes the ChatGpt application instance.
   * Ensures a single instance of the ChatGptApp is set and retrieved using the Registry.
   *
   * @return void
   */
  public function __construct()
  {
    if (!Registry::exists('ChatGpt')) {
      Registry::set('ChatGpt', new ChatGptApp());
    }

    $this->app = Registry::get('ChatGpt');
  }

  /**
   * Generates and returns the HTML content for displaying SEO options and integration with GPT functionalities
   * based on the state or configuration of the current manufacturer or available settings.
   *
   * @return string|false The generated HTML content for SEO options and GPT integration, or false if GPT is not enabled or unavailable.
   */
  public function display()
  {

    if (Gpt::checkGptStatus() === false) {
      return false;
    }

    $this->app->loadDefinitions('Module/Hooks/ClicShoppingAdmin/Manufacturer/seo_chat_gpt');

    if (isset($_GET['mID'])) {
      $id = HTML::sanitize($_GET['mID']);
      $manufacturer_name = ManufacturerAdmin::getManufacturerNameById($id);

      $question_title = $this->app->getDef('text_seo_page_title_question', ['brand_name' => $manufacturer_name]);
      $question_keywords = $this->app->getDef('text_seo_page_keywords_question', ['brand_name' => $manufacturer_name]);
      $question_summary_description = $this->app->getDef('text_seo_page_summary_description_question', ['brand_name' => $manufacturer_name]);
      $translate_language = $this->app->getDef('text_seo_page_translate_language', ['brand_name' => $manufacturer_name]);

      $url = Gpt::getAjaxUrl(false);
      $urlMultilanguage = Gpt::getAjaxSeoMultilanguageUrl();

      $content = '<button type="button" class="btn btn-primary btn-sm submit-button" data-index="0">';
      $content .= '<i class="bi-chat-square-dots" title="' . $this->app->getDef('text_seo_action') . '"></i>';
      $content .= '</button>';

      $getManufacturerSeoTitle = ChatJsAdminSeo::getManufacturerSeoTitle($content, $urlMultilanguage, $translate_language, $question_title, $manufacturer_name, $url);
      $getManufacturerSeoDescription = ChatJsAdminSeo::getManufacturerSeoDescription($content, $urlMultilanguage, $translate_language, $question_summary_description, $manufacturer_name, $url);
      $getManufacturerSeoKeywords = ChatJsAdminSeo::getManufacturerSeoKeywords($content, $urlMultilanguage, $translate_language, $question_keywords, $manufacturer_name, $url);

      $output = <<<EOD
<!------------------>
<!-- ChatGpt start tag-->
<!------------------>
<!-- manufacturer seo  meta title -->
  {$getManufacturerSeoTitle}
<!-- manufacturer seo  meta description -->
  {$getManufacturerSeoDescription}
<!-- manufacturer seo  meta keyword -->
  {$getManufacturerSeoKeywords}
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
  $('#section_OptionsGptApp_content').appendTo('#manufacturersTabs .tab-content');
  $('#manufacturersTabs .nav-tabs').append('    <li class="nav-item"><a data-bs-target="#section_OptionsGptApp_content" role="tab" data-bs-toggle="tab" class="nav-link">{$tab_title}</a></li>');
  </script>
  <!-- ######################## -->
  <!-- End OptionsGptApp  -->
  <!-- ######################## -->
  EOD;
    }

    return $output;
  }
}