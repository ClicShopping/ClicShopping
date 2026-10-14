<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Communication\EMail\Sites\ClicShoppingAdmin\Pages\Home\Actions;

use ClicShopping\OM\Registry;

class SendEmailToUser extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {
    $CLICSHOPPING_EMail = Registry::get('EMail');

    $this->page->data['action'] = 'SendEmailToUser';

    $CLICSHOPPING_EMail->loadDefinitions('Sites/ClicShoppingAdmin/email');
  }
}