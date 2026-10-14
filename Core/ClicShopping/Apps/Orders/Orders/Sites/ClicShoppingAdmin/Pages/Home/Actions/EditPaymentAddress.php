<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Orders\Orders\Sites\ClicShoppingAdmin\Pages\Home\Actions;

use ClicShopping\OM\Registry;

class EditPaymentAddress extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  protected $use_site_template = false;

  public function execute()
  {
    $CLICSHOPPING_Orders = Registry::get('Orders');

    $this->page->setUseSiteTemplate(false); //don't display Header / Footer
    $this->page->setFile('edit_payment_address.php');

    $CLICSHOPPING_Orders->loadDefinitions('Sites/ClicShoppingAdmin/main');
  }
}