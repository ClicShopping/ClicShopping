<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */


namespace ClicShopping\Apps\AI\Ecommerce\Module\ClicShoppingAdmin\Config\CAI\Params;

use ClicShopping\OM\HTML;

class strategy_y extends \ClicShopping\Apps\AI\Ecommerce\Module\ClicShoppingAdmin\Config\ConfigParamAbstract
{
  public $default = 'conversion';
  public int|null $sort_order = 60;

  public function getInputField()
  {
    $value = $this->getInputValue();

    $input = HTML::radioField($this->key, 'conversion', $value, 'id="' . $this->key . '1" autocomplete="off"') . $this->app->getDef('cfg_ecommerce_cockpit_ai_strategy_y_conversion') . ' ';
    $input .= HTML::radioField($this->key, 'revenue', $value, 'id="' . $this->key . '2" autocomplete="off"') . $this->app->getDef('cfg_ecommerce_cockpit_ai_strategy_y_revenue');
    $input .= HTML::radioField($this->key, 'engagement', $value, 'id="' . $this->key . '2" autocomplete="off"') . $this->app->getDef('cfg_ecommerce_cockpit_ai_strategy_y_engagement');

    return $input;
  }

  protected function init()
  {
    $this->title = $this->app->getDef('cfg_ecommerce_cockpit_ai_strategy_y_title');
    $this->description = $this->app->getDef('cfg_ecommerce_cockpit_ai_strategy_y_description');
  }
}