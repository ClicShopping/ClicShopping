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
 * Class SaveDynamicPricingRules
 *
 * This action class handles the saving of dynamic pricing rules in the admin interface.
 * It processes both the insertion of new rules and the updating of existing ones based on user input.
 */
class SaveDynamicPricingRules extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public mixed $app;

  /**
   * Constructor.
   *
   * Initializes the SaveDynamicPricingRules action and retrieves the Products app from the Registry.
   */
  public function __construct()
  {
    $this->app = Registry::get('Products');
  }

  /**
   * Execute the action to save dynamic pricing rules.
   *
   * This method handles both the insertion of new rules and the updating of existing ones
   * based on the presence of a rule ID (rID) in the GET parameters. It sanitizes input data,
   * prepares the data array, and calls the appropriate save method from the Products app.
   * After saving, it triggers any relevant hooks and redirects to the EditDynamicPricingRules page.
   */
  public function execute()
  {
    $CLICSHOPPING_Hooks = Registry::get('Hooks');

    if (isset($_POST['rules_status_special'])) {
      $rules_status_special = 1;
    } else {
      $rules_status_special = 0;
    }

    if (isset($_POST['rules_status_promotion'])) {
      $rules_status_promotion = 1;
    } else {
      $rules_status_promotion = 0;
    }

    if (isset($_POST['rules_status'])) {
      $rules_status = 1;
    } else {
      $rules_status = 0;
    }

    if (isset($_POST['customers_group'])) {
      $customers_group = 1;
    } else {
      $customers_group = 0;
    }

    if (isset($_GET['rID']) && is_numeric($_GET['rID'])) {
      $rID = HTML::sanitize($_GET['rID']);

      $update_array = [
        'rules_name' => HTML::sanitize($_POST['rules_name'] ?? ''),
        'rules_condition' => $_POST['rules_condition'] ?? '',
        'rules_type' => HTML::sanitize($_POST['rules_type'] ?? ''),
        'rules_value' => HTML::sanitize($_POST['rules_value'] ?? ''),
        'rules_priority' => HTML::sanitize($_POST['rules_priority'] ?? ''),
        'rules_status' => $rules_status,
        'date_modified' => 'now()',
        'rules_status_special' => $rules_status_special,
        'rules_status_promotion' => $rules_status_promotion,
        'customers_group' => $customers_group
      ];

      $this->app->db->save('dynamic_pricing_rules', $update_array,  ['rules_id' => $rID]);

      $CLICSHOPPING_Hooks->call('Products', 'SaveDynamicPricingRules', ['rules_id' => $rID]);
    } else {
      $insert_array = [
        'rules_name' => HTML::sanitize($_POST['rules_name'] ?? ''),
        'rules_condition' => $_POST['rules_condition'] ?? '',
        'rules_type' => HTML::sanitize($_POST['rules_type'] ?? ''),
        'rules_value' => HTML::sanitize($_POST['rules_value'] ?? ''),
        'rules_priority' => HTML::sanitize($_POST['rules_priority'] ?? ''),
        'rules_status' => $rules_status,
        'date_added' => 'now()',
        'rules_status_special' => $rules_status_special,
        'rules_status_promotion' => $rules_status_promotion,
        'customers_group' => $customers_group
      ];


      $this->app->db->save('dynamic_pricing_rules', $insert_array);

      $CLICSHOPPING_Hooks->call('Products', 'SaveDynamicPricingRules');
    }

    $this->app->redirect('DynamicPricingRules');
  }
}