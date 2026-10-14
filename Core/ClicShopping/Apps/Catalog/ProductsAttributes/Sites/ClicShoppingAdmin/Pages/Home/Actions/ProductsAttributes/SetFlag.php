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
    if (isset($_GET['flag'], $_GET['products_attributes_id']) && is_numeric($_GET['flag']) && is_numeric($_GET['products_attributes_id'])) {
      $flag = (int)$_GET['flag'];

      if ($flag === 0 || $flag === 1) {
        ProductsAttributesStatusAdmin::setStatus((int)$_GET['products_attributes_id'], $flag);
      }
    }

    if (isset($_GET['products_attributes_id'])) {
      $this->app->redirect('ProductsAttributes&products_attributes_id=' . (int)$_GET['products_attributes_id'] . '#tab3');
    } else {
      $this->app->redirect('ProductsAttributes#tab3');
    }
  }
}