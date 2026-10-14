<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */


namespace ClicShopping\Apps\Configuration\TaxGeoZones\Module\ClicShoppingAdmin\Config\TG\Params;

use ClicShopping\OM\HTML;

class status extends \ClicShopping\Apps\Configuration\TaxGeoZones\Module\ClicShoppingAdmin\Config\ConfigParamAbstract
{
  public $default = 'True';
  public int|null $sort_order = 10;

  protected function init()
  {
    $this->title = $this->app->getDef('cfg_products_tax_geo_zones_status_title');
    $this->description = $this->app->getDef('cfg_products_tax_geo_zones_status_description');
  }

  public function getInputField()
  {
    $value = $this->getInputValue();

    $input = HTML::radioField($this->key, 'True', $value, 'id="' . $this->key . '1" autocomplete="off"') . $this->app->getDef('cfg_products_tax_geo_zones_status_true') . ' ';
    $input .= HTML::radioField($this->key, 'False', $value, 'id="' . $this->key . '2" autocomplete="off"') . $this->app->getDef('cfg_products_tax_geo_zones_status_false');

    return $input;
  }
}