<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\OrdersStatusInvoice\Module\Hooks\ClicShoppingAdmin\Langues;

use ClicShopping\OM\Registry;

use ClicShopping\Apps\Configuration\Langues\Classes\ClicShoppingAdmin\LanguageAdmin;
use ClicShopping\Apps\Configuration\OrdersStatusInvoice\OrdersStatusInvoice as OrdersStatusInvoiceApp;

class Insert implements \ClicShopping\OM\Modules\HooksInterface
{
  public mixed $app;
  private mixed $lang;

  /**
   * Initializes the OrdersStatusInvoice application and sets the language registry.
   *
   * @return void
   */
  public function __construct()
  {
    if (!Registry::exists('OrdersStatusInvoice')) {
      Registry::set('OrdersStatusInvoice', new OrdersStatusInvoiceApp());
    }

    $this->app = Registry::get('OrdersStatusInvoice');
    $this->lang = Registry::get('Language');
  }

  /**
   *
   * Inserts new rows into the `orders_status_invoice` table for the latest language ID based on existing rows for the current language ID in the database.
   *
   * @return void
   */
  private function insert()
  {
    $insert_language_id = LanguageAdmin::getLatestLanguageID();

    $QordersStatusInvoice = $this->app->db->get('orders_status_invoice', '*', ['language_id' => $this->lang->getId()]);

    while ($QordersStatusInvoice->fetch()) {
      $cols = $QordersStatusInvoice->toArray();

      $cols['language_id'] = (int)$insert_language_id;

      $this->app->db->save('orders_status_invoice', $cols);
    }
  }

  /**
   * Executes the main logic to handle the incoming request.
   * Checks if specific GET parameters are set and calls the appropriate method.
   *
   * @return void
   */
  public function execute()
  {
    if (isset($_GET['Langues'], $_GET['Insert'])) {
      $this->insert();
    }
  }
}