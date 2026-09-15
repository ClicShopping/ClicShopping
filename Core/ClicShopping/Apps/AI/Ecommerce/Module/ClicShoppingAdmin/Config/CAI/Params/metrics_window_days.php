<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\AI\Ecommerce\Module\ClicShoppingAdmin\Config\CAI\Params;

use ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\CockpitAI\SubOrchestrator\DataCollector;

/**
 * Window, in days, the commercial metrics are read on: views, orders and the conversion rate.
 * Widening it also widens the impression retention, which can never fall below it.
 */
class metrics_window_days extends \ClicShopping\Apps\AI\Ecommerce\Module\ClicShoppingAdmin\Config\ConfigParamAbstract
{
  public $default = DataCollector::DEFAULT_METRICS_WINDOW_DAYS;
  public int|null $sort_order = 27;
  public bool $app_configured = true;

  protected function init()
  {
    $this->title = $this->app->getDef('cfg_ecommerce_cockpit_ai_metrics_window_days_title');
    $this->description = $this->app->getDef('cfg_ecommerce_cockpit_ai_metrics_window_days_description');
  }
}
