<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\AI\Ecommerce\Module\ClicShoppingAdmin\Config\ACP\Params;

use ClicShopping\OM\HTML;

class api_key_openai_retail extends \ClicShopping\Apps\AI\Ecommerce\Module\ClicShoppingAdmin\Config\ConfigParamAbstract
{

  public $default = '';
  public int|null $sort_order = 20;

  protected function init()
  {
    $this->title = $this->app->getDef('cfg_ecommerce_acp_api_key_openai_retail_title');
    $this->description = $this->app->getDef('cfg_ecommerce_acp_api_key_openai_retail_description');
  }

  public function getInputField()
  {
    $value = $this->getInputValue();

    $input = HTML::passwordField($this->key, $value, 'id="' . $this->key . '" autocomplete="off"');

    return $input;
  }
}
