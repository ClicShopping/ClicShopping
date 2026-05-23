<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Tools\AdministratorMenu\Module\Hooks\ClicShoppingAdmin\Langues;

use ClicShopping\Apps\Tools\AdministratorMenu\AdministratorMenu as AdministratorMenuApp;
use ClicShopping\OM\Registry;

use ClicShopping\Apps\Configuration\Langues\Classes\ClicShoppingAdmin\LanguageAdmin;

class Insert implements \ClicShopping\OM\Modules\HooksInterface
{
  public mixed $app;
  private mixed $lang;

  /**
   * Initializes the AdministratorMenu application and sets the language registry.
   *
   * @return void
   */
  public function __construct()
  {
    if (!Registry::exists('AdministratorMenu')) {
      Registry::set('AdministratorMenu', new AdministratorMenuApp());
    }

    $this->app = Registry::get('AdministratorMenu');
    $this->lang = Registry::get('Language');
  }

  /**
   * Inserts administrator menu descriptions for a newly added language
   * by duplicating entries of an existing language.
   *
   * @return void
   */
  private function insert()
  {
    $insert_language_id = LanguageAdmin::getLatestLanguageID();

    $QadministratorMenu = $this->app->db->prepare('select a.id as orig_id,
                                                            amd.*
                                                     from :table_administrator_menu a left join :table_administrator_menu_description amd on a.id = amd.id
                                                     where amd.language_id = :language_id
                                                    ');

    $QadministratorMenu->bindInt(':language_id', (int)$this->lang->getId());
    $QadministratorMenu->execute();

    while ($QadministratorMenu->fetch()) {
      $cols = $QadministratorMenu->toArray();

      $cols['id'] = $cols['orig_id'];
      $cols['language_id'] = (int)$insert_language_id;

      unset($cols['orig_id']);

      $this->app->db->save('administrator_menu_description', $cols);
    }
  }

  /**
   * Executes the main logic of the function based on predefined constants and query parameters.
   *
   * Checks if the application status is defined and active, and exits early if not.
   * If specific query parameters are set, invokes an insert operation.
   *
   * @return bool|void Returns false if the application is inactive, otherwise performs operations based on query parameters.
   */
  public function execute()
  {
    if (!\defined('CLICSHOPPING_APP_ADMINISTRATOR_MENU_AM_STATUS') || CLICSHOPPING_APP_ADMINISTRATOR_MENU_AM_STATUS == 'False') {
      return false;
    }

    if (isset($_GET['Langues'], $_GET['Insert'])) {
      $this->insert();
    }
  }
}