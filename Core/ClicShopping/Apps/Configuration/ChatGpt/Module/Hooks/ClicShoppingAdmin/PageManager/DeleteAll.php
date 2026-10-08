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

use ClicShopping\Apps\Communication\PageManager\PageManager as PageManagerApp;
use ClicShopping\Apps\Configuration\ChatGpt\ChatGpt as ChatGptApp;

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
    // DeleteAll rides the action URL; a locked page is never deleted, so its embedding stays.
    if (isset($_POST['selected']) && is_array($_POST['selected']) && isset($_GET['DeleteAll'])) {
      foreach ($_POST['selected'] as $items) {
        if (isset($items) && !in_array((int)$items, PageManagerApp::LOCKED_PAGES_ID, true)) {
          $this->app->db->delete('pages_manager_embedding', ['entity_id' => (int)$items]);
        }
      }
    }
  }
}