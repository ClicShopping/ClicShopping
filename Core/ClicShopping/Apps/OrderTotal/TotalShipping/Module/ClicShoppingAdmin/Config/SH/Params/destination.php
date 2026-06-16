<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\OrderTotal\TotalShipping\Module\ClicShoppingAdmin\Config\SH\Params;

use ClicShopping\OM\HTML;

class destination extends \ClicShopping\Apps\OrderTotal\TotalShipping\Module\ClicShoppingAdmin\Config\ConfigParamAbstract
{
  public $default = 'national';
  public int|null $sort_order = 15;

  protected function init()
  {
    $this->title = $this->app->getDef('cfg_order_total_shipping_destination_title');
    $this->description = $this->app->getDef('cfg_order_total_shipping_destination_description');
  }

  public function getInputField()
  {
    $value = $this->getInputValue();

    $dropdown = array(array('id' => 'national', 'text' => $this->app->getDef('cfg_order_total_shipping_destination_national')),
      array('id' => 'international', 'text' => $this->app->getDef('cfg_order_total_shipping_destination_international')),
      array('id' => 'both', 'text' => $this->app->getDef('cfg_order_total_shipping_destination_both')),
    );

    $input = HTML::selectField($this->key, $dropdown, $value);

    return $input;
  }
}