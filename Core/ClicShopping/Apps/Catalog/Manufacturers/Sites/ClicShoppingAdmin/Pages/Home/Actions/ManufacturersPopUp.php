<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Catalog\Manufacturers\Sites\ClicShoppingAdmin\Pages\Home\Actions;

use ClicShopping\OM\Registry;

class ManufacturersPopUp extends \ClicShopping\OM\Domains\PagesActionsAbstract
{

  protected $use_site_template = false;

  public function execute()
  {
    $CLICSHOPPING_Manufacturers = Registry::get('Manufacturers');

    $this->page->setUseSiteTemplate(false); //don't display Header / Footer
    $this->page->setFile('manufacturers_popup.php');
    $this->page->data['action'] = 'ManufacturersPopUp';

    $CLICSHOPPING_Manufacturers->loadDefinitions('Sites/ClicShoppingAdmin/manufacturers_popup');
  }
}