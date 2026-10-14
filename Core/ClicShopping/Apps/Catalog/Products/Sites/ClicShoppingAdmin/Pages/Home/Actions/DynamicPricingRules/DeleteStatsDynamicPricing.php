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
class DeleteStatsDynamicPricing extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public mixed $app;

  /**
   * Constructor.
   *
   * Initializes the DeleteAll class and retrieves necessary objects from the Registry.
   */
  public function __construct()
  {
    $this->app = Registry::get('Products');
  }

  /**
   * Execute the deletion of all dynamic pricing history records.
   * If the 'resetHistory' parameter is set to 1, it deletes all records from the dynamic pricing history table.
   * After execution, it redirects to the StatsDynamicPricing page.
   */
  public function execute()
  {
    if (isset($_GET['DynamicPricingRules']) && ($_GET['resetHistory'] == 1)) {
        $Qdelete = $this->app->db->prepare('delete
                                            from :table_dynamic_pricing_history
                                          ');
        $Qdelete->execute();
      }

      $this->app->redirect('StatsDynamicPricing');
    }
}