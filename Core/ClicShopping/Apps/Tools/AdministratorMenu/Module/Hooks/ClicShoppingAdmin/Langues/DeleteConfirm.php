<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Tools\AdministratorMenu\Module\Hooks\ClicShoppingAdmin\Langues;

use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

use ClicShopping\Apps\Tools\AdministratorMenu\AdministratorMenu as AdministratorMenuApp;

class DeleteConfirm implements \ClicShopping\OM\Modules\HooksInterface
{
  public mixed $app;

  /**
   * Constructor method for initializing the AdministratorMenu application.
   *
   * Checks if 'AdministratorMenu' exists in the Registry. If it does not exist,
   * it creates a new instance of `AdministratorMenuApp` and sets it in the Registry.
   * Assigns the instance from the Registry to the `$app` property.
   *
   * @return void
   */
  public function __construct()
  {
    if (!Registry::exists('AdministratorMenu')) {
      Registry::set('AdministratorMenu', new AdministratorMenuApp());
    }

    $this->app = Registry::get('AdministratorMenu');
  }

  /**
   * Deletes a record from the administrator_menu_description table based on the provided language ID.
   *
   * @param int $id The ID of the language to be deleted.
   * @return void
   */
  private function delete(int $id)
  {
    if (!\is_null($id)) {
      $this->app->db->delete('administrator_menu_description', ['language_id' => $id]);
    }
  }

  /**
   * Executes the deletion process if the 'DeleteConfirm' parameter is present in the request.
   *
   * @return void
   */
  public function execute()
  {
    if (isset($_GET['DeleteConfirm'])) {
      $id = HTML::sanitize($_GET['lID']);
      $this->delete($id);
    }
  }
}