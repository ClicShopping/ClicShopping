<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */
namespace ClicShopping\Apps\Configuration\ChatGpt\Sites\ClicShoppingAdmin\Pages\Home\Actions;

use ClicShopping\OM\Registry;

class Dashboard extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {
    $CLICSHOPPING_ChatGpt = Registry::get('ChatGpt');

    $this->page->setFile('dashboard.php');
    $this->page->data['action'] = 'RAG';

    $CLICSHOPPING_ChatGpt->loadDefinitions('Sites/ClicShoppingAdmin/dashboard');
  }
}
