<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Customers\Reviews\Module\Hooks\ClicShoppingAdmin\Langues;

use ClicShopping\OM\Interfaces\HooksInterface;
use ClicShopping\OM\Registry;

use ClicShopping\Apps\Customers\Reviews\Reviews as ReviewsApp;
use ClicShopping\Apps\Configuration\Langues\Classes\ClicShoppingAdmin\LanguageAdmin;
use function defined;

class Insert implements HooksInterface
{
  public mixed $app;
  private mixed $lang;

  /**
   * Initializes the Reviews application by checking its existence in the Registry.
   * If not found, a new instance is created and set. Retrieves and assigns the
   * Reviews application and Language objects from the Registry to class properties.
   *
   * @return void
   */
  public function __construct()
  {
    if (!Registry::exists('Reviews')) {
      Registry::set('Reviews', new ReviewsApp());
    }

    $this->app = Registry::get('Reviews');
    $this->lang = Registry::get('Language');
  }

  /**
   * Inserts new language-specific data into the `reviews_description` and `reviews_sentiment_description` tables
   * by duplicating existing entries with a new language ID.
   *
   * @return void
   */
  private function insert(): void
  {
    $insert_language_id = LanguageAdmin::getLatestLanguageID();

    $Qreviews = $this->app->db->prepare('select r.reviews_id as orig_review_id,
                                                 rd.*
                                          from :table_reviews r left join :table_reviews_description rd on r.reviews_id = rd.reviews_id
                                          where rd.language_id = :language_id
                                          ');

    $Qreviews->bindInt(':language_id', $this->lang->getId());
    $Qreviews->execute();

    while ($Qreviews->fetch()) {
      $cols = $Qreviews->toArray();

      $cols['reviews_id'] = $cols['orig_review_id'];
      $cols['language_id'] = (int)$insert_language_id;

      unset($cols['orig_review_id']);

      $this->app->db->save('reviews_description', $cols);
    }

// Table review_sentiment
    $QreviewsSentiment = $this->app->db->prepare('select r.id as orig_id,
                                                         rd.*
                                                  from :table_reviews_sentiment r left join :table_reviews_sentiment_description pd on r.id = rd.id
                                                  where rd.language_id = :language_id
                                                  ');

    $QreviewsSentiment->bindInt(':language_id', $this->lang->getId());
    $QreviewsSentiment->execute();

    while ($QreviewsSentiment->fetch()) {
      $cols = $QreviewsSentiment->toArray();

      $cols['id'] = $cols['orig_id'];
      $cols['language_id'] = (int)$insert_language_id;

      unset($cols['orig_id']);

      $this->app->db->save('reviews_sentiment_description', $cols);
    }
  }

  /**
   * Executes the primary logic for the Reviews application.
   *
   * This method checks whether the Reviews application is enabled and performs
   * specific actions based on query parameters, such as invoking the insert method.
   *
   * @return bool Returns false if the Reviews application is disabled; otherwise, no explicit return value.
   */
  public function execute()
  {
    if (!defined('CLICSHOPPING_APP_REVIEWS_RV_STATUS') || CLICSHOPPING_APP_REVIEWS_RV_STATUS == 'False') {
      return false;
    }

    if (isset($_GET['Langues'], $_GET['Insert'])) {
      $this->insert();
    }
  }
}