<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Catalog\Products\Sites\ClicShoppingAdmin\Pages\Home\Actions;

use ClicShopping\OM\Registry;

/**
 * Class ConfigurationPopUpFields
 *
 * This action class is responsible for displaying the configuration popup fields page in the admin interface.
 * It sets up the page without the standard site template (header/footer) and loads the necessary language definitions.
 */
class ConfigurationPopUpFields extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  /**
  * Execute the action to display the configuration popup fields page.
   *
   * This method configures the page to not use the standard site template, sets the specific file to be used,
   * and loads the relevant language definitions for the configuration popup fields.
   */
  public function execute()
  {
    $CLICSHOPPING_Products = Registry::get('Products');

    $this->page->setUseSiteTemplate(false); //don't display Header / Footer
    $this->page->setFile('configuration_popup_fields.php');
    $this->page->data['action'] = 'ConfigurationPopUpFields';

    $CLICSHOPPING_Products->loadDefinitions('Sites/ClicShoppingAdmin/configuration_popup_fields');
  }
}