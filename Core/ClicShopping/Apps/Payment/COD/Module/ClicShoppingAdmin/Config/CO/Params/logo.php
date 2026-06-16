<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Payment\COD\Module\ClicShoppingAdmin\Config\CO\Params;

class logo extends \ClicShopping\Apps\Payment\COD\Module\ClicShoppingAdmin\Config\ConfigParamAbstract
{
  public $default = 'paiement_livraison.jpg';
  public int|null $sort_order = 30;

  protected function init()
  {
    $this->title = $this->app->getDef('cfg_cod_no_logo_title');
    $this->description = $this->app->getDef('cfg_cod_logo_desc');
  }
}
