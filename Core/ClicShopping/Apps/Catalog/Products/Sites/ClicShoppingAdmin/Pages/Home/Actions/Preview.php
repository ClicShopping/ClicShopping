<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Catalog\Products\Sites\ClicShoppingAdmin\Pages\Home\Actions;

use ClicShopping\OM\Registry;

/**
 * Class Preview
 *
 * This action class handles the preview functionality in the admin interface.
 * It sets up the preview page and loads necessary definitions.
 */
class Preview extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  /**
   * Execute the preview action.
   *
   * This method sets the page file to 'preview.php', assigns the action name,
   * and loads language definitions for the Products app.
   */
  public function execute()
  {
    $CLICSHOPPING_Products = Registry::get('Products');

    $this->page->setFile('preview.php');
    $this->page->data['action'] = 'Preview';

    $CLICSHOPPING_Products->loadDefinitions('Sites/ClicShoppingAdmin/main');
  }
}