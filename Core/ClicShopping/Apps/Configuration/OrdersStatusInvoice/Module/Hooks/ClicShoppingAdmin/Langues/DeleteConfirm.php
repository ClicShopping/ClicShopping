<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\OrdersStatusInvoice\Module\Hooks\ClicShoppingAdmin\Langues;

use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

use ClicShopping\Apps\Configuration\OrdersStatusInvoice\OrdersStatusInvoice as OrdersStatusInvoiceApp;

class DeleteConfirm implements \ClicShopping\OM\Modules\HooksInterface
{
  public mixed $app;

  /**
   * Constructor method to initialize the OrdersStatusInvoice application.
   *
   * Checks if the 'OrdersStatusInvoice' object exists in the Registry.
   * If it does not exist, it creates and sets a new OrdersStatusInvoiceApp instance in the Registry.
   * Then, retrieves the instance from the Registry and assigns it to the $app property.
   *
   * @return void
   */
  public function __construct()
  {
    if (!Registry::exists('OrdersStatusInvoice')) {
      Registry::set('OrdersStatusInvoice', new OrdersStatusInvoiceApp());
    }

    $this->app = Registry::get('OrdersStatusInvoice');
  }

  /**
   * Deletes a record from the 'orders_status_invoice' table based on the provided language ID.
   *
   * @param int $id The ID of the language to be deleted.
   * @return void
   */
  private function delete(int $id)
  {
    if (!\is_null($id)) {
      $this->app->db->delete('orders_status_invoice', ['language_id' => $id]);
    }
  }

  /**
   * Executes the delete operation if a delete confirmation is provided in the request.
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