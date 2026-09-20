<?php
/**
 * AI Data Retention Window Parameter
 *
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 *
 */

namespace ClicShopping\Apps\Configuration\ChatGpt\Module\ClicShoppingAdmin\Config\ASY\Params;

/**
 * How many days of AI agent journals are kept. Zero or less disables the purge.
 *
 * @package ClicShopping\Apps\Configuration\ChatGpt\Module\ClicShoppingAdmin\Config\ASY\Params
 * @since 4.33.0
 */
class data_retention_days extends \ClicShopping\Apps\Configuration\ChatGpt\Module\ClicShoppingAdmin\Config\ConfigParamAbstract
{
  public $default = '90';
  public int|null $sort_order = 51;
  public bool $app_configured = true;

  /**
   * Initialize parameter configuration
   */
  protected function init()
  {
    $this->title = $this->app->getDef('cfg_chatgpt_data_retention_days_title');
    $this->description = $this->app->getDef('cfg_chatgpt_data_retention_days_description');
  }
}
