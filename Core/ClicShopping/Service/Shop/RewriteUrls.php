<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Service\Shop;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;
use ClicShopping\Sites\Shop\RewriteUrl as RewriteUrlClass;

/**
 * The RewriteUrls service class is responsible for initializing URL rewriting functionality in the shop system.
 * It ensures that the required RewriteUrl class is loaded and registered with the Registry for use throughout the application.
 * It also integrates the service to execute specific logic before the page content is generated.
 */
class RewriteUrls implements \ClicShopping\OM\Interfaces\ServiceInterface
{
  /**
   * Initiates the process by checking if the required RewriteUrl file exists and configures the necessary dependencies.
   *
   * @return bool Returns true if the RewriteUrl file exists and initialization is successful; otherwise, false.
   */
  public static function start(): bool
  {
    if (is_file(CLICSHOPPING::BASE_DIR . 'Sites/Shop/RewriteUrl.php')) {
      $CLICSHOPPING_Service = Registry::get('Service');

      if (!Registry::exists('RewriteUrl')) {
        Registry::set('RewriteUrl', new RewriteUrlClass());
      }

      $CLICSHOPPING_Service->addCallBeforePageContent('Address', 'initialize');

      return true;
    } else {
      return false;
    }
  }

  /**
   * Stops the currently running service or process.
   *
   * @return bool Returns true on successful termination.
   */
  public static function stop(): bool
  {
    return true;
  }
}
