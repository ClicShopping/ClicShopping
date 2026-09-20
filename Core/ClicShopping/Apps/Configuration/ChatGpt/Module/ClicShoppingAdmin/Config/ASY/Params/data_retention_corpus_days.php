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
 * How many days of the measurement corpus are kept — the questions users asked, the answers and
 * the executed SQL. Zero, the default, keeps it for ever.
 *
 * @package ClicShopping\Apps\Configuration\ChatGpt\Module\ClicShoppingAdmin\Config\ASY\Params
 * @since 4.33.0
 */
class data_retention_corpus_days extends \ClicShopping\Apps\Configuration\ChatGpt\Module\ClicShoppingAdmin\Config\ConfigParamAbstract
{
  public $default = '0';
  public int|null $sort_order = 52;
  public bool $app_configured = true;

  /**
   * Initialize parameter configuration
   */
  protected function init()
  {
    $this->title = $this->app->getDef('cfg_chatgpt_data_retention_corpus_days_title');
    $this->description = $this->app->getDef('cfg_chatgpt_data_retention_corpus_days_description');
  }
}
