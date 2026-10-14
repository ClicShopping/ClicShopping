<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Catalog\Categories\Module\Hooks\ClicShoppingAdmin\Langues;

use ClicShopping\Apps\Catalog\Categories\Categories as CategoriesApp;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Interfaces\HooksInterface;
use ClicShopping\OM\Registry;
/**
 * The DeleteConfirm class contains the logic for deleting a record from the categories_description table.
 */
class DeleteConfirm implements HooksInterface
{
  public mixed $app;

  /**
   * Initializes the Categories application by ensuring its existence in the registry
   * and assigning it to the local property.
   *
   * @return void
   */
  public function __construct()
  {
    if (!Registry::exists('Categories')) {
      Registry::set('Categories', new CategoriesApp());
    }

    $this->app = Registry::get('Categories');
  }

  /**
   * Deletes a record from the categories_description table based on the provided language ID.
   *
   * @param int|null $id The language ID of the record to be deleted. If null, no action is taken.
   * @return void
   */
  private function delete(int $id)
  {
    if (!\is_null($id)) {
      $this->app->db->delete('categories_description', ['language_id' => $id]);
    }
  }

  /**
   * Executes the necessary operations based on the configuration and input variables.
   *
   * @return bool Returns false if the application status is not defined or disabled.
   */
  public function execute()
  {

    if (!\defined('CLICSHOPPING_APP_CATEGORIES_CT_STATUS') || CLICSHOPPING_APP_CATEGORIES_CT_STATUS == 'False') {
      return false;
    }

    if (isset($_GET['DeleteConfirm'])) {
      $id = HTML::sanitize($_GET['lID']);
      $this->delete($id);
    }
  }
}