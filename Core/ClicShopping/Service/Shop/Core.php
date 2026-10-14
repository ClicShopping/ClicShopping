<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Service\Shop;

use ClicShopping\Apps\Catalog\Products\Classes\Shop\Prod as ProdClass;
use ClicShopping\Apps\Catalog\Products\Classes\Shop\ProductsCommon as ProductsCommonClass;
use ClicShopping\Apps\Catalog\Products\Classes\Shop\ProductsFunctionTemplate as ProductsFunctionTemplateClass;
use ClicShopping\Apps\Catalog\ProductsAttributes\Classes\Shop\ProductsAttributesShop;
use ClicShopping\Apps\Customers\Customers\Classes\Shop\CustomerShop as CustomerClass;
use ClicShopping\OM\Registry;
use ClicShopping\Sites\Shop\NavigationHistory as NavigationHistoryClass;
use ClicShopping\Sites\Shop\OrderTotal as OrderTotalClass;
use ClicShopping\Sites\Shop\ShoppingCart as ShoppingCartClass;
use ClicShopping\Sites\Shop\Tax as TaxClass;

/**
 * Core Service Class
 *
 * This class implements the ServiceInterface and provides the necessary
 * initialization and shutdown operations for the core services in the shop.
 */
class Core implements \ClicShopping\OM\Interfaces\ServiceInterface
{
  /**
   * Initializes the required registry entries and session objects.
   *
   * This method sets up various essential components in the registry that
   * are required for the application's operation. Additionally, it ensures
   * the shopping cart session is properly initialized. If necessary, it
   * creates and assigns a new shopping cart object to the registry.
   *
   * @return bool Returns true upon successful initialization of all components.
   */
  public static function start(): bool
  {
    Registry::set('Customer', new CustomerClass());
    Registry::set('Tax', new TaxClass());
    Registry::set('Prod', new ProdClass());
    Registry::set('ProductsCommon', new ProductsCommonClass());
    Registry::set('ProductsFunctionTemplate', new ProductsFunctionTemplateClass());
    Registry::set('OrderTotal', new OrderTotalClass());
    Registry::set('ProductsAttributes', new ProductsAttributesShop());

    if (!isset($_SESSION['cart']) || !\is_object($_SESSION['cart']) || (\get_class($_SESSION['cart']) !== 'shoppingCart')) {
      Registry::set('ShoppingCart', new ShoppingCartClass());
    }

    Registry::set('NavigationHistory', new NavigationHistoryClass(true));

    return true;
  }

  /**
   * Stops the execution or process and returns its success status.
   *
   * @return bool Returns true if the stop operation was successful, false otherwise.
   */
  public static function stop(): bool
  {
    return true;
  }
}
