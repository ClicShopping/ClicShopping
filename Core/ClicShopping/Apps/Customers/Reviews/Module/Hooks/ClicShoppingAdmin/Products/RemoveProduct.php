<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Customers\Reviews\Module\Hooks\ClicShoppingAdmin\Products;

use ClicShopping\OM\HTML;
use ClicShopping\OM\Interfaces\HooksInterface;
use ClicShopping\OM\Registry;

use ClicShopping\Apps\Customers\Reviews\Reviews as ReviewsApp;
use function is_null;

class RemoveProduct implements HooksInterface
{
  public mixed $app;

  /**
   * Initializes a new instance of the class and sets up the application registry for 'Reviews'.
   *
   * @return void
   */
  public function __construct()
  {
    if (!Registry::exists('Reviews')) {
      Registry::set('Reviews', new ReviewsApp());
    }

    $this->app = Registry::get('Reviews');
  }

  /**
   * Removes all reviews associated with a specific product ID.
   *
   * @param int|null $id The ID of the product whose reviews are to be removed. If null, no action is taken.
   * @return void
   */
  private function removeReviews(int|null $id): void
  {
    if (!is_null($id)) {
      $Qreviews = $this->app->db->get('reviews', 'reviews_id', ['products_id' => $id]);

      if ($Qreviews->fetch()) {
        $this->app->db->delete('reviews', ['products_id' => $id]);

        while ($Qreviews->fetch()) {
          $this->app->db->delete('reviews_description', ['reviews_id' => $Qreviews->valueInt('reviews_id')]);
        }
      }
    }
  }

  /**
   * Removes reviews sentiment data associated with a specific product ID.
   *
   * @param int|null $id The ID of the product whose reviews sentiment data is to be removed. If null, no action is taken.
   * @return void
   */
  private function removeReviewsSentiment(int|null $id): void
  {
    if (!is_null($id)) {
      $QreviewsSentiment = $this->app->db->get('reviews_sentiment', 'id', ['products_id' => $id]);

      if ($QreviewsSentiment->fetch()) {
        $this->app->db->delete('reviews', ['products_id' => $id]);

        while ($QreviewsSentiment->fetch()) {
          $this->app->db->delete('reviews_sentiment_description', ['id' => $QreviewsSentiment->valueInt('id')]);
        }
      }
    }
  }

  /**
   *
   * Executes the removal of reviews and their corresponding sentiments based on input data.
   *
   * @return void
   */
  public function execute()
  {
    if (isset($_POST['selected']) && \is_array($_POST['selected']) && !empty($_POST['selected'])) {
      foreach ($_POST['selected'] as $id) {
        $this->removeReviews($id);
        $this->removeReviewsSentiment($id);
      }
    } elseif (isset($_POST['products_id'])) {
      $id = HTML::sanitize($_POST['products_id']);
      $this->removeReviews($id);
      $this->removeReviewsSentiment($id);
    }
  }
}