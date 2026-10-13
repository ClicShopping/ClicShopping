<?php
/**
 * AI Objective Queue Retention Window Parameter
 *
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 *
 */

namespace ClicShopping\Apps\Configuration\ChatGpt\Module\ClicShoppingAdmin\Config\ASY\Params;

/**
 * How many days of the agent objective queue are kept. Its success criteria carry the user's own
 * question (AES-encrypted), so the operator sets its window. 90 by default; zero keeps it for ever.
 *
 * @package ClicShopping\Apps\Configuration\ChatGpt\Module\ClicShoppingAdmin\Config\ASY\Params
 * @since 4.33.0
 */
class data_retention_objectives_days extends \ClicShopping\Apps\Configuration\ChatGpt\Module\ClicShoppingAdmin\Config\ConfigParamAbstract
{
  public $default = '90';
  public int|null $sort_order = 53;
  public bool $app_configured = true;

  /**
   * Initialize parameter configuration
   */
  protected function init()
  {
    $this->title = $this->app->getDef('cfg_chatgpt_data_retention_objectives_days_title');
    $this->description = $this->app->getDef('cfg_chatgpt_data_retention_objectives_days_description');
  }
}
