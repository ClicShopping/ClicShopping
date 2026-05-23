<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */


namespace ClicShopping\Apps\Catalog\ProductsAttributes\Sites\ClicShoppingAdmin\Pages\Home\Actions\ProductsAttributes;

use ClicShopping\Apps\Catalog\ProductsAttributes\Classes\ClicShoppingAdmin\ProductsAttributesStatusAdmin;
use ClicShopping\OM\Registry;

class SetFlag extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public mixed $app;

  public function __construct()
  {
    $this->app = Registry::get('ProductsAttributes');
  }

  public function execute()
  {
    if (($_GET['flag'] == 0) || ($_GET['flag'] == 1)) {
      if (isset($_GET['products_attributes_id'])) {
        ProductsAttributesStatusAdmin::getStatus($_GET['products_attributes_id'], $_GET['flag']);
      }
    }

    if (isset($_GET['products_attributes_id'])) {
      $this->app->redirect('ProductsAttributes&products_attributes_id=' . $_GET['products_attributes_id'] . '#tab3');
    } else {
      $this->app->redirect('ProductsAttributes#tab3');
    }
  }
}