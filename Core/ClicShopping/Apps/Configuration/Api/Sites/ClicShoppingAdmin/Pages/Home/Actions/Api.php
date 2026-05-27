<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\Api\Sites\ClicShoppingAdmin\Pages\Home\Actions;

use ClicShopping\OM\Registry;

class Api extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {
    $CLICSHOPPING_Api = Registry::get('Api');

    $this->page->setFile('api.php');
    $this->page->data['action'] = 'Api';

    $CLICSHOPPING_Api->loadDefinitions('Sites/ClicShoppingAdmin/Api');
  }
}