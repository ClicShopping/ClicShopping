<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */


namespace ClicShopping\Apps\Customers\Customers\Sites\ClicShoppingAdmin\Pages\Home\Actions\Customers;

use ClicShopping\Apps\Customers\Customers\Classes\Shared\CustomerDataEraser;
use ClicShopping\OM\Registry;

class DeleteAll extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {
    $CLICSHOPPING_Customers = Registry::get('Customers');
    $CLICSHOPPING_Hooks = Registry::get('Hooks');

    $page = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int)$_GET['page'] : 1;

    if (isset($_POST['selected'], $_GET['DeleteAll']) && \is_array($_POST['selected'])) {
      foreach ($_POST['selected'] as $id) {
        CustomerDataEraser::erase((int)$id, isset($_POST['delete_reviews']) && $_POST['delete_reviews'] == 'on');
      }

      $CLICSHOPPING_Hooks->call('Customers', 'DeleteCustomers');
    }

    $CLICSHOPPING_Customers->redirect('Customers', 'page=' . $page);
  }
}