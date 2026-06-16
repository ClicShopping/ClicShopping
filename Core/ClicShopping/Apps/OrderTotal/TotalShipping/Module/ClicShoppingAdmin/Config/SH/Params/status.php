<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */


namespace ClicShopping\Apps\OrderTotal\TotalShipping\Module\ClicShoppingAdmin\Config\SH\Params;

use ClicShopping\OM\HTML;
use ClicShopping\Apps\OrderTotal\TotalShipping\Module\ClicShoppingAdmin\Config\ConfigParamAbstract;

class status extends ConfigParamAbstract
{
  public $default = 'True';
  public int|null $sort_order = 10;
  public bool $app_configured = true;

  protected function init()
  {
    $this->title = $this->app->getDef('cfg_order_total_shipping_status_title');
    $this->description = $this->app->getDef('cfg_order_total_shipping_status_description');
  }

  public function getInputField()
  {
    $value = $this->getInputValue();

    $input = HTML::radioField($this->key, 'True', $value, 'id="' . $this->key . '1" autocomplete="off"') . $this->app->getDef('cfg_order_total_shipping_status_true') . ' ';
    $input .= HTML::radioField($this->key, 'False', $value, 'id="' . $this->key . '2" autocomplete="off"') . $this->app->getDef('cfg_order_total_shipping_status_false');

    return $input;
  }
}