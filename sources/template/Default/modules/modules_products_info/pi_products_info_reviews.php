<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\DateTime;
  use ClicShopping\OM\Hash;
  use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

class pi_products_info_reviews
{
  public string $code;
  public string $group;
  public $title;
  public $description;
  public int|null $sort_order = 0;
  public bool $enabled = false;

  public function __construct()
  {
    $this->code = get_class($this);
    $this->group = basename(__DIR__);

    $this->title = CLICSHOPPING::getDef('module_products_info_reviews');
    $this->description = CLICSHOPPING::getDef('module_products_info_reviews_description');

    if (\defined('MODULE_PRODUCTS_INFO_REVIEWS_STATUS')) {
      $this->sort_order = \defined('MODULE_PRODUCTS_INFO_REVIEWS_SORT_ORDER') ? (int)MODULE_PRODUCTS_INFO_REVIEWS_SORT_ORDER : 0;
      $this->enabled = \defined('MODULE_PRODUCTS_INFO_REVIEWS_STATUS') ? (MODULE_PRODUCTS_INFO_REVIEWS_STATUS == 'True') : false;
    }
  }

  public function execute()
  {
    $CLICSHOPPING_ProductsCommon = Registry::get('ProductsCommon');

    if ($CLICSHOPPING_ProductsCommon->getID()) {
      $content_width = \defined('MODULE_PRODUCTS_INFO_REVIEWS_CONTENT_WIDTH') ? (int)MODULE_PRODUCTS_INFO_REVIEWS_CONTENT_WIDTH : 12;

      $CLICSHOPPING_Db = Registry::get('Db');
      $CLICSHOPPING_Template = Registry::get('Template');
      $CLICSHOPPING_Language = Registry::get('Language');
      $CLICSHOPPING_Customer = Registry::get('Customer');
      $customer_id = $CLICSHOPPING_Customer->getID();
      $products_id = $CLICSHOPPING_ProductsCommon->getID();
      $ajax_url = CLICSHOPPING::getConfig('http_server', 'Shop') .  CLICSHOPPING::getConfig('http_path', 'Shop') . 'ext/ajax/voted_review.php';

//*******************************************
// products review
//********************************************
      $Qreviews = $CLICSHOPPING_Db->prepare('select r.reviews_id,
                                                       left(rd.reviews_text, :limitText ) as reviews_text,
                                                       r.reviews_rating,
                                                       r.date_added,
                                                       r.status,
                                                       r.customers_name,
                                                       r.customers_tag
                                               from :table_reviews r,
                                                    :table_reviews_description rd
                                               where r.products_id = :products_id
                                               and r.reviews_id = rd.reviews_id
                                               and rd.languages_id = :languages_id
                                               and r.status = 1
                                               order by r.reviews_rating desc,
                                                        r.date_added desc
                                               limit :limit
                                           ');
      $Qreviews->bindInt(':products_id', $products_id);
      $Qreviews->bindInt(':languages_id', $CLICSHOPPING_Language->getId());
      $Qreviews->bindInt(':limitText', (int)MODULE_PRODUCTS_INFO_REVIEWS_NUMBER_WORDS);
      $Qreviews->bindInt(':limit', (int)MODULE_PRODUCTS_INFO_REVIEWS_NUMBER_COMMENTS);

      $Qreviews->execute();

//senitment
      $Qsentiment = $CLICSHOPPING_Db->prepare('select rsd.description,
                                                      rsd.analysis_json,
                                                      rs.review_count,
                                                      rs.sentiment_approved
                                                from :table_reviews_sentiment rs,
                                                     :table_reviews_sentiment_description rsd
                                                where rs.id = rsd.id
                                                and rs.products_id = :products_id
                                                and rsd.language_id = :language_id
                                              ');
      $Qsentiment->bindInt(':products_id', $products_id);
      $Qsentiment->bindInt(':language_id', $CLICSHOPPING_Language->getId());
      $Qsentiment->execute();

      $count_review = $Qreviews->rowCount();

//*******************************************
// customers_feedback
//********************************************
      $QorderProducts = $CLICSHOPPING_Db->prepare('select products_id,
                                                          orders_id
                                                     from :table_orders_products
                                                     where products_id = :products_id
                                                    ');
      $QorderProducts->bindValue(':products_id', $CLICSHOPPING_ProductsCommon->getID());
      $QorderProducts->execute();

      $products_reviews_content = '<!-- Start products_REVIEWS -->' . "\n";
      $products_reviews_content .= '<div class="mt-1"></div>';
      $products_reviews_content .= '<div class="' . $content_width . '">';
      $products_reviews_content .= '<hr>';
      $products_reviews_content .= '<div class="mt-1"></div>';

      if ($count_review > 0) {
        $products_reviews_content .= '<div class="moduleProductsInfoReviewsRow">';
        $products_reviews_content .= '<div class="moduleProductsInfoReviewsTitle">';
        $products_reviews_content .= '<span class="page-title moduleProductsInfoReviewsTitle"><h3>' . CLICSHOPPING::getDef('heading_rewiews') . ' ' . $CLICSHOPPING_ProductsCommon->getProductsName() . '</h3></span>';
        $products_reviews_content .= '</div>';
        $products_reviews_content .= '<div class="mt-1"></div>';

       if ($Qsentiment->valueInt('sentiment_approved') == 1) {
          $products_reviews_content .= '<div class="alert alert-info" role="alert">';
          $products_reviews_content .= '<span class="moduleProductsInfoReviewsTitleAiSentiment"><h4>' . CLICSHOPPING::getDef('modules_products_reviews_info_content_text_customers_ai_sentiment') . ' ' . $CLICSHOPPING_ProductsCommon->getProductsName() . '</h4></span>';

          $analysis = \ClicShopping\Apps\Customers\Reviews\Classes\ClicShoppingAdmin\SentimentAnalysisData::fromJson(
            $Qsentiment->value('analysis_json'),
            $Qsentiment->value('description')
          );
          $reviewCount = $Qsentiment->valueInt('review_count');

          $products_reviews_content .= '<div class="moduleProductsInfoAiSentiment">';
          $products_reviews_content .= '<p class="aiSentimentMeta"><em>' . CLICSHOPPING::getDef('module_products_reviews_ai_based_on', ['count' => $reviewCount]) . '</em></p>';

          if ($analysis->isStructured()) {
            $products_reviews_content .= '<span class="badge text-bg-secondary moduleProductsInfoAiSentimentBadge">' . HTML::outputProtected($analysis->getDominantSentiment()) . '</span>';

            if ($analysis->getStrengths() !== []) {
              $products_reviews_content .= '<p class="mt-2"><strong>' . CLICSHOPPING::getDef('module_products_reviews_ai_strengths') . '</strong></p><ul>';
              foreach ($analysis->getStrengths() as $strength) {
                $products_reviews_content .= '<li>' . HTML::outputProtected($strength) . '</li>';
              }
              $products_reviews_content .= '</ul>';
            }

            if ($analysis->getIssues() !== []) {
              $products_reviews_content .= '<p><strong>' . CLICSHOPPING::getDef('module_products_reviews_ai_issues') . '</strong></p><ul>';
              foreach ($analysis->getIssues() as $issue) {
                $products_reviews_content .= '<li>' . HTML::outputProtected($issue) . '</li>';
              }
              $products_reviews_content .= '</ul>';
            }

            if ($analysis->getThemes() !== []) {
              $products_reviews_content .= '<p class="moduleProductsInfoAiSentimentThemes">';
              foreach ($analysis->getThemes() as $theme) {
                $products_reviews_content .= ' <span class="badge text-bg-light">' . HTML::outputProtected($theme['label']) . '</span>';
              }
              $products_reviews_content .= '</p>';
            }
          }

          $products_reviews_content .= '<p class="aiSentimentSummary">' . nl2br(HTML::outputProtected($analysis->getSummary())) . '</p>';
          $products_reviews_content .= '</div>';
          $products_reviews_content .= '<div class="mt-1"></div>';

         // Uniq ID for every button
         $uniqueId = 0;

         $QreviewsVoteYes = $CLICSHOPPING_Db->prepare('select count(vote) as countVoteYes
                                                         from :table_reviews_vote
                                                         where products_id = :products_id
                                                         and vote = 1
                                                         and sentiment = 1	
                                                         and reviews_id = 0
                                                      ');
         $QreviewsVoteYes->bindInt(':products_id', $products_id);
         $QreviewsVoteYes->execute();

         $QreviewsVoteNo = $CLICSHOPPING_Db->prepare('select count(vote) as countVoteNo
                                                         from :table_reviews_vote
                                                         where products_id = :products_id
                                                         and vote = 0
                                                         and sentiment = 0
                                                         and reviews_id = 0
                                                        ');
         $QreviewsVoteNo->bindInt(':products_id', $products_id);
         $QreviewsVoteNo->execute();

         $products_reviews_content .= '<div class="moduleProductsInfoReviewCustomersNotice">';
         $products_reviews_content .= CLICSHOPPING::getDef('modules_products_reviews_text_useful_vote');

// Bouton YES
         $products_reviews_content .= '<span class="buttonVoteYes">';
         $products_reviews_content .= ' <button type="button" class="toggleButton yesButton" id="' . $uniqueId . '_yesButton" data-unique-id="' . $uniqueId . '" data-product-id="' . $products_id . '" data-customer-id="' . $customer_id . '" data-ajax-url="' . $ajax_url . '"><strong>' . CLICSHOPPING::getDef('modules_products_reviews_text_useful_vote_yes') . '</strong></button> ';
         $products_reviews_content .= ' <span class="toggleValue yesValue">(' . $QreviewsVoteYes->valueInt('countVoteYes') . ')</span> ';
         $products_reviews_content .= '</span>';

// Bouton NO
         $products_reviews_content .= '<span class="buttonVoteNo">';
         $products_reviews_content .= ' <button type="button" class="toggleButton noButton" id="' . $uniqueId . '_noButton" data-unique-id="' . $uniqueId . '" data-product-id="' . $products_id . '" data-customer-id="' . $customer_id . '" data-ajax-url="' . $ajax_url . '"><strong>' . CLICSHOPPING::getDef('modules_products_reviews_text_useful_vote_no') . '</strong></button> ';
         $products_reviews_content .= ' <span class="toggleValue noValue">(' . $QreviewsVoteNo->valueInt('countVoteNo') . ')</span> ';
         $products_reviews_content .= ' <span class="thankYouMessage" style="display: none;">' . CLICSHOPPING::getDef('modules_products_reviews_text_useful_vote_thank_you') . '</span>';
         $products_reviews_content .= '</span>';
         $products_reviews_content .= '</div>';

         $products_reviews_content .= '</div>';
         $products_reviews_content .= '<div class="mt-1"></div>';
        }

        $products_reviews_content .= '<div class="clearfix"></div>';
        $products_reviews_content .= '<hr>';
        $products_reviews_content .= '<div class="d-flex flex-wrap">';

        while ($Qreviews->fetch()) {
          $customer_tag = $Qreviews->value('customers_tag');
          $customer_tag = explode(',', $customer_tag);
          $reviews_id = $Qreviews->valueInt('reviews_id');

          $customer_name = '*** ' . HTML::outputProtected(substr(HASH::displayDecryptedDataText($Qreviews->value('customers_name')) . ' ', 4, -4)) . ' ***';
          $products_reviews_content .= '<div class="col-md-12">';
          $products_reviews_content .= '<span class="moduleProductsInfoTextReviewByName" itemprop="author">';
          $products_reviews_content .= '<a href="' . CLICSHOPPING::link(null, 'Products&ReviewsInfo&products_id=' . $CLICSHOPPING_ProductsCommon->getID() . '&reviews_id=' . $reviews_id) . '">' . CLICSHOPPING::getDef('text_review_by', ['customer_name' => $customer_name]) . '</a>';
          $products_reviews_content .= '</span>';
          $products_reviews_content .= '<span class="float-end" itemprop="reviewRating" itemscope itemtype="https://schema.org/Rating">';
          $products_reviews_content .= '<meta itemprop="worstRating" content = "1">';
          $products_reviews_content .= '<span class="col-md-12 productsInfoReviewsRating" itemprop="ratingValue">' . HTML::stars($Qreviews->valueInt('reviews_rating')) . '</span>';
          $products_reviews_content .= '</span>';
          $products_reviews_content .= '</div>';
          $products_reviews_content .= '<div class="col-md-12 moduleProductsInfoDateReviewAdded" itemprop="datePublished" content="' . DateTime::toShort($Qreviews->value('date_added')) . '">';
          $products_reviews_content .= '<span class="moduleProductsInfoDateReviewAdded">' . CLICSHOPPING::getDef('text_review_date_added') . ' ' . DateTime::toShort($Qreviews->value('date_added')) . '</span>';
          $products_reviews_content .= '</div>';
          $products_reviews_content .= '<div class="col-md-12">';
          $products_reviews_content .= '<div class="moduleProductsInfoReviewText" itemprop="description">';
          $products_reviews_content .= HTML::breakString(HTML::outputProtected($Qreviews->value('reviews_text')), 60, '-<br />') . ((\strlen($Qreviews->value('reviews_text')) >= MODULE_PRODUCTS_INFO_REVIEWS_NUMBER_WORDS) ? '..' : '') . '<br />';
          $products_reviews_content .= '</div>';

          if (\defined('MODULE_PRODUCTS_INFO_REVIEWS_CUSTOMERS_SENTIMENT_TAG') && MODULE_PRODUCTS_INFO_REVIEWS_CUSTOMERS_SENTIMENT_TAG == 'True') {
            if(is_array($customer_tag)) {
              $products_reviews_content .= '<div class="moduleProductsInfoReviewCustomersBadge">';
              $products_reviews_content .= '<div class="row">';
              $products_reviews_content .= '<span class="col-md-11 module_products_info_reviews_customerTag">';
              $products_reviews_content .= CLICSHOPPING::getDef('modules_products_reviews_text_customers_badge');

              foreach ($customer_tag as $value) {
                $products_reviews_content .= ' <span class="badge text-bg-primary">' . $value . '</span> ';
              }

              $products_reviews_content .= '</span>';
              $products_reviews_content .= '<div class="mt-1"></div>';
              $products_reviews_content .= '</div>';
              $products_reviews_content .= '</div>';
            }
          }

          if (\defined('MODULE_PRODUCTS_INFO_REVIEWS_CUSTOMERS_DISPLAY_VOTE') && MODULE_PRODUCTS_INFO_REVIEWS_CUSTOMERS_DISPLAY_VOTE == 'True') {
          // Uniq ID for every button
            $uniqueId = $reviews_id;

            $QreviewsVoteYes = $CLICSHOPPING_Db->prepare('select count(vote) as countVoteYes
                                                         from :table_reviews_vote
                                                         where products_id = :products_id
                                                         and vote = 1
                                                         and reviews_id = :reviews_id	
                                                        ');
            $QreviewsVoteYes->bindInt(':products_id', $products_id);
            $QreviewsVoteYes->bindInt('reviews_id', $reviews_id);
            $QreviewsVoteYes->execute();

            $QreviewsVoteNo = $CLICSHOPPING_Db->prepare('select count(vote) as countVoteNo
                                                         from :table_reviews_vote
                                                         where products_id = :products_id
                                                         and vote = 0
                                                         and reviews_id = :reviews_id	
                                                        ');
            $QreviewsVoteNo->bindInt(':products_id', $products_id);
            $QreviewsVoteNo->bindInt('reviews_id', $reviews_id);
            $QreviewsVoteNo->execute();

            $products_reviews_content .= '<div class="moduleProductsInfoReviewCustomersNotice">';
            $products_reviews_content .= CLICSHOPPING::getDef('modules_products_reviews_text_useful_vote');
// Bouton YES
            $products_reviews_content .= '<span class="buttonVoteYes">';
            $products_reviews_content .= ' <button type="button" class="toggleButton yesButton" id="' . $uniqueId . '_yesButton" data-unique-id="' . $uniqueId . '" data-product-id="' . $products_id . '" data-customer-id="' . $customer_id . '" data-ajax-url="' . $ajax_url . '"><strong>' . CLICSHOPPING::getDef('modules_products_reviews_text_useful_vote_yes') . '</strong></button> ';
            $products_reviews_content .= ' <span class="toggleValue yesValue">(' . $QreviewsVoteYes->valueInt('countVoteYes') . ')</span> ';
            $products_reviews_content .= '</span>';
// Bouton NO
            $products_reviews_content .= '<span class="buttonVoteNo">';
            $products_reviews_content .= ' <button type="button" class="toggleButton noButton" id="' . $uniqueId . '_noButton" data-unique-id="' . $uniqueId . '" data-product-id="' . $products_id . '" data-customer-id="' . $customer_id . '" data-ajax-url="' . $ajax_url . '"><strong>' . CLICSHOPPING::getDef('modules_products_reviews_text_useful_vote_no') . '</strong></button> ';
            $products_reviews_content .= ' <span class="toggleValue noValue">(' . $QreviewsVoteNo->valueInt('countVoteNo') . ')</span> ';
            $products_reviews_content .= ' <span class="thankYouMessage" style="display: none;">' . CLICSHOPPING::getDef('modules_products_reviews_text_useful_vote_thank_you') . '</span>';
            $products_reviews_content .= '</span>';
            $products_reviews_content .= '</div>';

            $products_reviews_content .= '<script defer src="' . CLICSHOPPING::link($CLICSHOPPING_Template->getTemplateDefaultJavaScript('clicshopping/voted_review.js')) . '"></script>' . "\n";
            $products_reviews_content .= '<div class="mt-1"></div>';
          }

          $products_reviews_content .= '</div>';
          $products_reviews_content .= '<hr>';
        }

//*******************************************
// customers_feedback
//********************************************
        if ($count_review !== 0) {
          $details_button = HTML::button(CLICSHOPPING::getDef('button_all_reviews'), null, CLICSHOPPING::link(null, 'Products&Reviews&products_id=' . $CLICSHOPPING_ProductsCommon->getID()), 'info');
          $write_button = HTML::button(CLICSHOPPING::getDef('button_write_review'), null, CLICSHOPPING::link(null, 'Products&ReviewsWrite&products_id=' . $CLICSHOPPING_ProductsCommon->getID()), 'success');

          $products_reviews_content .= '<div class="clearfix"></div>';
          $products_reviews_content .= '<div class="mt-1"></div>';
          $products_reviews_content .= '<div class="row col-md-12">';
          $products_reviews_content .= '<span class="col-md-6">' . $details_button . '</span>';
          $products_reviews_content .= '<span class="col-md-6 text-end">' . $write_button . '</span>';
          $products_reviews_content .= '</div>';
          $products_reviews_content .= '<div class="mt-1"></div>';
          $products_reviews_content .= '<div class="mt-1"></div>';
        }
      }

      if ($count_review == 0) {
        $write_button = HTML::button(CLICSHOPPING::getDef('button_write_review'), null, CLICSHOPPING::link(null, 'Products&ReviewsWrite&products_id=' . $CLICSHOPPING_ProductsCommon->getID()), 'info');
        $products_reviews_content .= '<div class="py-3"></div>';
        $products_reviews_content .= '<div class="col-md-12">';
        $products_reviews_content .= '<div class="moduleProductsInfoNoReview">' . CLICSHOPPING::getDef('text_info_no_reviews') . '</div>';
        $products_reviews_content .= '<div class="text-end">' . $write_button . '</div>';
        $products_reviews_content .= '<div>';
      }
      $products_reviews_content .= '<div class="mt-1"></div>';
      $products_reviews_content .= '</div>' . "\n";
      $products_reviews_content .= '<div class="mt-1"></div>';
      $products_reviews_content .= '<!-- end products_REVIEWS -->' . "\n";

      $CLICSHOPPING_Template->addBlock($products_reviews_content, $this->group);
    }
  } // public function execute

  public function isEnabled()
  {
    return $this->enabled;
  }

  public function check()
  {
    return \defined('MODULE_PRODUCTS_INFO_REVIEWS_STATUS');
  }

  public function install()
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Do you want to enable this module ?',
        'configuration_key' => 'MODULE_PRODUCTS_INFO_REVIEWS_STATUS',
        'configuration_value' => 'True',
        'configuration_description' => 'Do you want to enable this module in your shop ?',
        'configuration_group_id' => '6',
        'sort_order' => '1',
        'set_function' => 'clic_cfg_set_boolean_value(array(\'True\', \'False\'))',
        'date_added' => 'now()'
      ]
    );

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Please select the width of the display?',
        'configuration_key' => 'MODULE_PRODUCTS_INFO_REVIEWS_CONTENT_WIDTH',
        'configuration_value' => '12',
        'configuration_description' => 'Please enter a number between 1 and 12',
        'configuration_group_id' => '6',
        'sort_order' => '1',
        'set_function' => 'clic_cfg_set_content_module_width_pull_down',
        'date_added' => 'now()'
      ]
    );

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'How many comments would you like to display ?',
        'configuration_key' => 'MODULE_PRODUCTS_INFO_REVIEWS_NUMBER_COMMENTS',
        'configuration_value' => '5',
        'configuration_description' => 'Please indicate the number of comments you wish to display ?',
        'configuration_group_id' => '6',
        'sort_order' => '2',
        'set_function' => '',
        'date_added' => 'now()'
      ]
    );

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'How many words do you want to display ?',
        'configuration_key' => 'MODULE_PRODUCTS_INFO_REVIEWS_NUMBER_WORDS',
        'configuration_value' => '300',
        'configuration_description' => 'Please indicate the number of words you wish to display ?',
        'configuration_group_id' => '6',
        'sort_order' => '3',
        'set_function' => '',
        'date_added' => 'now()'
      ]
    );

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Do you want to display the customer sentiment tage ?',
        'configuration_key' => 'MODULE_PRODUCTS_INFO_REVIEWS_CUSTOMERS_SENTIMENT_TAG',
        'configuration_value' => 'True',
        'configuration_description' => 'Do you want to enable this option in your shop ?',
        'configuration_group_id' => '6',
        'sort_order' => '1',
        'set_function' => 'clic_cfg_set_boolean_value(array(\'True\', \'False\'))',
        'date_added' => 'now()'
      ]
    );

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Do you want to display the customers vote ?',
        'configuration_key' => 'MODULE_PRODUCTS_INFO_REVIEWS_CUSTOMERS_DISPLAY_VOTE',
        'configuration_value' => 'True',
        'configuration_description' => 'Do you want to enable this option in your shop ?',
        'configuration_group_id' => '6',
        'sort_order' => '3',
        'set_function' => 'clic_cfg_set_boolean_value(array(\'True\', \'False\'))',
        'date_added' => 'now()'
      ]
    );

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Sort order',
        'configuration_key' => 'MODULE_PRODUCTS_INFO_REVIEWS_SORT_ORDER',
        'configuration_value' => '700',
        'configuration_description' => 'Sort order of display. Lowest is displayed first. The sort order must be different on every module',
        'configuration_group_id' => '6',
        'sort_order' => '4',
        'set_function' => '',
        'date_added' => 'now()'
      ]
    );
  }

  public function remove()
  {
    return Registry::get('Db')->exec('delete from :table_configuration where configuration_key in ("' . implode('", "', $this->keys()) . '")');
  }

  public function keys()
  {
    return array(
      'MODULE_PRODUCTS_INFO_REVIEWS_STATUS',
      'MODULE_PRODUCTS_INFO_REVIEWS_CONTENT_WIDTH',
      'MODULE_PRODUCTS_INFO_REVIEWS_NUMBER_COMMENTS',
      'MODULE_PRODUCTS_INFO_REVIEWS_NUMBER_WORDS',
      'MODULE_PRODUCTS_INFO_REVIEWS_CUSTOMERS_SENTIMENT_TAG',
      'MODULE_PRODUCTS_INFO_REVIEWS_CUSTOMERS_DISPLAY_VOTE',
      'MODULE_PRODUCTS_INFO_REVIEWS_SORT_ORDER'
    );
  }
}
