<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Customers\Gdpr\Sites\ClicShoppingAdmin\Pages\Home\Actions;

use ClicShopping\OM\Registry;

class Gdpr extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {
    $CLICSHOPPING_Gdpr = Registry::get('Gdpr');

    $this->page->setFile('gdpr.php');

    $CLICSHOPPING_Gdpr->loadDefinitions('Sites/ClicShoppingAdmin/gdpr');
  }
}