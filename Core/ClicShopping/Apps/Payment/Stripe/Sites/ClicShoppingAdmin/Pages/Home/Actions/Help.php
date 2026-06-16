<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Payment\Stripe\Sites\ClicShoppingAdmin\Pages\Home\Actions;

use ClicShopping\OM\Registry;

/**
 * Help action class for Stripe payment module administration.
 * 
 * This class handles the help page for the Stripe payment integration,
 * providing documentation and assistance information within the
 * ClicShoppingAdmin environment.
 * 
 * @package ClicShopping\Apps\Payment\Stripe\Sites\ClicShoppingAdmin\Pages\Home\Actions
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */
class Help extends \ClicShopping\OM\Domains\PagesActionsAbstract
{

  /**
   * Execute the help action.
   * 
   * Sets up the help page for the Stripe module, including:
   * - Loading help template file
   * - Setting page action data
   * - Loading help-specific language definitions
   * 
   * @return void
   */
  public function execute()
  {
    $CLICSHOPPING_Stripe = Registry::get('Stripe');

    $this->page->setFile('help.php');
    $this->page->data['action'] = 'Help';

    $CLICSHOPPING_Stripe->loadDefinitions('Sites/ClicShoppingAdmin/help');
  }
}