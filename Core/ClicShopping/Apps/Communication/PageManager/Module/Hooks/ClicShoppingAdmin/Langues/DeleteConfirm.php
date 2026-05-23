<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Communication\PageManager\Module\Hooks\ClicShoppingAdmin\Langues;

use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

use ClicShopping\Apps\Communication\PageManager\PageManager as PageManagerApp;

class DeleteConfirm implements \ClicShopping\OM\Modules\HooksInterface
{
  public mixed $app;

  /**
   * Initializes the PageManager instance within the application.
   *
   * Checks if the 'PageManager' object exists in the registry. If it does not exist,
   * a new instance of PageManagerApp is created and set in the registry.
   * Finally, the 'PageManager' instance is retrieved from the registry and assigned
   * to the `app` property.
   *
   * @return void
   */
  public function __construct()
  {
    if (!Registry::exists('PageManager')) {
      Registry::set('PageManager', new PageManagerApp());
    }

    $this->app = Registry::get('PageManager');
  }

  /**
   * Deletes a record from the 'pages_manager_description' table based on the provided language ID.
   *
   * @param int $id The ID of the language to delete. If null, the operation will not be performed.
   * @return void
   */
  private function delete(int $id)
  {
    if (!\is_null($id)) {
      $this->app->db->delete('pages_manager_description', ['language_id' => $id]);
    }
  }

  /**
   * Executes the main functionality of the method related to Page Manager.
   *
   * Checks if the application status is defined and enabled. If not, it stops execution.
   * Handles the 'DeleteConfirm' action by retrieving and sanitizing the ID from the request
   * and then calls the delete method with the ID.
   *
   * @return bool Returns false if the application status is not defined or is disabled.
   */
  public function execute()
  {
    if (!\defined('CLICSHOPPING_APP_PAGE_MANAGER_PM_STATUS') || CLICSHOPPING_APP_PAGE_MANAGER_PM_STATUS == 'False') {
      return false;
    }

    if (isset($_GET['DeleteConfirm'])) {
      $id = HTML::sanitize($_GET['lID']);
      $this->delete($id);
    }
  }
}