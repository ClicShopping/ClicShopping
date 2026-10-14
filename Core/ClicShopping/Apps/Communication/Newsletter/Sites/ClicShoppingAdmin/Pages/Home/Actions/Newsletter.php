<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Communication\Newsletter\Sites\ClicShoppingAdmin\Pages\Home\Actions;

use ClicShopping\OM\Registry;

class Newsletter extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {
    $CLICSHOPPING_Newsletter = Registry::get('Newsletter');

    $this->page->setFile('newsletter.php');
    $this->page->data['action'] = 'Newsletter';

    $CLICSHOPPING_Newsletter->loadDefinitions('Sites/ClicShoppingAdmin/Newsletter');
  }
}