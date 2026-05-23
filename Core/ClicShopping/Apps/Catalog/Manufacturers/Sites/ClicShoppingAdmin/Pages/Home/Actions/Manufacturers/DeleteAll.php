<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Catalog\Manufacturers\Sites\ClicShoppingAdmin\Pages\Home\Actions\Manufacturers;

use ClicShopping\OM\Registry;

class DeleteAll extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public mixed $app;

  public function execute()
  {
    $this->app = Registry::get('Manufacturers');
    $this->Hooks = Registry::get('Hooks');

    if (isset($_POST['selected']) && is_array($_POST['selected']) && isset($_GET['DeleteAll'])) {
      foreach ($_POST['selected'] as $id) {
        $this->app->db->delete('manufacturers', ['manufacturers_id' => (int)$id]);
        $this->app->db->delete('manufacturers_info', ['manufacturers_id' => (int)$id]);

        $Qupdate = $this->app->db->prepare('update :table_products
                                              set  manufacturers_id = :manufacturers_id
                                              where manufacturers_id = :manufacturers_id
                                            ');
        $Qupdate->bindInt(':manufacturers_id', $id);

        $Qupdate->execute();

        $this->Hooks->call('Manufacturers', 'DeleteAll');
      }
    }

    $this->app->redirect('Manufacturers');
  }
}