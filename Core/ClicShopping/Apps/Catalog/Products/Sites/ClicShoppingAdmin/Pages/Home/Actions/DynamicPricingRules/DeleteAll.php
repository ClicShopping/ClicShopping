<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Catalog\Products\Sites\ClicShoppingAdmin\Pages\Home\Actions\DynamicPricingRules;

use ClicShopping\OM\Registry;

/**
 * DeleteConfirm Class
 * This class implements idempotent operations for product deletion
 * Running this operation multiple times with the same input will produce the same result
 */
class DeleteAll extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public mixed $app;
  protected $id;
  protected $hooks;

  /**
   * Constructor.
   *
   * Initializes the DeleteAll class and retrieves necessary objects from the Registry.
   */
  public function __construct()
  {
    $this->app = Registry::get('Suppliers');
    $this->hooks = Registry::get('Hooks');
  }

  /**
   * Execute the delete all action.
   *
   * Deletes all selected dynamic pricing rules and their associated history records from the database.
   * This operation is idempotent; running it multiple times with the same input will produce the same result.
   * After deletion, redirects to the DynamicPricingRules page.
   */
  public function execute()
  {
    if (isset($_POST['selected'])) {
      foreach ($_POST['selected'] as $id) {
        $Qdelete = $this->app->db->prepare('delete
                                            from :table_dynamic_pricing_rules
                                            where rules_id = :rules_id
                                          ');
        $Qdelete->bindInt(':rules_id', $id);
        $Qdelete->execute();

        $Qdelete = $this->app->db->prepare('delete
                                            from :dynamic_pricing_history
                                            where rules_id = :rules_id
                                          ');
        $Qdelete->bindInt(':rules_id', $id);
        $Qdelete->execute();

        $this->hooks->call('EditDynamicPricingRules', 'DeleteAll');
      }
    }

    $this->app->redirect('EditDynamicPricingRules');
  }
}