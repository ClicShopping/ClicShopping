<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\ChatGpt\Module\Hooks\ClicShoppingAdmin\PageManager;

use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

use ClicShopping\Apps\Communication\PageManager\Classes\ClicShoppingAdmin\PageManagerAdmin;
use ClicShopping\Apps\Configuration\ChatGpt\ChatGpt as ChatGptApp;
use ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\ChatJsAdminSeo;
use ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\Gpt;

class SeoChatGpt implements \ClicShopping\OM\Modules\HooksInterface
{
  public mixed $app;

  /**
   * Constructor method initializes the ChatGpt application instance
   * and stores it in the application registry if it doesn't already exist.
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
   * Generates and returns the SEO metadata content for a page based on the provided configurations and user inputs.
   * The method fetches necessary data, processes it with defined SEO tools, and organizes it into a formatted output.
   *
   * @return string|false Returns the formatted SEO metadata content as a string if the necessary conditions are met.
   * Returns false if GPT status is inactive or if the page ID is not provided.
   */
  public function display()
  {
    $CLICSHOPPING_Language = Registry::get('Language');

    if (Gpt::checkGptStatus() === false) {
      return false;
    }

    $this->app->loadDefinitions('Module/Hooks/ClicShoppingAdmin/PageManager/seo_chat_gpt');

    if (isset($_GET['bID'])) {
      $id = HTML::sanitize($_GET['bID']);
    } else {
      return false;
    }

    $question = $this->app->getDef('text_seo_page_title_question');
    $question_keywords = $this->app->getDef('text_seo_page_keywords_question');
    $question_summary_description = $this->app->getDef('text_seo_page_summary_description_question');
    $translate_language = $this->app->getDef('text_seo_page_translate_language');

    $page_manager_name = PageManagerAdmin::getPageManagerTitle($id, $CLICSHOPPING_Language->getId());

    $url = Gpt::getAjaxUrl(false);
    $urlMultilanguage = Gpt::getAjaxSeoMultilanguageUrl();

    $content = '<button type="button" class="btn btn-primary btn-sm submit-button" data-index="0">';
    $content .= '<i class="bi-chat-square-dots" title="' . $this->app->getDef('text_seo_page_title') . '"></i>';
    $content .= '</button>';

    $getPageManagerSeoTitle = ChatJsAdminSeo::getPageManagerSeoTitle($content, $urlMultilanguage, $translate_language, $question, $page_manager_name, $url);
    $getPageManagerSeoDescription = ChatJsAdminSeo::getPageManagerSeoDescription($content, $urlMultilanguage, $translate_language, $question_summary_description, $page_manager_name, $url);
    $getPageManagerSeoKeywords = ChatJsAdminSeo::getPageManagerSeoKeywords($content, $urlMultilanguage, $translate_language, $question_keywords, $page_manager_name, $url);

    $output = <<<EOD
<!------------------>
<!-- ChatGpt start tag-->
<!------------------>
<!-- page manager seo meta tilte  -->
  {$getPageManagerSeoTitle}
<!-- page manager seo meta description -->
  {$getPageManagerSeoDescription}
<!-- pqge manager seo  meta keyword -->
  {$getPageManagerSeoKeywords}
EOD;

    return $output;
  }
}
