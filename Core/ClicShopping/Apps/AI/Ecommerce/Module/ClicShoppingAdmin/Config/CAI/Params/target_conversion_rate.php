<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\AI\Ecommerce\Module\ClicShoppingAdmin\Config\CAI\Params;

use ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\CockpitAI\SubScoring\CommercialScoreAxis;

/**
 * Conversion rate, in percent, worth a full mark on the commercial score.
 */
class target_conversion_rate extends \ClicShopping\Apps\AI\Ecommerce\Module\ClicShoppingAdmin\Config\ConfigParamAbstract
{
  public $default = CommercialScoreAxis::DEFAULT_TARGET_CONVERSION_RATE;
  public int|null $sort_order = 29;
  public bool $app_configured = true;

  protected function init()
  {
    $this->title = $this->app->getDef('cfg_ecommerce_cockpit_ai_target_conversion_rate_title');
    $this->description = $this->app->getDef('cfg_ecommerce_cockpit_ai_target_conversion_rate_description');
  }
}
