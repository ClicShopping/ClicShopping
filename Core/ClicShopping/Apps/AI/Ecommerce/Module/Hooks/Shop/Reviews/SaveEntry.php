<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\AI\Ecommerce\Module\Hooks\Shop\Reviews;

use ClicShopping\Apps\Configuration\ChatGpt\Classes\Shop\GptShop;
use ClicShopping\Apps\Customers\reviews\Classes\Shop\ReviewsClass;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Interfaces\HooksInterface;
use ClicShopping\OM\Registry;

class SaveEntry implements HooksInterface
{
  protected mixed $productsCommon;
  protected mixed $reviewsShop;

  /**
   * Constructor method initializes the required class properties by registering
   * and retrieving common product and review-related dependencies.
   *
   * @return void
   */
  public function __construct()
  {
    $this->productsCommon = Registry::get('ProductsCommon');
    Registry::set('ReviewsClass', new ReviewsClass());

    $this->reviewsShop = Registry::get('ReviewsClass');
  }

  /**
   * Retrieves the latest review ID associated with a given product.
   *
   * Queries the database to find the highest review ID for the specified product ID.
   * If no reviews are found, the method returns false.
   *
   * @return int|bool Returns the review ID as an integer if found, or false if no review exists.
   */
  private static function getReviewsId(): int|bool
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $products_id = HTML::sanitize($_GET['products_id']);

    $Qreviews = $CLICSHOPPING_Db->prepare('select reviews_id
                                              from :table_reviews
                                              where products_id = :products_id
                                              order by reviews_id desc
                                              limit 1
                                             ');
    $Qreviews->bindInt('products_id', $products_id);
    $Qreviews->execute();

    $result = $Qreviews->ValueInt('reviews_id');

    if (empty($result)) {
      return false;
    }

    return $result;
  }

  /**
   * Retrieves the customer reviews text based on the review ID and language ID.
   *
   * The method queries the database for the review text associated with the given
   * review ID and current language ID. If no review is found, the method returns false.
   *
   * @return string|bool Returns the reviews text as a string if found, otherwise false.
   */
  private static function getCustomerReviews(): string|bool
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_Language = Registry::get('Language');

    $Qreviews = $CLICSHOPPING_Db->prepare('select rd.reviews_text
                                              from :table_reviews r,
                                                   :table_reviews_description rd
                                              where r.reviews_id = rd.reviews_id
                                              and rd.languages_id = :languages_id
                                              and r.reviews_id = :reviews_id
                                             ');

    $Qreviews->bindInt(':reviews_id', self::getReviewsId());
    $Qreviews->bindInt(':languages_id', $CLICSHOPPING_Language->getId());

    $Qreviews->execute();

    $result = $Qreviews->value('reviews_text');

    if (empty($result)) {
      return false;
    }

    return $result;
  }

  /**
   * Saves the review tag for a specific review based on its ID.
   *
   * @param int $id The unique identifier of the review.
   * @param string $tag The tag to be associated with the review.
   * @return void
   */
  private static function saveReviews(int $id, string $tag): void
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $sql_array = ['customers_tag' => $tag];
    $update_array = ['reviews_id' => $id];

    $CLICSHOPPING_Db->save('reviews', $sql_array, $update_array);
  }

  /**
   * Executes the sentiment analysis process for customer reviews.
   *
   * This method validates the status of the reviews module, checks the GPT integration,
   * and processes customer reviews to generate sentiment tags. If all necessary conditions
   * are met, it fetches a review, formulates a prompt for GPT, and saves the generated sentiment tags.
   *
   * @return bool|string Returns false if any prerequisite condition fails or the customer review is unavailable.
   *                     Returns the output result of the process if it is successful.
   */
  public function execute()
  {
    $CLICSHOPPING_Language = Registry::get('Language');

    if (!\defined('CLICSHOPPING_APP_REVIEWS_RV_STATUS') || CLICSHOPPING_APP_REVIEWS_RV_STATUS == 'False') {
      return false;
    }

    if (GptShop::checkGptStatus() === false) {
      return false;
    }


    if (CLICSHOPPING_APP_REVIEWS_RV_SENTIMENT_TAG == 'False') {
      return false;
    }

    $customer_review = self::getCustomerReviews();

    if ($customer_review === false) {
      return false;
    }

    $language_name = $CLICSHOPPING_Language->getLanguagesName($CLICSHOPPING_Language->getId());

    $question = 'Task: Sentiment Analysis for eCommerce product.
- Provide the result in this Language: ' . $language_name . '
- Format: Return ONLY a comma-separated list of maximum 6 tags.
- Constraint 1: If the content is not related to an ecommerce review, return NONE.
- Constraint 2: No introductory text, no explanations, no quotes.
- Review to analyze: ' . $customer_review;

    $tag = GptShop::getGptResponse($question, 15, 0.7);

    if (self::getReviewsId() !== false && !empty($tag)) {
      self::saveReviews(self::getReviewsId(), $tag);
    }
  }
}