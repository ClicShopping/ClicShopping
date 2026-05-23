<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Orders\ReturnOrders\Sites\Shop\Pages\ProductReturnHistoryInfo\Actions;

use ClicShopping\Apps\Orders\ReturnOrders\ReturnOrders as ReturnOrdersApp;
use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

class ProductReturnHistoryInfo extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {
    $CLICSHOPPING_Customer = Registry::get('Customer');
    $CLICSHOPPING_Breadcrumb = Registry::get('Breadcrumb');
    $CLICSHOPPING_Template = Registry::get('Template');
    $CLICSHOPPING_NavigationHistory = Registry::get('NavigationHistory');
    $CLICSHOPPING_Language = Registry::get('Language');
    $CLICSHOPPING_Hooks = Registry::get('Hooks');

    $CLICSHOPPING_Hooks->call('ProductReturnHistoryInfo', 'PreAction');

    if (!$CLICSHOPPING_Customer->isLoggedOn()) {
      $CLICSHOPPING_NavigationHistory->setSnapshot();
      CLICSHOPPING::redirect(null, 'Account&LogIn');
    }

    if (!Registry::exists('ReturnOrders')) {
      Registry::set('ReturnOrders', new ReturnOrdersApp());
    }

    $CLICSHOPPING_ReturnOrders = Registry::get('ReturnOrders');
    $this->app = $CLICSHOPPING_ReturnOrders;

    $rId = null;

    if (isset($_GET['rId'])) {
      $rId = HTML::sanitize($_GET['rId']);
    } else {
      CLICSHOPPING::redirect(null, 'Account&History');
    }

// templates
    $this->page->setFile('product_return_history_info.php');
//Content
    $this->page->data['content'] = $CLICSHOPPING_Template->getTemplateFiles('account_product_return_history_info');
//language
    $CLICSHOPPING_Language->loadDefinitions('account_product_return_history');

    $CLICSHOPPING_Breadcrumb->add(CLICSHOPPING::getDef('navbar_title_1'), CLICSHOPPING::link(null, 'Account&Main'));
    $CLICSHOPPING_Breadcrumb->add(sprintf(CLICSHOPPING::getDef('navbar_title_2', ['rId' => HTML::sanitize($rId)]), $rId), CLICSHOPPING::link(null, 'Account&ProductReturnHistoryInfo&rId=' . (int)HTML::sanitize($rId)));
  }
}
