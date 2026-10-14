<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\Settings\Sites\ClicShoppingAdmin\Pages\Home\Actions;

use ClicShopping\OM\Registry;

class Settings extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {
    $CLICSHOPPING_Settings = Registry::get('Settings');

    $this->page->setFile('settings.php');
    $this->page->data['action'] = 'Settings';

    $CLICSHOPPING_Settings->loadDefinitions('Sites/ClicShoppingAdmin/Settings');
  }
}