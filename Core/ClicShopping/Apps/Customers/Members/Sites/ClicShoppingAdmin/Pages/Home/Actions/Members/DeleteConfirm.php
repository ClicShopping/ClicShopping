<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */


namespace ClicShopping\Apps\Customers\Members\Sites\ClicShoppingAdmin\Pages\Home\Actions\Members;

use ClicShopping\OM\HTML;
use ClicShopping\Apps\Customers\Customers\Classes\Shared\CustomerDataEraser;
use ClicShopping\OM\Registry;

class DeleteConfirm extends \ClicShopping\OM\Domains\PagesActionsAbstract
{

  public function execute()
  {

    $CLICSHOPPING_Members = Registry::get('Members');

    if (isset($_GET['cID'])) {
      $customers_id = (int)HTML::sanitize($_GET['cID']);


      CustomerDataEraser::erase($customers_id, isset($_POST['delete_reviews']) && $_POST['delete_reviews'] == 'on');
    }

    $CLICSHOPPING_Members->redirect('Members');
  }
}