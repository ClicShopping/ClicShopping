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

    // Le formulaire poste categories_id[] : HTML::sanitize rend '' sur un tableau, et les deux
    // branches ci-dessous testent is_array(). Un identifiant de categorie est un entier.
    $this->categoriesId = array_values(array_filter(array_map('intval', (array)($_POST['categories_id'] ?? []))));

    if ($this->categoriesId === []) {
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
    foreach ($this->categoriesId as $value_id) {
      if ($value_id == $this->currentCategoryId) {
        continue;
      }

      $update_array = [
        'products_id' => (int)$this->Id,
        'categories_id' => $value_id
      ];

      $Qcheck = $this->app->db->get('products_to_categories', 'categories_id', $update_array);

      if ($Qcheck->fetch() !== false) {
        continue;
      }

      if ($this->productsAdmin->getCountProductsToCategory((int)$this->Id, $value_id) < 1) {
        $this->app->db->save('products_to_categories', $update_array);
      }
    }
  }

  /**
   * Duplicate products in other categories
   * @return void
   */
  private function productsDuplicate(): void
  {
    if ($this->copyAs !== 'duplicate') {
      return;
    }

    foreach ($this->categoriesId as $value_id) {
      $this->productsAdmin->cloneProductsInOtherCategory((int)$this->Id, $value_id);
    }
  }

  /**
   * Link products in other categories
   * @return void
   */
  private function productsLink(): void
  {
    if ($this->copyAs !== 'link') {
      return;
    }

    // Une seule categorie choisie, et c'est celle d'origine : il n'y a rien a lier, et le dire
    // vaut mieux qu'un ecran qui revient inchange.
    if ($this->categoriesId === [(int)$this->currentCategoryId]) {
      $this->messageStack->add($this->app->getDef('error_cannot_link_to_same_category'), 'error');
      return;
    }

    $this->Link();
  }

  /**
   * Execute the action
   */
  public function execute()
  {
    $CLICSHOPPING_Hooks = Registry::get('Hooks');

    if (isset($this->Id) && $this->categoriesId !== []) {
      $this->productsDuplicate();
      $this->productsLink();

      Cache::clear('categories');

      $CLICSHOPPING_Hooks->call('Products', 'CopyConfirm');

      $this->messageStack->add($this->app->getDef('alert_message_b2b_update'), 'warning');

      $this->app->redirect('Products&cPath=' . $this->currentCategoryId . '&pID=' . $this->Id);
    }
  }
}