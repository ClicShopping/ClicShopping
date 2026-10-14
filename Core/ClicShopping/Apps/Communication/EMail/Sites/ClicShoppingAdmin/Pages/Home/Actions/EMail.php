<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Communication\EMail\Sites\ClicShoppingAdmin\Pages\Home\Actions;

use ClicShopping\OM\Registry;

class EMail extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {
    $CLICSHOPPING_EMail = Registry::get('EMail');

    $this->page->setFile('email.php');

    $CLICSHOPPING_EMail->loadDefinitions('Sites/ClicShoppingAdmin/email');
  }
}