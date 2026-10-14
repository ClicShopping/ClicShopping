<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\ChatGpt\Module\Hooks\ClicShoppingAdmin\Langues;

use ClicShopping\OM\HTML;
use ClicShopping\OM\Interfaces\HooksInterface;
use ClicShopping\OM\Registry;

use ClicShopping\Apps\Configuration\ChatGpt\ChatGpt as ChatGptApp;
use function defined;

/**
 * Class DeleteConfirm
 *
 * This class handles the deletion of reviews associated with a specific language ID.
 * It checks if the Reviews application is registered and performs the deletion
 * when the appropriate conditions are met.
 *
 */
class DeleteConfirm implements HooksInterface
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
    if (!Registry::exists('ChatGpt')) {
      Registry::set('ChatGpt', new ChatGptApp());
    }

    $this->app = Registry::get('ChatGpt');
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
      $this->app->db->delete('categories_embedding', ['language_id' => $id]);
      $this->app->db->delete('manufacturers_embedding', ['language_id' => $id]);
      $this->app->db->delete('products_embedding', ['language_id' => $id]);
      $this->app->db->delete('pages_manager_embedding', ['language_id' => $id]);
      $this->app->db->delete('reviews_embedding ', ['language_id' => $id]);
      $this->app->db->delete('reviews_sentiment_embedding  ', ['language_id' => $id]);
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