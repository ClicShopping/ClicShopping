<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\OrderTotal\TotalShipping\Module\ClicShoppingAdmin\Config\SH\Params;

use ClicShopping\OM\HTML;
use ClicShopping\OM\OrderTotalSequence;

class tax_position extends \ClicShopping\Apps\OrderTotal\TotalShipping\Module\ClicShoppingAdmin\Config\ConfigParamAbstract
{
  // MUST stay equal to SH::$moduletype_position, the default the module ships: the two are
  // compared by unit_test/2026_08_17/order_total_tax_position_test.php.
  public $default = OrderTotalSequence::POSITION_BEFORE_TAX;
  public int|null $sort_order = 20;

  protected function init()
  {
    $this->title = $this->app->getDef('cfg_order_total_shipping_tax_position_title');
    $this->description = $this->app->getDef('cfg_order_total_shipping_tax_position_description');
  }

  public function getInputField()
  {
    $dropdown = [
      ['id' => OrderTotalSequence::POSITION_BEFORE_TAX, 'text' => $this->app->getDef('cfg_order_total_shipping_tax_position_before')],
      ['id' => OrderTotalSequence::POSITION_AFTER_TAX, 'text' => $this->app->getDef('cfg_order_total_shipping_tax_position_after')]
    ];

    return HTML::selectField($this->key, $dropdown, $this->getInputValue());
  }
}
