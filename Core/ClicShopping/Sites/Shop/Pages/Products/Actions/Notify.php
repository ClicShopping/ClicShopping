<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Sites\Shop\Pages\Products\Actions;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;

class Notify extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_ProductsCommon = Registry::get('ProductsCommon');
    $CLICSHOPPING_Language = Registry::get('Language');
    $CLICSHOPPING_Customer = Registry::get('Customer');
    $CLICSHOPPING_NavigationHistory = Registry::get('NavigationHistory');
    $CLICSHOPPING_MessageStack = Registry::get('MessageStack');

    $products_id = $CLICSHOPPING_ProductsCommon->getId();

    $Qproduct = $CLICSHOPPING_Db->prepare('select p.products_id
                                             from :table_products p,
                                                  :table_products_description pd,
                                                  :table_products_to_categories p2c,
                                                  :table_categories c
                                             where p.products_status = 1
                                             and p.products_id =:products_id
                                             and pd.language_id = :language_id
                                             and p.products_id = p2c.products_id
                                             and p2c.categories_id = c.categories_id
                                             and c.status = 1
                                            ');
    $Qproduct->bindInt(':products_id', $products_id);
    $Qproduct->bindInt(':language_id', $CLICSHOPPING_Language->getId());
    $Qproduct->execute();

    $product_exists = ($Qproduct->fetch() !== false);

    if ($product_exists === false) {
      CLICSHOPPING::redirect();
    }

// if the customer is not logged on, redirect them to the login page
    if (!$CLICSHOPPING_Customer->isLoggedOn()) {
      $CLICSHOPPING_NavigationHistory->setSnapshot();
      $CLICSHOPPING_MessageStack->add(CLICSHOPPING::getDef('success_notifications_updated'), 'success');

      CLICSHOPPING::redirect(null, 'Account&LogIn');
    }
  }
}

