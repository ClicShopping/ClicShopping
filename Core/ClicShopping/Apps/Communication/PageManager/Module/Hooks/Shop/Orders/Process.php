<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Communication\PageManager\Module\Hooks\Shop\Orders;

use ClicShopping\OM\Registry;

class Process implements \ClicShopping\OM\Modules\HooksInterface
{
  public mixed $app;

  /**
   * Inserts page manager general condition data into the 'orders_pages_manager' table.
   *
   * @return void
   */
  private function getPageManagerGeneralConditons()
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_Customer = Registry::get('Customer');
    $CLICSHOPPING_PageManagerShop = Registry::get('PageManagerShop');
    $CLICSHOPPING_Order = Registry::get('Order');

    $page_manager_general_condition = $CLICSHOPPING_PageManagerShop->pageManagerGeneralCondition();

    $sql_data_array = [
      'orders_id' => (int)$CLICSHOPPING_Order->getLastOrderId(),
      'customers_id' => (int)$CLICSHOPPING_Customer->getID(),
      'page_manager_general_condition' => $page_manager_general_condition
    ];

    $CLICSHOPPING_Db->save('orders_pages_manager', $sql_data_array);
  }

  public function execute()
  {
    if (!\defined('CLICSHOPPING_APP_PAGE_MANAGER_PM_STATUS') || CLICSHOPPING_APP_PAGE_MANAGER_PM_STATUS == 'False') {
      return false;
    }

    $this->getPageManagerGeneralConditons();
  }
}