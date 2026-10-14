<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */


namespace ClicShopping\Apps\Configuration\ProductsQuantityUnit\Sites\ClicShoppingAdmin\Pages\Home\Actions\ProductsQuantityUnit;

use ClicShopping\OM\Cache;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

class DeleteConfirm extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public mixed $app;

  public function __construct()
  {
    $this->app = Registry::get('ProductsQuantityUnit');
  }

  public function execute()
  {

    if (isset($_GET['oID'])) {
      $oID = HTML::sanitize($_GET['oID']);
      $page = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int)$_GET['page'] : 1;

      $Qstatus = $this->app->db->get('configuration', 'configuration_value', ['configuration_key' => 'DEFAULT_PRODUCTS_QUANTITY_UNIT_STATUS_ID']);

      if ($Qstatus->value('configuration_value') == $oID) {
        $this->app->db->save('configuration', [
          'configuration_value' => ''
        ], [
            'configuration_key' => 'DEFAULT_PRODUCTS_QUANTITY_UNIT_STATUS_ID'
          ]
        );
      }

      $this->app->db->delete('products_quantity_unit', ['products_quantity_unit_id' => (int)$oID]);

      Cache::clear('configuration');

      $this->app->redirect('ProductsQuantityUnit&page=' . $page);
    }
  }
}