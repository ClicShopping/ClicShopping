<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Tools\Cronjob\Sites\ClicShoppingAdmin\Pages\Home\Actions;

use ClicShopping\OM\Registry;

class Edit extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {
    $CLICSHOPPING_Cronjob = Registry::get('Cronjob');

    $this->page->setFile('edit.php');
    $this->page->data['action'] = 'Edit';

    $CLICSHOPPING_Cronjob->loadDefinitions('Sites/ClicShoppingAdmin/Cronjob');
  }
}