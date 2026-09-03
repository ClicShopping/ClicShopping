<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Catalog\Suppliers\Sites\ClicShoppingAdmin\Pages\Home\Actions;

use ClicShopping\OM\Registry;

class SuppliersPopUp extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {
    $CLICSHOPPING_Suppliers = Registry::get('Suppliers');

    $this->page->setUseSiteTemplate(false); //don't display Header / Footer
    $this->page->setFile('suppliers_popup.php');
    $this->page->data['action'] = 'SuppliersPopUp';

    $CLICSHOPPING_Suppliers->loadDefinitions('Sites/ClicShoppingAdmin/suppliers_popup');
  }
}