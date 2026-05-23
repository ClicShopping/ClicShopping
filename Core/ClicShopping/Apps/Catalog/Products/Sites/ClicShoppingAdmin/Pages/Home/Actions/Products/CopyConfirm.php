<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Catalog\Products\Sites\ClicShoppingAdmin\Pages\Home\Actions\Products;

use ClicShopping\Apps\Catalog\Products\Classes\ClicShoppingAdmin\ProductsAdmin;
use ClicShopping\OM\Cache;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

class CopyConfirm extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  private mixed $app;
  private $Id;
  private $categoriesId;
  private $currentCategoryId;
  private $copyAs;
  private $productsAdmin;
  private $messageStack;

  public function __construct()
  {
    $this->app = Registry::get('Products');
    $this->messageStack = Registry::get('MessageStack');

    $this->Id = HTML::sanitize($_POST['products_id']);

    $this->currentCategoryId = HTML::sanitize($_POST['current_category_id']);
    $this->copyAs = $_POST['copy_as'];

    if ( isset($_POST['categories_id'])) {
      $this->categoriesId = isset($_POST['categories_id']);
    } else {
      $this->messageStack->add($this->app->getDef('alert_copy_category'), 'warning');
      $this->app->redirect('Products&cPath=' . $this->currentCategoryId . '&pID=' . $this->Id);
    }

    $this->productsAdmin = new ProductsAdmin();
  }

  /**
   * Link products to categories
   * @return void
   */
  private function Link(): void
  {
    if ($this->categoriesId != $this->currentCategoryId) {
      $new_category = $this->categoriesId;

      if (\is_array($new_category) && isset($new_category)) {
        foreach ($new_category as $value_id) {

          $update_array = [
            'products_id' => (int)$this->Id,
            'categories_id' => (int)$value_id
          ];

          $Qcheck = $this->app->db->get('products_to_categories', 'categories_id', $update_array);

          if ($Qcheck->fetch() === false) {
            if ($value_id != $this->currentCategoryId) {
              $count = $this->productsAdmin->getCountProductsToCategory($this->Id, $value_id);

              if ($count < 1) {
                $sql_array = [
                  'products_id' => $this->Id,
                  'categories_id' => $value_id
                ];

                $this->app->db->save('products_to_categories', $sql_array);
              }
            }
          }
        }
      }
    }
  }

  /**
   * Duplicate products in other categories
   * @return void
   */
  private function productsDuplicate(): void
  {
    $new_category = $this->categoriesId;

    if (\is_array($new_category) && isset($new_category)) {
      foreach ($new_category as $value_id) {
        if ($this->copyAs == 'duplicate') {
          $this->productsAdmin->cloneProductsInOtherCategory($this->Id, $value_id);
        }
      }
    }
  }

  /**
   * Link products in other categories
   * @return void
   */
  private function productsLink(): void
  {
    if ($this->copyAs == 'link') {
      if ($this->categoriesId != $this->currentCategoryId) {
        $this->Link();
      } else {
        $this->messageStack->add($this->app->getDef('error_cannot_link_to_same_category'), 'error');
      }
    }
  }

  /**
   * Execute the action
   */
  public function execute()
  {
    $CLICSHOPPING_Hooks = Registry::get('Hooks');

    if (isset($this->Id) && isset($this->categoriesId)) {
      $this->productsDuplicate();
      $this->productsLink();

      Cache::clear('categories');
      Cache::clear('products-also_purchased');
      Cache::clear('products_related');
      Cache::clear('products_cross_sell');
      Cache::clear('upcoming');

      $CLICSHOPPING_Hooks->call('Products', 'CopyConfirm');

      $this->messageStack->add($this->app->getDef('alert_message_b2b_update'), 'warning');

      $this->app->redirect('Products&cPath=' . $this->currentCategoryId . '&pID=' . $this->Id);
    }
  }
}