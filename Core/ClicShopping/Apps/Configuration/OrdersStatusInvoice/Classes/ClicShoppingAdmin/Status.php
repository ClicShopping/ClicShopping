<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\OrdersStatusInvoice\Classes\ClicShoppingAdmin;

use ClicShopping\OM\Registry;

class Status
{
  /**
   * Retrieves the name of the orders status invoice based on the given ID and language ID.
   *
   * @param int $orders_status_invoice_id The ID of the orders status invoice.
   * @param int $language_id The ID of the language. If not provided, the default language ID will be used.
   * @return string The name of the orders status invoice.
   */
  public static function getOrdersStatusInvoiceName(int $orders_status_invoice_id, int $language_id): string
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_Language = Registry::get('Language');

    if (!$language_id) $language_id = $CLICSHOPPING_Language->getId();

    $Qinvoice = $CLICSHOPPING_Db->prepare('select orders_status_invoice_name
                                           from :table_orders_status_invoice
                                           where orders_status_invoice_id = :orders_status_invoice_id
                                           and language_id =:language_id
                                        ');
    $Qinvoice->bindInt(':orders_status_invoice_id', (int)$orders_status_invoice_id);
    $Qinvoice->bindInt(':language_id', (int)$language_id);

    $Qinvoice->execute();

    return $Qinvoice->value('orders_status_invoice_name');
  }

  /**
   * Retrieves a list of order invoice statuses with their IDs and names for the current language.
   *
   * @return array An array of order invoice statuses, where each status is represented as an associative array
   *               with 'id' (int) as the order status invoice ID and 'text' (string) as the order status invoice name.
   */
  public static function getOrdersInvoiceStatus(): array
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_Language = Registry::get('Language');

    $orders_status_invoice_array = [];

    $Qinvoice = $CLICSHOPPING_Db->prepare('select orders_status_invoice_id,
                                                    orders_status_invoice_name
                                             from :table_orders_status_invoice
                                             where language_id = :language_id
                                             order by orders_status_invoice_id
                                            ');
    $Qinvoice->bindInt(':language_id', (int)$CLICSHOPPING_Language->getId());

    $Qinvoice->execute();

    while ($orders_invoice_status = $Qinvoice->fetch()) {
      $orders_status_invoice_array[] = [
        'id' => $orders_invoice_status['orders_status_invoice_id'],
        'text' => $orders_invoice_status['orders_status_invoice_name']
      ];
    }

    return $orders_status_invoice_array;
  }

  /**
   * Retrieves the merchant-written business definition of a invoice status.
   * This text is what the analytics layer reads instead of guessing what the id means.
   *
   * @param int $orders_status_invoice_id The unique identifier.
   * @param int $language_id The language id. If not provided, the default language id is used.
   * @return string The definition corresponding to the given ids.
   */
  public static function getOrdersStatusInvoiceDefinition(int $orders_status_invoice_id, int $language_id): string
  {
    $CLICSHOPPING_Language = Registry::get('Language');
    $CLICSHOPPING_Db = Registry::get('Db');

    if (!$language_id) $language_id = $CLICSHOPPING_Language->getId();

    $Qstatus = $CLICSHOPPING_Db->get('orders_status_invoice', 'orders_status_invoice_definition', ['orders_status_invoice_id' => (int)$orders_status_invoice_id, 'language_id' => $language_id]);

    return $Qstatus->value('orders_status_invoice_definition');
  }

  /**
   * Checks that every language carries a non-empty definition.
   * The column is NOT NULL DEFAULT '', so only this guard makes it truly mandatory.
   *
   * @param array $definitions Posted definitions, keyed by language id.
   * @return bool True when at least one language is missing its definition.
   */
  public static function hasMissingDefinition(array $definitions): bool
  {
    $CLICSHOPPING_Language = Registry::get('Language');

    foreach ($CLICSHOPPING_Language->getLanguages() as $language) {
      if (trim((string)($definitions[$language['id']] ?? '')) === '') {
        return true;
      }
    }

    return false;
  }
}
