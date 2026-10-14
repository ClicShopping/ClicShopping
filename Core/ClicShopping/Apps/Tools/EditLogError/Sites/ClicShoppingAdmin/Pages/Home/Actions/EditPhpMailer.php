<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Tools\EditLogError\Sites\ClicShoppingAdmin\Pages\Home\Actions;

use ClicShopping\OM\Registry;

class EditPhpMailer extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {
    $CLICSHOPPING_EditLogError = Registry::get('EditLogError');

    $this->page->setFile('edit_php_mailer.php');
    $this->page->data['action'] = 'LogError';

    $CLICSHOPPING_EditLogError->loadDefinitions('Sites/ClicShoppingAdmin/main');
  }
}