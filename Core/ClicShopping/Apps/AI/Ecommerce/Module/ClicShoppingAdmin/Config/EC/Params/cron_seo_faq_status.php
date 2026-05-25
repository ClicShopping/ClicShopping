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
 * Include Phase 3 FAQ generation in the SEO daily cron.
 *
 * Phase 3 runs SeoFaqPipeline (grounded FAQ with anti-hallucination checks).
 * It is the most expensive phase (multiple LLM calls per product + embedding
 * grounding) so it can be toggled off independently when budget is tight or
 * when the FAQ generation is being debugged.  Set to False if Phase 3 is
 * misbehaving; Phase 1 + Phase 2 of the cron will keep running.
 */
class cron_seo_faq_status extends \ClicShopping\Apps\AI\Ecommerce\Module\ClicShoppingAdmin\Config\ConfigParamAbstract
{
  public $default = 'True';
  public int|null $sort_order = 210;

  public function getInputField()
  {
    $value = $this->getInputValue();
    $input  = HTML::radioField($this->key, 'True',  $value, 'id="' . $this->key . '1" autocomplete="off"') . ' '
            . ($this->app->getDef('cfg_ecommerce_cron_seo_faq_status_true')  ?: 'Enabled') . ' ';
    $input .= HTML::radioField($this->key, 'False', $value, 'id="' . $this->key . '2" autocomplete="off"') . ' '
            . ($this->app->getDef('cfg_ecommerce_cron_seo_faq_status_false') ?: 'Disabled');
    return $input;
  }

  protected function init()
  {
    $this->title       = $this->app->getDef('cfg_ecommerce_cron_seo_faq_status_title')       ?: 'Include FAQ (Phase 3) in SEO cron';
    $this->description = $this->app->getDef('cfg_ecommerce_cron_seo_faq_status_description') ?: 'When enabled, the SEO cron also runs Phase 3 (grounded FAQ generation) after Phase 1 and Phase 2. Disable to skip FAQ in the cron if it is too expensive or under debug.';
  }
}
