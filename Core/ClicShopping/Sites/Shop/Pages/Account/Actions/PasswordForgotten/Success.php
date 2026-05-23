<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Sites\Shop\Pages\Account\Actions\PasswordForgotten;

use ClicShopping\OM\Registry;

class Success extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {
    $CLICSHOPPING_Template = Registry::get('Template');
    $CLICSHOPPING_Language = Registry::get('Language');
// templates
    $this->page->setFile('password_forgotten_success.php');
//Content
    $this->page->data['content'] = $CLICSHOPPING_Template->getTemplateFiles('password_forgotten_success');
//language
    $CLICSHOPPING_Language->loadDefinitions('password_forgotten_success');
  }
}