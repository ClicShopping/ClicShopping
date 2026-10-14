<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\Settings\Sites\ClicShoppingAdmin\Pages\Home\Actions;

use ClicShopping\OM\Registry;

class SettingsPopUp extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {
    $CLICSHOPPING_Settings = Registry::get('Settings');

    $this->page->setUseSiteTemplate(false); //don't display Header / Footer
    $this->page->setFile('settings_popup.php');

    $CLICSHOPPING_Settings->loadDefinitions('Sites/ClicShoppingAdmin/main');
  }
}