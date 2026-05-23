<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\TaxRates\Sites\ClicShoppingAdmin\Pages\Home\Actions\TaxRates;

use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

class Insert extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public mixed $app;

  public function __construct()
  {
    $this->app = Registry::get('TaxRates');
  }

  public function execute()
  {
    $page = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int)$_GET['page'] : 1;

    if (!is_numeric($_POST['tax_rate'])) {
      $this->app->redirect('TaxRates&page=' . $page);
    }

    $tax_zone_id = HTML::sanitize($_POST['tax_zone_id']);
    $tax_class_id = HTML::sanitize($_POST['tax_class_id']);
    $tax_rate = HTML::sanitize($_POST['tax_rate']);
    $tax_description = HTML::sanitize($_POST['tax_description']);
    $tax_priority = HTML::sanitize($_POST['tax_priority']);
    $code_tax_erp = HTML::sanitize($_POST['code_tax_erp']);

    $insert_array = [
      'tax_zone_id' => (int)$tax_zone_id,
      'tax_class_id' => (int)$tax_class_id,
      'tax_rate' => (float)$tax_rate,
      'tax_description' => $tax_description,
      'tax_priority' => (int)$tax_priority,
      'date_added' => 'now()',
      'code_tax_erp' => $code_tax_erp
    ];

    $this->app->db->save('tax_rates', $insert_array);

    $this->app->redirect('TaxRates&page=' . $page);
  }
}