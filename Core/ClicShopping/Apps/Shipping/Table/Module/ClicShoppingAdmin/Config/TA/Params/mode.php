<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Shipping\Table\Module\ClicShoppingAdmin\Config\TA\Params;

use ClicShopping\OM\HTML;

class mode extends \ClicShopping\Apps\Shipping\Table\Module\ClicShoppingAdmin\Config\ConfigParamAbstract
{
  public $default = 'weight';
  public int|null $sort_order = 10;

  protected function init()
  {
    $this->title = $this->app->getDef('cfg_table_mode_title');
    $this->description = $this->app->getDef('cfg_table_mode_description');
  }

  public function getInputField()
  {
    $value = $this->getInputValue();

    $input = HTML::radioField($this->key, 'weight', $value, 'id="' . $this->key . '1" autocomplete="off"') . $this->app->getDef('cfg_table_mode_weight') . ' ';
    $input .= HTML::radioField($this->key, 'price', $value, 'id="' . $this->key . '2" autocomplete="off"') . $this->app->getDef('cfg_table_mode_price');

    return $input;
  }
}