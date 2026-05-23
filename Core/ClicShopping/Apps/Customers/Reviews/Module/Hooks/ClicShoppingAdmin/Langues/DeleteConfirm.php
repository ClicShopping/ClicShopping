<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Customers\Reviews\Module\Hooks\ClicShoppingAdmin\Langues;

use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

use ClicShopping\Apps\Customers\Reviews\Reviews as ReviewsApp;
use function defined;

class DeleteConfirm implements \ClicShopping\OM\Modules\HooksInterface
{
  public mixed $app;

  /**
   * Initializes the Reviews application by checking its registry existence.
   *
   * If the Reviews application is not already registered, it creates a new instance
   * and stores it in the registry. The application is then retrieved and assigned
   * to the app property.
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
   * Deletes records associated with the specified language ID.
   *
   * @param int $id The ID of the language whose associated records should be deleted.
   * @return void
   */
  private function delete(int $id): void
  {
    if (!\is_null($id)) {
      $this->app->db->delete('reviews_description', ['language_id' => $id]);
      $this->app->db->delete('reviews_sentiment_description', ['language_id' => $id]);
    }
  }

  /**
   * Executes the main logic for handling the deletion of reviews based on the status and confirmation conditions.
   *
   * @return bool Returns false if the application reviews status is not defined or set to 'False'.
   */
  public function execute()
  {
    if (!defined('CLICSHOPPING_APP_REVIEWS_RV_STATUS') || CLICSHOPPING_APP_REVIEWS_RV_STATUS == 'False') {
      return false;
    }

    if (isset($_GET['DeleteConfirm'])) {
      $id = HTML::sanitize($_GET['lID']);
      $this->delete($id);
    }
  }
}