<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\ChatGpt\Module\ClicShoppingAdmin\Config\RA\Params;

class security_alert_email extends \ClicShopping\Apps\Configuration\ChatGpt\Module\ClicShoppingAdmin\Config\ConfigParamAbstract
{
  public $default = '';
  public int|null $sort_order = 150;

  protected function init()
  {
    $this->title = $this->app->getDef('cfg_chatgpt_security_alert_title');
    $this->description = $this->app->getDef('cfg_chatgpt_security_alert_description');
  }
}
