<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Sites\Shop\Pages\Account\Actions;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;

/**
 * Account dashboard.
 *
 * `Account&Main` is linked from every account breadcrumb but had no action class: the segment
 * resolved to nothing and the request fell through to the page's default template, which carried
 * the guard and the rendering itself. The page therefore worked by accident, and the router could
 * not tell it from a junk segment. This restores the convention its 23 sibling actions follow.
 */
class Main extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {
    $CLICSHOPPING_Customer = Registry::get('Customer');
    $CLICSHOPPING_Breadcrumb = Registry::get('Breadcrumb');
    $CLICSHOPPING_Template = Registry::get('Template');
    $CLICSHOPPING_NavigationHistory = Registry::get('NavigationHistory');
    $CLICSHOPPING_Language = Registry::get('Language');
    $CLICSHOPPING_Hooks = Registry::get('Hooks');

    if (!$CLICSHOPPING_Customer->isLoggedOn()) {
      $CLICSHOPPING_NavigationHistory->setSnapshot();
      CLICSHOPPING::redirect(null, 'Account&LogIn');
    }

    $CLICSHOPPING_Hooks->call('Main', 'PreAction');

// templates
    $this->page->setFile('main.php');
//Content
    $this->page->data['content'] = $CLICSHOPPING_Template->getTemplateFiles('account');
//language
    $CLICSHOPPING_Language->loadDefinitions('account');

    $CLICSHOPPING_Breadcrumb->add(CLICSHOPPING::getDef('navbar_title'), CLICSHOPPING::link(null, 'Account&Main'));

    $CLICSHOPPING_Hooks->call('Main', 'PostAction');
  }
}
