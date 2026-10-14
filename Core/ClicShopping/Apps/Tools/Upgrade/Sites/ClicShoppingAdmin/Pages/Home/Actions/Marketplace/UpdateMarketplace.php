<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Tools\Upgrade\Sites\ClicShoppingAdmin\Pages\Home\Actions\Marketplace;

use ClicShopping\OM\Registry;

class UpdateMarketplace extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public mixed $app;

  public function __construct()
  {
    $this->app = Registry::get('Upgrade');
  }

  public function execute()
  {
    $CLICSHOPPING_MessageStack = Registry::get('MessageStack');

    if (isset($_GET['UpdateMarketplace'], $_GET['Marketplace'])) {
      $this->app->db->delete('marketplace_categories');
      $this->app->db->delete('marketplace_files');
      $this->app->db->delete('marketplace_file_informations');

      $CLICSHOPPING_MessageStack->add($this->app->getDef('Marketplace'), 'success');
    }

    $this->app->redirect('Marketplace');
  }
}