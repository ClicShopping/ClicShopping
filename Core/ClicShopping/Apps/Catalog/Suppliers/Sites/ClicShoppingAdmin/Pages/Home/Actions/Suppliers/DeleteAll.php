<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Catalog\Suppliers\Sites\ClicShoppingAdmin\Pages\Home\Actions\Suppliers;

use ClicShopping\OM\Registry;

class DeleteAll extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public mixed $app;

  public function __construct()
  {
    $this->app = Registry::get('Suppliers');
    $this->Hooks = Registry::get('Hooks');
  }

  public function execute()
  {
    if (isset($_POST['selected'])) {
      foreach ($_POST['selected'] as $id) {
        $Qdelete = $this->app->db->prepare('delete
                                              from :table_suppliers
                                              where suppliers_id = :suppliers_id
                                            ');
        $Qdelete->bindInt(':suppliers_id', $id);
        $Qdelete->execute();

        $Qdelete = $this->app->db->prepare('delete
                                              from :table_suppliers_info
                                              where suppliers_id = :suppliers_id
                                            ');
        $Qdelete->bindInt(':suppliers_id', $id);
        $Qdelete->execute();

        $Qupdate = $this->app->db->prepare('update :table_products
                                              set suppliers_id = :suppliers_id,
                                                  products_status = 0
                                              where suppliers_id = :suppliers_id1
                                            ');
        $Qupdate->bindInt(':suppliers_id', '');
        $Qupdate->bindInt(':suppliers_id1', $id);

        $Qupdate->execute();

        $this->Hooks->call('Suppliers', 'DeleteAll');
      }
    }

    $this->app->redirect('Suppliers');
  }
}