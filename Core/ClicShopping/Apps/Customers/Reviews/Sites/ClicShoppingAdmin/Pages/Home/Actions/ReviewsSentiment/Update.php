<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Customers\Reviews\Sites\ClicShoppingAdmin\Pages\Home\Actions\ReviewsSentiment;

use ClicShopping\OM\Registry;

class Update extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
   public function execute()
  {
    $CLICSHOPPING_Reviews = Registry::get('Reviews');
    $CLICSHOPPING_MessageStack = Registry::get('MessageStack');
    $CLICSHOPPING_Hooks =  Registry::get('Hooks');

    $page = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int)$_GET['page'] : 1;

    if (isset($_GET['ReviewsSentiment'], $_GET['Update'], $_GET['rID'])) {
      $CLICSHOPPING_Hooks->call('ReviewsSentiment', 'Update');

      $CLICSHOPPING_MessageStack->add($CLICSHOPPING_Reviews->getDef('text_success'), 'success', 'main');

      $CLICSHOPPING_Reviews->redirect('ReviewsSentiment&page=' . $page);
    }
  }
}