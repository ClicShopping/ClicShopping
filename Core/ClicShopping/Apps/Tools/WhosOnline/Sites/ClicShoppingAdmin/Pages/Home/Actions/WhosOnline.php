<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Tools\WhosOnline\Sites\ClicShoppingAdmin\Pages\Home\Actions;

use ClicShopping\OM\Registry;

class WhosOnline extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {
    $CLICSHOPPING_WhosOnline = Registry::get('WhosOnline');

    $this->page->setFile('whos_online.php');

    $CLICSHOPPING_WhosOnline->loadDefinitions('Sites/ClicShoppingAdmin/main');
  }
}