<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Catalog\Products\Sites\ClicShoppingAdmin\Pages\Home\Actions\Products;

use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

class Update extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public mixed $app;
  protected $ID;
  protected $currentCategoryId;

  public function __construct()
  {
    $this->app = Registry::get('Products');

    if (isset($_GET['pID'])) {
      $this->ID = HTML::sanitize($_GET['pID']); // insert
    } elseif (isset($_POST['pID'])) {
      $this->ID = HTML::sanitize($_POST['pID']); // update
    }

    $current_category = HTML::sanitize($_POST['cPath']);

    if (isset($_POST['move_to_category_id'][0])) {
      $move_to_category_id = HTML::sanitize($_POST['move_to_category_id'][0]);
    } else {
      $move_to_category_id = '';
    }

    if ($current_category != $move_to_category_id) {
      $this->currentCategoryId = $move_to_category_id;
    } else {
      $this->currentCategoryId = $current_category;
    }
  }

  public function execute()
  {
    $CLICSHOPPING_Hooks = Registry::get('Hooks');
    $CLICSHOPPING_ProductsAdmin = Registry::get('ProductsAdmin');

    if (isset($_GET['Update'], $_GET['Products'])) {
      $CLICSHOPPING_ProductsAdmin->save($this->ID, 'Update');

      $CLICSHOPPING_Hooks->call('Products', 'Update', ['products_id', $this->ID]);
    }

    $this->app->redirect('Products&cPath=' . $this->currentCategoryId . '&pID=' . $this->ID);
  }
}