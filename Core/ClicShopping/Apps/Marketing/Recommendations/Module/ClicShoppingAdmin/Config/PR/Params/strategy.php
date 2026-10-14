<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */


namespace ClicShopping\Apps\Marketing\Recommendations\Module\ClicShoppingAdmin\Config\PR\Params;

use ClicShopping\OM\HTML;

class strategy extends \ClicShopping\Apps\Marketing\Recommendations\Module\ClicShoppingAdmin\Config\ConfigParamAbstract
{
  public $default = 'Range';
  public int|null $sort_order = 15;

  protected function init()
  {
    $this->title = $this->app->getDef('cfg_products_recommendations_strategy_title');
    $this->description = $this->app->getDef('cfg_products_recommendations_strategy_description');
  }

  public function getInputField()
  {
    $value = $this->getInputValue();

    $input = HTML::radioField($this->key, 'Range', $value, 'id="' . $this->key . '1" autocomplete="off"') . $this->app->getDef('cfg_products_recommendations_strategy_range') . '<br />';
    $input .= HTML::radioField($this->key, 'Multiple', $value, 'id="' . $this->key . '2" autocomplete="off"') . $this->app->getDef('cfg_products_recommendations_strategy_multiple');

    return $input;
  }
}