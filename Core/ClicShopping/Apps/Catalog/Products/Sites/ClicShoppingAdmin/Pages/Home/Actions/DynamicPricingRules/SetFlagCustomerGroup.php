<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Catalog\Products\Sites\ClicShoppingAdmin\Pages\Home\Actions\DynamicPricingRules;


use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

/**
 * Class SetFlag
 *
 * This action class handles setting the status flag for dynamic pricing rules in the admin interface.
 * It updates the rule's status based on user input and redirects to the DynamicPricingRules page.
 */
class SetFlagCustomerGroup extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public mixed $app;

  /**
   * Constructor.
   *
   * Initializes the SetFlag action and retrieves the Products app from the Registry.
   */
  public function __construct()
  {
    $this->app = Registry::get('Products');
  }

  /**
   * Execute the action to set the flag for a dynamic pricing rule.
   *
   * This method updates the status of a dynamic pricing rule based on the provided
   * rule ID (rID) and flag value in the GET parameters. It sanitizes input data,
   * updates the database, and redirects to the DynamicPricingRules page.
   */
  public function execute()
  {
    if (isset($_GET['flag']) && ($_GET['flag'] == 0 || $_GET['flag'] == 1)) {
      if (isset($_GET['rID']) && is_numeric($_GET['rID'])) {
        $CLICSHOPPING_Db = Registry::get('Db');

        $rID = HTML::sanitize($_GET['rID']);

        $status = (int)$_GET['flag'];

        if ($status == 1) {
          return $CLICSHOPPING_Db->save('dynamic_pricing_rules', [
            'customers_group' => 1,
            'date_added' => 'now()'
          ],
            ['rules_id' => (int)$rID]
          );

        } elseif ($status == 0) {
          return $CLICSHOPPING_Db->save('dynamic_pricing_rules', [
            'customers_group' => 0,
            'date_added' => 'now()'
          ],
            ['rules_id' => (int)$rID]
          );

        } else {
          return -1;
        }
      }
    }

    $this->app->redirect('DynamicPricingRules');
  }
}