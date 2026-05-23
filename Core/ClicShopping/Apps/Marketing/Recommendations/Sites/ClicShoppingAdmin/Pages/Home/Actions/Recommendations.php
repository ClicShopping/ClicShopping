<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Marketing\Recommendations\Sites\ClicShoppingAdmin\Pages\Home\Actions;

use ClicShopping\OM\Registry;

class Recommendations extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {
    $CLICSHOPPING_Recommendations = Registry::get('Recommendations');

    $this->page->setFile('recommendations.php');
    $this->page->data['action'] = 'Recommendations';

    $CLICSHOPPING_Recommendations->loadDefinitions('Sites/ClicShoppingAdmin/Recommendations');
  }
}