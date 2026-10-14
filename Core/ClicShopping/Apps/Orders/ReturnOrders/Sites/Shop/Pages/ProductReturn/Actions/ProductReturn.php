<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Orders\ReturnOrders\Sites\Shop\Pages\ProductReturn\Actions;

use ClicShopping\Apps\Orders\ReturnOrders\ReturnOrders as ReturnOrdersApp;
use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;
use ClicShopping\Sites\Shop\Pages\Account\Classes\HistoryInfo as Info;

class ProductReturn extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {
    $CLICSHOPPING_Customer = Registry::get('Customer');
    $CLICSHOPPING_Breadcrumb = Registry::get('Breadcrumb');
    $CLICSHOPPING_Template = Registry::get('Template');
    $CLICSHOPPING_NavigationHistory = Registry::get('NavigationHistory');
    $CLICSHOPPING_Language = Registry::get('Language');
    $CLICSHOPPING_Hooks = Registry::get('Hooks');

    $CLICSHOPPING_Hooks->call('ProductReturn', 'PreAction');

    if (!$CLICSHOPPING_Customer->isLoggedOn()) {
      $CLICSHOPPING_NavigationHistory->setSnapshot();
      CLICSHOPPING::redirect(null, 'Account&LogIn');
    }

    if (!Registry::exists('ReturnOrders')) {
      Registry::set('ReturnOrders', new ReturnOrdersApp());
    }

    $CLICSHOPPING_ReturnOrders = Registry::get('ReturnOrders');
    $this->app = $CLICSHOPPING_ReturnOrders;

    if (!isset($_GET['order_id']) || (isset($_GET['order_id']) && !is_numeric($_GET['order_id']))) {
      CLICSHOPPING::redirect(null, 'Account&History');
    }

    $check_history_info = Info::getHistoryInfoCheck();

    if ($check_history_info === false || ($check_history_info['customers_id'] != $CLICSHOPPING_Customer->getID())) {
      CLICSHOPPING::redirect('index', 'Account&History');
    }

// templates
    $this->page->setFile('product_return.php');
//Content
    $this->page->data['content'] = $CLICSHOPPING_Template->getTemplateFiles('account_product_return');
//language
    $CLICSHOPPING_Language->loadDefinitions('account_product_return_history');

    $CLICSHOPPING_Breadcrumb->add(CLICSHOPPING::getDef('navbar_title_1'), CLICSHOPPING::link(null, 'Account&Main'));
    $CLICSHOPPING_Breadcrumb->add(sprintf(CLICSHOPPING::getDef('navbar_title_2', ['order_id' => HTML::sanitize($_GET['order_id'])]), $_GET['order_id']), CLICSHOPPING::link(null, 'Account&ProductReturn&order_id=' . (int)HTML::sanitize($_GET['order_id'])));
  }
}
