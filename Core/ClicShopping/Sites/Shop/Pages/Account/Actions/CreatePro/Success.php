<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Sites\Shop\Pages\Account\Actions\CreatePro;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;

class Success extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {
    $CLICSHOPPING_Template = Registry::get('Template');
    $CLICSHOPPING_Breadcrumb = Registry::get('Breadcrumb');
    $CLICSHOPPING_Language = Registry::get('Language');

    unset($_SESSION['process']);
    unset($_SESSION['entry_state_has_zones']);
    unset($_SESSION['country']);
// templates
    $this->page->setFile('create_account_pro_success.php');
//Content
    $this->page->data['content'] = $CLICSHOPPING_Template->getTemplateFiles('create_account_pro_success');
//language
    $CLICSHOPPING_Language->loadDefinitions('create_account_pro_success');

    $CLICSHOPPING_Breadcrumb->add(CLICSHOPPING::getDef('nav_bar_title_1'), CLICSHOPPING::link(null, 'Account&CreatePro'));
    $CLICSHOPPING_Breadcrumb->add(CLICSHOPPING::getDef('nav_bar_title_2'), CLICSHOPPING::link(null, 'Account&CreatePro&Success'));
  }
}