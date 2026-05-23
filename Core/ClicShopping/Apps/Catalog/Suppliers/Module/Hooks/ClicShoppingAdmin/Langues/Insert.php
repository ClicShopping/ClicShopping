<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Catalog\Suppliers\Module\Hooks\ClicShoppingAdmin\Langues;

use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

use ClicShopping\Apps\Catalog\Suppliers\Suppliers as SuppliersApp;

class Insert implements \ClicShopping\OM\Modules\HooksInterface
{
  public mixed $app;
  protected $insert_language_id;

  /**
   * Constructor method for initializing the Suppliers application.
   *
   * Checks if the Suppliers instance exists in the Registry. If it does not exist,
   * it creates a new SuppliersApp instance and registers it. Also, retrieves
   * the Suppliers application and Language objects from the Registry and assigns
   * them to the respective class properties.
   *
   * @return void
   */
  public function __construct()
  {
    if (!Registry::exists('Suppliers')) {
      Registry::set('Suppliers', new SuppliersApp());
    }

    $this->app = Registry::get('Suppliers');
    $this->lang = Registry::get('Language');
  }

  /**
   * Inserts supplier language data into the database based on the provided conditions.
   *
   * @return void
   */
  private function insert()
  {
    if (isset($this->insert_language_id, $_GET['Langues'], $_GET['Insert'])) {
      $this->insert_language_id = HTML::sanitize($_POST['insert_id']);

      $Qsuppliers = $this->app->db->prepare('select m.suppliers_id as orig_suppliers_id,
                                                      mi.*
                                              from :table_suppliers m left join :table_suppliers_info mi on m.suppliers_id = mi.suppliers_id
                                              where mi.languages_id = :languages_id
                                             ');

      $Qsuppliers->bindInt(':languages_id', $this->lang->getId());
      $Qsuppliers->execute();

      while ($Qsuppliers->fetch()) {
        $cols = $Qsuppliers->toArray();

        $cols['suppliers_id'] = $cols['orig_suppliers_id'];
        $cols['languages_id'] = $this->insert_language_id;

        unset($cols['orig_suppliers_id']);

        $this->app->db->save('suppliers_info', $cols);
      }
    }
  }

  /**
   * Executes the main logic of the method.
   *
   * Checks the application status and verifies if a specific GET parameter is set
   * to trigger corresponding actions.
   *
   * @return bool Returns false if the application status is disabled; otherwise, void.
   */
  public function execute()
  {
    if (!\defined('CLICSHOPPING_APP_SUPPLIERS_CS_STATUS') || CLICSHOPPING_APP_SUPPLIERS_CS_STATUS == 'False') {
      return false;
    }

    if (isset($_GET['Insert'])) {
      $this->insert();
    }
  }
}