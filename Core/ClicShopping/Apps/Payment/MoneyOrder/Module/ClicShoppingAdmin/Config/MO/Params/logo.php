<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Payment\MoneyOrder\Module\ClicShoppingAdmin\Config\MO\Params;

class logo extends \ClicShopping\Apps\Payment\MoneyOrder\Module\ClicShoppingAdmin\Config\ConfigParamAbstract
{
  public $default = 'cheque.gif';
  public int|null $sort_order = 30;

  protected function init()
  {
    $this->title = $this->app->getDef('cfg_moneyorder_title');
    $this->description = $this->app->getDef('cfg_moneyorder_desc');
  }
}
