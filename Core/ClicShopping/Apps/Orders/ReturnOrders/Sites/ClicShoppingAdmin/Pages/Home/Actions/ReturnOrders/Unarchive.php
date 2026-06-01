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

class Unarchive extends \ClicShopping\OM\Domains\PagesActionsAbstract
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

    $Qupdate = $this->app->db->prepare('update :table_return_orders
                                        set archive = 0
                                        where return_id = :return_id
                                      ');

    $Qupdate->bindInt(':return_id', $this->rID);
    $Qupdate->execute();

    $this->app->redirect('ReturnOrders');
  }
}