<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\AI\Ecommerce\Module\ClicShoppingAdmin\Config\CAI\Params;

use ClicShopping\OM\HTML;

/**
 * Master switch for the CockpitAI daily cron (productCockpitAi).
 *
 * NB: legacy code reads CLICSHOPPING_APP_ECOMMERCE_EC_CRON_COCKPITAI_STATUS
 * to stay consistent with the EC namespace once both crons are co-located.
 */
class cron_status extends \ClicShopping\Apps\AI\Ecommerce\Module\ClicShoppingAdmin\Config\ConfigParamAbstract
{
  public $default = 'True';
  public int|null $sort_order = 200;

  public function getInputField()
  {
    $value = $this->getInputValue();
    $input  = HTML::radioField($this->key, 'True',  $value, 'id="' . $this->key . '1" autocomplete="off"') . ' '
            . ($this->app->getDef('cfg_ecommerce_cockpit_ai_cron_status_true')  ?: 'Enabled') . ' ';
    $input .= HTML::radioField($this->key, 'False', $value, 'id="' . $this->key . '2" autocomplete="off"') . ' '
            . ($this->app->getDef('cfg_ecommerce_cockpit_ai_cron_status_false') ?: 'Disabled');
    return $input;
  }

  protected function init()
  {
    $this->title       = $this->app->getDef('cfg_ecommerce_cockpit_ai_cron_status_title')       ?: 'CockpitAI daily cron';
    $this->description = $this->app->getDef('cfg_ecommerce_cockpit_ai_cron_status_description') ?: 'Enable the daily CockpitAI cron (analyses products ordered today, modified since last analysis, and never analysed yet with stock > 0).';
  }
}
