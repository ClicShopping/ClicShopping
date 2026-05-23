<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\ChatGpt\Module\ClicShoppingAdmin\Config\AT\Params;

/**
 * Consensus Threshold Parameter
 * 
 * Controls the minimum weighted score (0.0 to 1.0) required for consensus.
 * Higher threshold = stricter quality requirements but may reject valid responses.
 * 
 * @package ClicShopping\Apps\Configuration\ChatGpt\Module\ClicShoppingAdmin\Config\AT\Params
 * @since 4.2.0
 */
class consensus_threshold extends \ClicShopping\Apps\Configuration\ChatGpt\Module\ClicShoppingAdmin\Config\ConfigParamAbstract
{
  public $default = '0.8';
  public int|null $sort_order = 30;
  public bool $app_configured = true;

  /**
   * Initialize parameter configuration
   */
  protected function init()
  {
    $this->title = $this->app->getDef('cfg_chatgpt_consensus_threshold_title');
    $this->description = $this->app->getDef('cfg_chatgpt_consensus_threshold_description');
  }
}
