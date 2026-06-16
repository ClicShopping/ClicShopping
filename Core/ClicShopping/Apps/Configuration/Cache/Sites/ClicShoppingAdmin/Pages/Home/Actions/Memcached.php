<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\Cache\Sites\ClicShoppingAdmin\Pages\Home\Actions;

use ClicShopping\OM\Registry;

class Memcached extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {
    $CLICSHOPPING_Cache = Registry::get('Cache');

    $this->page->setFile('memcached.php');
    $this->page->data['action'] = 'Cache';

    $CLICSHOPPING_Cache->loadDefinitions('Sites/ClicShoppingAdmin/memcached');
  }
}