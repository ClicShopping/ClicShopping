<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Marketing\SEO\Sites\ClicShoppingAdmin\Pages\Home\Actions;

use ClicShopping\OM\Registry;

class SEO extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {
    $CLICSHOPPING_SEO = Registry::get('SEO');

    $this->page->setFile('seo.php');
    $this->page->data['action'] = 'SEO';

    $CLICSHOPPING_SEO->loadDefinitions('Sites/ClicShoppingAdmin/SEO');
  }
}