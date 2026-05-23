<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */


namespace ClicShopping\Apps\Orders\Orders\Sites\ClicShoppingAdmin\Pages\Home\Actions\Orders;

use ClicShopping\Apps\Orders\Orders\Classes\ClicShoppingAdmin\OrderAdmin;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

class DeleteConfirm extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public mixed $app;
  protected bool $restock;
  protected int $oID;

  public function __construct()
  {
    $this->app = Registry::get('Orders');
    $this->oID = HTML::sanitize($_GET['oID']);
  }

  public function execute()
  {
    $CLICSHOPPING_MessageStack = Registry::get('MessageStack');
    $CLICSHOPPING_Hooks = Registry::get('Hooks');
    if (isset($_GET['DeleteConfirm'], $_GET['oID'])) {
      if ($this->oID != 0) {
        if (isset($_POST['restock'])) {
          $restock = true;
        } else {
          $restock = false;
        }

        OrderAdmin::removeOrder($this->oID, $restock);
      } else {
        $CLICSHOPPING_MessageStack->add($this->app->getDef('warning_order_not_updated'), 'warning');
      }

      $CLICSHOPPING_Hooks->call('Orders', 'DeleteConfirm');
    }

    $this->app->redirect('Orders');
  }
}