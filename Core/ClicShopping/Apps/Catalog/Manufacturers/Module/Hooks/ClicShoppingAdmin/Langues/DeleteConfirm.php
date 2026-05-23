<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Catalog\Manufacturers\Module\Hooks\ClicShoppingAdmin\Langues;

use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

use ClicShopping\Apps\Catalog\Manufacturers\Manufacturers as ManufacturersApp;

class DeleteConfirm implements \ClicShopping\OM\Modules\HooksInterface
{
  public mixed $app;

  /**
   * Constructor method for initializing the Manufacturers application.
   *
   * It checks if the Manufacturers key exists in the Registry. If not,
   * it creates a new instance of the ManufacturersApp class and registers
   * it in the Registry. Finally, it retrieves the Manufacturers instance
   * from the Registry and assigns it to the $app property.
   *
   * @return void
   */
  public function __construct()
  {
    if (!Registry::exists('Manufacturers')) {
      Registry::set('Manufacturers', new ManufacturersApp());
    }

    $this->app = Registry::get('Manufacturers');
  }

  /**
   * Deletes a record from the manufacturers_info table based on the provided language ID.
   *
   * @param int|null $id The language ID of the record to delete. If null, no action is taken.
   * @return void
   */
  private function delete(int $id)
  {
    if (!\is_null($id)) {
      $this->app->db->delete('manufacturers_info', ['languages_id' => $id]);
    }
  }

  /**
   * Executes the method's main functionality, including performing a condition check
   * on the application's status and handling a delete confirmation if applicable.
   *
   * @return bool Returns false if the application's status is not defined
   *              or is set to 'False', otherwise performs the delete operation.
   */
  public function execute()
  {
    if (!\defined('CLICSHOPPING_APP_MANUFACTURERS_CM_STATUS') || CLICSHOPPING_APP_MANUFACTURERS_CM_STATUS == 'False') {
      return false;
    }

    if (isset($_GET['DeleteConfirm'])) {
      $id = HTML::sanitize($_GET['lID']);
      $this->delete($id);
    }
  }
}