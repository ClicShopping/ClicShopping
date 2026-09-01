<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\AI\Ecommerce\Module\ClicShoppingAdmin\Config\UCP\Params;

class session_timeout extends \ClicShopping\Apps\AI\Ecommerce\Module\ClicShoppingAdmin\Config\ConfigParamAbstract
{
  public $default = '86400';
  public int|null $sort_order = 60;

  protected function init()
  {
    $this->title = $this->app->getDef('cfg_ecommerce_ucp_session_timeout_title');
    $this->description = $this->app->getDef('cfg_ecommerce_ucp_session_timeout_description');
  }
}
