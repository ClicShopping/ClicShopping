<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Orders\ReturnOrders\Sites\ClicShoppingAdmin\Pages\Home\Actions\ReturnOrders;

use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

class Archive extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public mixed $app;
  protected int $rID;

  public function __construct()
  {
    $this->app = Registry::get('ReturnOrders');
    $this->rID = HTML::sanitize($_GET['rID']);
  }

  public function execute()
  {
    $CLICSHOPPING_MessageStack = Registry::get('MessageStack');

    if ($this->rID != 0) {
      $Qupdate = $this->app->db->prepare('update :table_return_orders
                                            set archive = 1
                                            where return_id = :return_id
                                          ');

      $Qupdate->bindInt(':return_id', $this->rID);
      $Qupdate->execute();
    } else {
      $CLICSHOPPING_MessageStack->add($this->app->getDef('warning_order_not_updated'), 'warning');
    }

    $this->app->redirect('ReturnOrders');
  }
}