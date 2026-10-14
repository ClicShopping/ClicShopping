<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Tools\DefineLanguage\Sites\ClicShoppingAdmin\Pages\Home\Actions;

use ClicShopping\OM\Registry;

class Contentgroup extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {
    $CLICSHOPPING_DefineLanguage = Registry::get('DefineLanguage');

    $this->page->setFile('content_group.php');
    $this->page->data['action'] = 'ContentGroupe';

    $CLICSHOPPING_DefineLanguage->loadDefinitions('Sites/ClicShoppingAdmin/main');
  }
}