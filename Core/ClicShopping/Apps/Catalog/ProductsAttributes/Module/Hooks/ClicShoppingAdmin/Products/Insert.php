<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Catalog\ProductsAttributes\Module\Hooks\ClicShoppingAdmin\Products;

use ClicShopping\Apps\Catalog\ProductsAttributes\Classes\ClicShoppingAdmin\ProductsAttributesInlineAdmin;
use ClicShopping\Apps\Catalog\ProductsAttributes\ProductsAttributes as ProductsAttributesApp;
use ClicShopping\OM\Modules\HooksInterface;
use ClicShopping\OM\Registry;

/**
 * Persist the inline attributes tab when a brand-new product is created.
 * The host action calls ProductsAdmin::save(null, 'Insert') just before
 * firing this hook. PDO::lastInsertId() is unreliable here because the
 * save() helper performs subsequent inserts (gallery images, etc.) that
 * shift the cursor, so we look up the newest products_id directly — same
 * pattern used by QuantityDiscount/Save and other companion hooks.
 */
class Insert implements HooksInterface
{
  public mixed $app;

  public function __construct()
  {
    if (!Registry::exists('ProductsAttributes')) {
      Registry::set('ProductsAttributes', new ProductsAttributesApp());
    }

    $this->app = Registry::get('ProductsAttributes');
  }

  /**
   * @return bool false when the app is disabled or the new id cannot be resolved.
   */
  public function execute(): bool
  {
    if (!\defined('CLICSHOPPING_APP_PRODUCTS_ATTRIBUTES_PA_STATUS') || CLICSHOPPING_APP_PRODUCTS_ATTRIBUTES_PA_STATUS === 'False') {
      return false;
    }

    $Qproducts = $this->app->db->prepare('select products_id
                                            from :table_products
                                            order by products_id desc
                                            limit 1
                                         ');
    $Qproducts->execute();
    $products_id = (int)$Qproducts->valueInt('products_id');

    if ($products_id <= 0) {
      return false;
    }

    $admin = new ProductsAttributesInlineAdmin();
    $admin->savePostedRows($products_id);

    return true;
  }
}
