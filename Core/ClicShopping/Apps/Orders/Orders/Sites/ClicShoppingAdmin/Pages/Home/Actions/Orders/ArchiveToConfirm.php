<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Orders\Orders\Sites\ClicShoppingAdmin\Pages\Home\Actions\Orders;

use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

class ArchiveToConfirm extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public mixed $app;
  protected $oID;

  public function __construct()
  {
    $this->app = Registry::get('Orders');
    $this->oID = HTML::sanitize($_GET['oID']);
  }

  public function execute()
  {
    $CLICSHOPPING_MessageStack = Registry::get('MessageStack');

    if ($this->oID != 0) {
      $Qupdate = $this->app->db->prepare('update :table_orders
                                            set orders_archive = 1
                                            where orders_id = :orders_id
                                          ');

      $Qupdate->bindInt(':orders_id', $this->oID);
      $Qupdate->execute();
    } else {
      $CLICSHOPPING_MessageStack->add($this->app->getDef('text_orders_not_archived'), 'main');
    }

    $this->app->redirect('Orders');
  }
}