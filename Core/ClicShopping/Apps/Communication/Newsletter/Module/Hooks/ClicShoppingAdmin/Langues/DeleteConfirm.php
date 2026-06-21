<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Communication\Newsletter\Module\Hooks\ClicShoppingAdmin\Langues;

use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;
use ClicShopping\OM\Interfaces\HooksInterface;

use ClicShopping\Apps\Communication\Newsletter\Newsletter as NewsletterApp;

class DeleteConfirm implements HooksInterface
{
  public mixed $app;

  /**
   * Initializes the Newsletter application by checking its existence in the registry.
   * If it does not exist, a new instance of NewsletterApp is created and added to the registry.
   * Sets the application instance as a class property.
   *
   * @return void
   */
  public function __construct()
  {
    if (!Registry::exists('Newsletter')) {
      Registry::set('Newsletter', new NewsletterApp());
    }

    $this->app = Registry::get('Newsletter');
  }

  /**
   * Deletes a record in the 'newsletters' database table based on the given ID.
   *
   * @param int $id The ID used to identify the record to delete.
   *                            If null, no action is taken.
   * @return void
   */
  private function delete(int $id)
  {
    if (!\is_null($id)) {
      $this->app->db->delete('newsletters', ['languages_id' => $id]);
    }
  }

  /**
   * Executes the main logic for the method, handling specific conditions and actions.
   *
   * @return bool Returns false if the application newsletter status is not defined or set to 'False'.
   */
  public function execute()
  {
    if (!\defined('CLICSHOPPING_APP_NEWSLETTER_NL_STATUS') || CLICSHOPPING_APP_NEWSLETTER_NL_STATUS == 'False') {
      return false;
    }

    if (isset($_GET['DeleteConfirm'])) {
      $id = HTML::sanitize($_GET['lID']);
      $this->delete($id);
    }
  }
}