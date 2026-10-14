<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Service\Shop;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;
use ClicShopping\Sites\Shop\Breadcrumb as BreadcrumbClass;

/**
 * Service class responsible for handling the initialization and management
 * of the Breadcrumb functionality within the shop.
 *
 * This class implements the \ClicShopping\OM\ServiceInterface interface.
 */
class Breadcrumb implements \ClicShopping\OM\Interfaces\ServiceInterface
{
  /**
   * Initializes the breadcrumb system by verifying the required file exists
   * and setting up the breadcrumb object in the registry. Adds predefined
   * breadcrumb entries for navigation.
   *
   * @return bool Returns true if the BreadcrumbClass is successfully initialized
   * and added to the registry; otherwise, returns false.
   */
  public static function start(): bool
  {
    if (is_file(CLICSHOPPING::BASE_DIR . 'Sites/Shop/Breadcrumb.php')) {
      Registry::set('Breadcrumb', new BreadcrumbClass());
      $CLICSHOPPING_Breadcrumb = Registry::get('Breadcrumb');

      $CLICSHOPPING_Breadcrumb->add(CLICSHOPPING::getDef('header_title_top'), CLICSHOPPING::getConfig('http_server', 'Shop'));
      $CLICSHOPPING_Breadcrumb->add(CLICSHOPPING::getDef('header_title_catalog', ['store_name' => HTML::sanitize(STORE_NAME)]), CLICSHOPPING::link());

      return true;
    } else {
      return false;
    }
  }

  /**
   * Halts the current process or operation.
   *
   * @return bool Returns true when the method is successfully executed.
   */
  public static function stop(): bool
  {
    return true;
  }
}
