<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Catalog\ProductsAttributes\Module\Hooks\ClicShoppingAdmin\Products;

use ClicShopping\Apps\Catalog\ProductsAttributes\Classes\ClicShoppingAdmin\ProductsAttributesInlineAdmin;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Modules\HooksInterface;

/**
 * Persist the inline attributes tab when an existing product is updated.
 * The host action (Products/Update) already validated the products_id,
 * which we resolve here from the same request scope.
 */
class Update implements HooksInterface
{
  /**
   * @return bool false when the app is disabled, true once rows are persisted.
   */
  public function execute(): bool
  {
    if (!\defined('CLICSHOPPING_APP_PRODUCTS_ATTRIBUTES_PA_STATUS') || CLICSHOPPING_APP_PRODUCTS_ATTRIBUTES_PA_STATUS === 'False') {
      return false;
    }

    $products_id = 0;

    if (isset($_GET['pID'])) {
      $products_id = (int)HTML::sanitize($_GET['pID']);
    } elseif (isset($_POST['pID'])) {
      $products_id = (int)HTML::sanitize($_POST['pID']);
    }

    if ($products_id <= 0) {
      return false;
    }

    $admin = new ProductsAttributesInlineAdmin();
    $admin->savePostedRows($products_id);

    return true;
  }
}
