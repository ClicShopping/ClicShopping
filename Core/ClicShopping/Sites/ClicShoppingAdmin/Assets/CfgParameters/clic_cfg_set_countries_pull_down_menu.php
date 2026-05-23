<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

/**
 * @param $default
 * @param $key
 * @return string
 */
//clic_cfg_pull_down_country_list(
function clic_cfg_set_countries_pull_down_menu($default, $key = null)
{
  $CLICSHOPPING_Address = Registry::get('Address');

  $name = (!empty($key) ? 'configuration[' . $key . ']' : 'configuration_value');

  $countries_array = [];

  foreach ($CLICSHOPPING_Address->getCountries() as $country) {

    $countries_array[] = ['id' => $country['countries_id'],
      'text' => $country['countries_name']
    ];
  }

  return HTML::selectMenu($name, $countries_array, $default);
}