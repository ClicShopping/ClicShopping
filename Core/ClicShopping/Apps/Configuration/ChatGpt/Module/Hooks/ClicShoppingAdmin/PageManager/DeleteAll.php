<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\ChatGpt\Module\Hooks\ClicShoppingAdmin\PageManager;

use ClicShopping\OM\Interfaces\HooksInterface;
use ClicShopping\OM\Registry;
use ClicShopping\OM\HTML;

use ClicShopping\Apps\Configuration\ChatGpt\ChatGpt as ChatGptApp;
use ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\Gpt;

use ClicShopping\Apps\Catalog\Manufacturers\Classes\ClicShoppingAdmin\ManufacturerAdmin;
use ClicShopping\Apps\Configuration\Api\Sites\Shop\Pages\Manufacturers\Manufacturers;
use ClicShopping\AI\DomainsAI\Shared\Embedding\NewVector;

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
    if (!Registry::exists('ChatGpt')) {
      Registry::set('ChatGpt', new ChatGptApp());
    }

    $this->app = Registry::get('ChatGpt');

    $this->app->loadDefinitions('Module/Hooks/ClicShoppingAdmin/Products/seo_chat_gpt');
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
          $this->app->delete('page_manager_embedding', 'entity_id', $items);
        }
      }
    }
  }
}