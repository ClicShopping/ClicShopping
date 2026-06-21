<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\OrdersStatus\Module\Hooks\ClicShoppingAdmin\Langues;

use ClicShopping\Apps\Configuration\OrdersStatus\OrdersStatus as OrdersStatusApp;
use ClicShopping\OM\Interfaces\HooksInterface;
use ClicShopping\OM\Registry;

use ClicShopping\Apps\Configuration\Langues\Classes\ClicShoppingAdmin\LanguageAdmin;

class Insert implements HooksInterface
{
  public mixed $app;
  private mixed $lang;

  /**
   * Initializes the OrdersStatus application by ensuring it is registered in the Registry.
   * Sets the app and language properties from the Registry.
   *
   * @return void
   */
  public function __construct()
  {
    if (!Registry::exists('OrdersStatus')) {
      Registry::set('OrdersStatus', new OrdersStatusApp());
    }

    $this->app = Registry::get('OrdersStatus');
    $this->lang = Registry::get('Language');
  }

  /**
   * Inserts records into the 'orders_status' table for a newly added language.
   * Duplicates the existing records of the current language, modifies the language ID,
   * and saves them with the new language ID.
   *
   * @return void
   */
  private function insert()
  {
    $insert_language_id = LanguageAdmin::getLatestLanguageID();

    $QordersStatus = $this->app->db->get('orders_status', '*', ['language_id' => $this->lang->getId()]);

    while ($QordersStatus->fetch()) {
      $cols = $QordersStatus->toArray();

      $cols['language_id'] = (int)$insert_language_id;

      $this->app->db->save('orders_status', $cols);
    }
  }

  /**
   * Executes the main functionality of the method.
   *
   * Checks if the application status is defined and enabled, and performs specific actions if certain GET parameters are set.
   *
   * @return bool Returns false if the application status is not defined or disabled. Otherwise, performs actions based on the request parameters.
   */
  public function execute()
  {
    if (!\defined('CLICSHOPPING_APP_ORDERS_STATUS_OU_STATUS') || CLICSHOPPING_APP_ORDERS_STATUS_OU_STATUS == 'False') {
      return false;
    }

    if (isset($_GET['Langues'], $_GET['Insert'])) {
      $this->insert();
    }
  }
}