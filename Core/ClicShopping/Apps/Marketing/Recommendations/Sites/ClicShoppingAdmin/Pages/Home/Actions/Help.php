<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Marketing\Recommendations\Sites\ClicShoppingAdmin\Pages\Home\Actions;

use ClicShopping\OM\Registry;

class Help extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {
    $CLICSHOPPING_Recommendations = Registry::get('Recommendations');

    $this->page->setFile('help.php');
    $this->page->data['action'] = 'Help';

    $CLICSHOPPING_Recommendations->loadDefinitions('Sites/ClicShoppingAdmin/help');
  }
}