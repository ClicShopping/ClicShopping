<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Tools\Cronjob\Sites\ClicShoppingAdmin\Pages\Home\Actions;

use ClicShopping\OM\Registry;

class Cronjob extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {
    $CLICSHOPPING_Cronjob = Registry::get('Cronjob');

    $this->page->setFile('cronjob.php');
    $this->page->data['action'] = 'Cronjob';

    $CLICSHOPPING_Cronjob->loadDefinitions('Sites/ClicShoppingAdmin/Cronjob');
  }
}