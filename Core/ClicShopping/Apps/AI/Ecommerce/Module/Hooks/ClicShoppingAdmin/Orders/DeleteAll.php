<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\AI\Ecommerce\Module\Hooks\ClicShoppingAdmin\Orders;

use ClicShopping\OM\Interfaces\HooksInterface;
use ClicShopping\OM\Registry;
use ClicShopping\Apps\AI\Ecommerce\Ecommerce as EcommerceApp;

class DeleteAll implements HooksInterface
{
  public mixed $app;

  /**
   * Class constructor.
   *
   * Initializes the ChatGptApp instance in the Registry if it doesn't already exist,
   * and loads the necessary definitions for the application.
   *
   * @return void
   */
  public function __construct()
  {
    if (!Registry::exists('Ecommerce')) {
      Registry::set('Ecommerce', new EcommerceApp());
    }

    $this->app = Registry::get('Ecommerce');
  }

  /**
   * Processes the execution related to product data management and delete in the database.
   * This includes generating products_embedding, based on product information.
   *
   * @return void
   */
  public function execute()
  {
    if (isset($_POST['selected']) && is_array($_POST['selected']) && isset($_POST['DeleteAll'])) {
      foreach ($_POST['selected'] as $items) {
        if (isset($items)) {
          $this->app->delete('orders_embedding', 'entity_id', $items);
        }
      }
    }
  }
}