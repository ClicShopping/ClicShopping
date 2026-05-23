<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\ChatGpt\Module\Hooks\ClicShoppingAdmin\SEO;

use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;
use ClicShopping\Apps\Configuration\ChatGpt\ChatGpt as ChatGptApp;
use ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\ChatJsAdminSeo;
use ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\Gpt;

class SeoChatGpt implements \ClicShopping\OM\Modules\HooksInterface
{
  public mixed $app;

  /**
   * Constructor method.
   *
   * Initializes the ChatGpt application by checking if it already exists in the Registry.
   * If not, it creates a new instance of ChatGptApp and stores it in the Registry.
   * Then, retrieves the instance from the Registry and assigns it to the app property.
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
   * Displays the SEO-related content and recommendations for the ChatGpt module in the administrative interface.
   *
   * This method checks the status of the GPT integration and ensures that necessary definitions for SEO handling are loaded.
   * It generates SEO meta tags and recommendations for various pages, including products, reviews, specials, favorites,
   * and featured sections, using pre-defined language and store variables.
   * If any required element (e.g., store name) is missing or the GPT status is unsupported, the method returns false.
   *
   * @return false|string Returns false if the GPT status is unsupported or a required element is missing; otherwise, returns the generated HTML output containing SEO meta tags.
   */
  public function display()
  {

    if (Gpt::checkGptStatus() === false) {
      return false;
    }

    $this->app->loadDefinitions('Module/Hooks/ClicShoppingAdmin/SEO/seo_chat_gpt');

    if (empty(STORE_NAME)) {
      return false;
    }
    
    $store_name = HTML::outputProtected(STORE_NAME);

    $translate_language = $this->app->getDef('text_seo_page_translate_language');
    $question_title = $this->app->getDef('text_seo_page_title_question', ['store_name' => $store_name]);
    $question_summary_description = $this->app->getDef('text_seo_page_summary_description_question', ['store_name' => $store_name]);
    $question_keywords = $this->app->getDef('text_seo_page_keywords_question', ['store_name' => $store_name]);
    $question_tag = $this->app->getDef('text_seo_page_tag_question', ['store_name' => $store_name]);

    $text_tag_specials = $this->app->getDef('text_tag_specials', ['store_name' => $store_name]);
    $text_tag_favorite = $this->app->getDef('text_tag_favorite', ['store_name' => $store_name]);
    $text_tag_featured = $this->app->getDef('text_tag_featured', ['store_name' => $store_name]);
    $text_tag_products_new = $this->app->getDef('text_tag_products_new', ['store_name' => $store_name]);
    $text_tag_review = $this->app->getDef('text_tag_review', ['store_name' => $store_name]);

    $content = '<button type="button" class="btn btn-primary btn-sm submit-button" data-index="0">';
    $content .= '<i class="bi-chat-square-dots" title="' . $this->app->getDef('text_seo_action') . '"></i>';
    $content .= '</button>';

    $url = Gpt::getAjaxUrl(false);
    $urlMultilanguage = Gpt::getAjaxSeoMultilanguageUrl();

    $getInfoSeoDefaultTitleH1 = ChatJsAdminSeo::getInfoSeoDefaultTitleH1($content, $urlMultilanguage, $translate_language, $question_title, $store_name, $url);
    $getInfoSeoDefaultTitle = ChatJsAdminSeo::getInfoSeoDefaultTitle($content, $urlMultilanguage, $translate_language, $question_title, $store_name, $url);
    $getInfoSeoDefaultDescription = ChatJsAdminSeo::getInfoSeoDefaultDescription($content, $urlMultilanguage, $translate_language, $question_summary_description, $store_name, $url);
    $getInfoSeoDefaultKeywords = ChatJsAdminSeo::getInfoSeoDefaultKeywords($content, $urlMultilanguage, $translate_language, $question_keywords, $store_name, $url);
    $getInfoSeoDefaultFooter = ChatJsAdminSeo::getInfoSeoDefaultFooter($content, $urlMultilanguage, $translate_language, $question_tag, $store_name, $url);

    $getInfoSeoProductDescriptionTitle = ChatJsAdminSeo::getInfoSeoProductDescriptionTitle($content, $urlMultilanguage, $translate_language, $question_title, $store_name, $url);
    $getInfoSeoProductDescription = ChatJsAdminSeo::getInfoSeoProductDescription($content, $urlMultilanguage, $translate_language, $question_summary_description, $store_name, $url);
    $getInfoSeoProductKeywords = ChatJsAdminSeo::getInfoSeoProductkeywords($content, $urlMultilanguage, $translate_language, $question_keywords, $store_name, $url);

    $getInfoSeoProductsNewTitle = ChatJsAdminSeo::getInfoSeoProductsNewTitle($content, $urlMultilanguage, $translate_language, $question_title, $store_name, $text_tag_products_new, $url);
    $getInfoSeoProductsNewDescription = ChatJsAdminSeo::getInfoSeoProductsNewDescription($content, $urlMultilanguage, $translate_language, $question_summary_description, $store_name, $text_tag_products_new, $url);
    $getInfoSeoProductsNewKeywords = ChatJsAdminSeo::getInfoSeoProductsNewKeywords($content, $urlMultilanguage, $translate_language, $question_keywords, $store_name, $text_tag_products_new, $url);

    $getInfoSeoSpecialsTitle = ChatJsAdminSeo::getInfoSeoSpecialsTitle($content, $urlMultilanguage, $translate_language, $question_title, $store_name, $text_tag_specials, $url);
    $getInfoSeoSpecialsDescription = ChatJsAdminSeo::getInfoSeoSpecialsDescription($content, $urlMultilanguage, $translate_language, $question_summary_description, $store_name, $text_tag_specials, $url);
    $getInfoSeoSpecialsKeywords = ChatJsAdminSeo::getInfoSeoSpecialsKeywords($content, $urlMultilanguage, $translate_language, $question_keywords, $store_name, $text_tag_specials, $url);

    $getInfoSeoReviewsTitle = ChatJsAdminSeo::getInfoSeoReviewsTitle($content, $urlMultilanguage, $translate_language, $question_title, $store_name, $text_tag_review, $url);
    $getInfoSeoReviewsDescription = ChatJsAdminSeo::getInfoSeoReviewsDescription($content, $urlMultilanguage, $translate_language, $question_summary_description, $store_name, $text_tag_review, $url);
    $getInfoSeoReviewsKeywords = ChatJsAdminSeo::getInfoSeoReviewskeywords($content, $urlMultilanguage, $translate_language, $question_keywords, $store_name, $text_tag_review, $url);

    $getInfoFavoritesTitle = ChatJsAdminSeo::getInfoFavoritesTitle($content, $urlMultilanguage, $translate_language, $question_title, $store_name, $text_tag_favorite, $url);
    $getInfoFavoritesDescription = ChatJsAdminSeo::getInfoFavoritesDescription($content, $urlMultilanguage, $translate_language, $question_summary_description, $store_name, $text_tag_favorite, $url);
    $getInfoFavoritesKeywords = ChatJsAdminSeo::getInfoFavoritesKeywords($content, $urlMultilanguage, $translate_language, $question_keywords, $store_name, $text_tag_favorite, $url);

    $getInfoFeaturedTitle = ChatJsAdminSeo::getInfoFeaturedTitle($content, $urlMultilanguage, $translate_language, $question_title, $store_name, $text_tag_featured, $url);
    $getInfoFeaturedDescription = ChatJsAdminSeo::getInfoFeaturedDescription($content, $urlMultilanguage, $translate_language, $question_summary_description, $store_name, $text_tag_featured, $url);
    $getInfoFeaturedKeywords = ChatJsAdminSeo::getInfoFeaturedKeywords($content, $urlMultilanguage, $translate_language, $question_keywords, $store_name, $text_tag_featured, $url);

// Recommendations
    $question_title = $this->app->getDef('text_seo_page_recommendations_title_question', ['store_name' => $store_name]);
    $question_summary_description = $this->app->getDef('text_seo_page_recommendations_description_question', ['store_name' => $store_name]);
    $question_keywords = $this->app->getDef('text_seo_page_recommendations_keywords_question', ['store_name' => $store_name]);

    $getInfoSeoRecommendationsTitle = ChatJsAdminSeo::getInfoSeoRecommendationsTitle($content, $urlMultilanguage, $translate_language, $question_title, $store_name, $url);
    $getInfoSeoRecommendationsDescription = ChatJsAdminSeo::getInfoSeoRecommendationsDescription($content, $urlMultilanguage, $translate_language, $question_summary_description, $store_name, $url);
    $getInfoSeoRecommendationsKeywords = ChatJsAdminSeo::getInfoSeoRecommendationsKeywords($content, $urlMultilanguage, $translate_language, $question_keywords, $store_name, $url);

    $output = <<<EOD
<!------------------>
<!-- ChatGpt start tag-->
<!------------------>
<!-- product seo meta title h1 -->
       {$getInfoSeoDefaultTitleH1}
<!-- product seo meta title h1 -->
       {$getInfoSeoDefaultTitle}
<!-- product seo meta description -->       
       {$getInfoSeoDefaultDescription}
<!-- product seo meta keywords -->       
       {$getInfoSeoDefaultKeywords}
<!-- product seo meta footer -->
       {$getInfoSeoDefaultFooter}
<!-- product seo  description title -->
       {$getInfoSeoProductDescriptionTitle}
<!-- product seo product description -->       
       {$getInfoSeoProductDescription}
<!-- product product meta keywords -->       
       {$getInfoSeoProductKeywords}
<!-- product seo products new title -->       
       {$getInfoSeoProductsNewTitle}
<!-- product seo products new Description -->          
       {$getInfoSeoProductsNewDescription}
<!-- product seo products new keywords -->          
       {$getInfoSeoProductsNewKeywords}
<!-- product seo specials Title -->          
       {$getInfoSeoSpecialsTitle}
<!-- product seo specials Description -->          
       {$getInfoSeoSpecialsDescription}
<!-- product seo specials keywords -->          
       {$getInfoSeoSpecialsKeywords}
<!-- product seo reviews title -->          
       {$getInfoSeoReviewsTitle}
<!-- product seo reviews description -->                
       {$getInfoSeoReviewsDescription}
<!-- product seo reviews keywords -->                
       {$getInfoSeoReviewsKeywords}
<!-- product seo favorites title -->                
       {$getInfoFavoritesTitle}
<!-- product seo favorites description -->  
       {$getInfoFavoritesDescription}
<!-- product seo favorites keywords -->         
       {$getInfoFavoritesKeywords}
<!-- product seo featured title -->                      
       {$getInfoFeaturedTitle}
<!-- product seo featured description -->  
       {$getInfoFeaturedDescription}
<!-- product seo featured keywords -->         
       {$getInfoFeaturedKeywords}
<!-- recommendations seo meta title -->
       {$getInfoSeoRecommendationsTitle}
<!-- recommendation seo meta description -->       
       {$getInfoSeoRecommendationsDescription}
<!-- recommendations seo meta keywords -->       
       {$getInfoSeoRecommendationsKeywords}
<!------------------>
<!-- ChatGpt end tag-->
<!------------------>
EOD;
    return $output;
  }
}
