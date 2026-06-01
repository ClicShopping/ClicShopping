<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Catalog\Categories\Module\Hooks\ClicShoppingAdmin\Products;

use ClicShopping\Apps\Catalog\Categories\Categories as CategoriesApp;
use ClicShopping\Apps\Catalog\Products\Classes\ClicShoppingAdmin\ProductsAdmin;
use ClicShopping\Apps\Catalog\Categories\Classes\ClicShoppingAdmin\CategoriesAdmin;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;
/**
 * Class responsible  for managing and displaying statistics related to categories within the application.
 */
class Update implements \ClicShopping\OM\Modules\HooksInterface
{
  public mixed $app;
  protected $productsAdmin;
  protected string $productsLink;
  protected string $currentCategoryId;

  /**
   * Constructor method for initializing the Categories class.
   *
   * This method checks if the 'Categories' instance exists in the Registry.
   * If not, it registers a new instance of `CategoriesApp`.
   * It initializes the application-specific object `$this->app` with the 'Categories' instance.
   * It also sanitizes and sets the current category path and the copy action for the products
   * (defaulting to 'none' if no 'copy_as' is provided in the POST data).
   * Additionally, it initializes a new instance of `ProductsAdmin` for product management tasks.
   *
   * @return void
   */
  public function __construct()
  {
    if (!Registry::exists('Categories')) {
      Registry::set('Categories', new CategoriesApp());
    }

    $this->app = Registry::get('Categories');

    if (isset($_POST['copy_as'])) {
      $this->productsLink = HTML::sanitize($_POST['copy_as']);
    } else {
      $this->productsLink = 'none';
    }

    $this->currentCategoryId = HTML::sanitize($_POST['cPath']);

    $this->productsAdmin = new ProductsAdmin();
  }

  /**
   * Moves a product to a new category, updating the category association in the database.
   *
   * @param int $move_new_category The ID of the new category to move the product to.
   * @param int $id The ID of the product to be moved.
   * @return void
   */
  public function moveCategory(int $move_new_category, int $id): void
  {
    $QCheck = $this->app->db->prepare('select count(*)
                                          from :table_products_to_categories
                                          where products_id = :products_id
                                          and categories_id not in ( :categories_id )
                                        ');
    $QCheck->bindInt(':products_id', $id);
    $QCheck->bindInt(':categories_id', $move_new_category);
    $QCheck->execute();

    if ($QCheck->rowCount() > 0) {
      $Qupdate = $this->app->db->prepare('update :table_products_to_categories
                                            set categories_id = :categories_id
                                            where products_id = :products_id
                                            and categories_id = :categories_id1
                                          ');
      $Qupdate->bindInt(':categories_id', $move_new_category);
      $Qupdate->bindInt(':products_id', $id);
      $Qupdate->bindInt(':categories_id1', $this->currentCategoryId);

      $Qupdate->execute();
    }
  }

  /**
   * Updates the categories associated with a specific product.
   *
   * This method handles actions such as moving a product to a new category,
   * linking a product to multiple categories, and duplicating a product in
   * other categories. It also clears the associated cache for categories and products.
   *
   * @param int|null $id The ID of the product to update. If null, no product is specified.
   * @return void
   */
  public function updateProductCategories( int|null $id = null): void
  {
    $CLICSHOPPING_MessageStack = Registry::get('MessageStack');

    if (isset($_GET['Update'])) {
      if (isset($_POST['move_to_category_id'])) {
        $new_category = HTML::sanitize($_POST['move_to_category_id']);
      } else {
        $new_category = null;
      }

      if (empty($this->productsLink) || $this->productsLink == 'move') {
        $move_new_category = $new_category[0];

        $this->moveCategory($move_new_category, $id);
      } elseif ($this->productsLink != 'move') {
//link the category
        if (\is_array($new_category) && isset($new_category)) {
          foreach ($new_category as $value_id) {
            $insert_sql = [
              'products_id' => (int)$id,
              'categories_id' => (int)$value_id
            ];

            $Qcheck = $this->app->db->get('products_to_categories', 'categories_id', $insert_sql);

            if ($Qcheck->fetch() === false) {
//if the product does not exist inside the category
              if ($value_id != $this->currentCategoryId) {
                $count = $this->productsAdmin->getCountProductsToCategory($id, $value_id);

                if ($count < 1) {
// just link the product another category
                  if ($this->productsLink == 'link') {
                    if ($this->currentCategoryId != $value_id) {
                      $sql_array = [
                        'products_id' => (int)$id,
                        'categories_id' => (int)$value_id
                      ];

                      $this->app->db->save('products_to_categories', $sql_array);
                    } else {
                      $CLICSHOPPING_MessageStack->add($this->app->getDef('error_cannot_link_to_same_category'), 'error');
                    }
                  }
                }
              }

              if ($this->productsLink == 'duplicate') {
                $this->productsAdmin->cloneProductsInOtherCategory($id, $value_id);
              }
            }
          }
        }
      }
    }

    CategoriesAdmin::clearCategoryCaches();
  }

  /**
   * Executes the primary logic for managing category updates.
   *
   * This method checks if the application is enabled and processes category updates for a product
   * if a valid product ID is provided in the request.
   *
   * @return bool Returns false if the application is disabled, otherwise does not return a value.
   */
  public function execute()
  {
    if (!\defined('CLICSHOPPING_APP_CATEGORIES_CT_STATUS') || CLICSHOPPING_APP_CATEGORIES_CT_STATUS == 'False') {
      return false;
    }

    if (isset($_GET['pID'])) {
      $id = HTML::sanitize($_GET['pID']);

      $this->updateProductCategories($id);
    }
  }
}
