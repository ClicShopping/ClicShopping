<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\AI\Ecommerce\Module\ClicShoppingAdmin\Config\EC\Params;

use ClicShopping\OM\HTML;

/**
 * Master switch for the SEO daily cron (productSeoOptimization).
 * When False, the cron exits early without consuming LLM credits.
 */
class cron_seo_status extends \ClicShopping\Apps\AI\Ecommerce\Module\ClicShoppingAdmin\Config\ConfigParamAbstract
{
  public $default = 'True';
  public int|null $sort_order = 200;

  public function getInputField()
  {
    $value = $this->getInputValue();
    $input  = HTML::radioField($this->key, 'True',  $value, 'id="' . $this->key . '1" autocomplete="off"') . ' '
            . ($this->app->getDef('cfg_ecommerce_cron_seo_status_true')  ?: 'Enabled') . ' ';
    $input .= HTML::radioField($this->key, 'False', $value, 'id="' . $this->key . '2" autocomplete="off"') . ' '
            . ($this->app->getDef('cfg_ecommerce_cron_seo_status_false') ?: 'Disabled');
    return $input;
  }

  protected function init()
  {
    $this->title       = $this->app->getDef('cfg_ecommerce_cron_seo_status_title')       ?: 'SEO daily cron';
    $this->description = $this->app->getDef('cfg_ecommerce_cron_seo_status_description') ?: 'Enable the daily SEO optimization cron (Phase 1 + 2 + optional Phase 3 FAQ). Recommended to run at 02:00 AM via the Cronjob app.';
  }
}
