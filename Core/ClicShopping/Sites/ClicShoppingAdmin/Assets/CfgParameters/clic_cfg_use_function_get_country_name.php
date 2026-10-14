<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

/**
 * the clic_cfg_use_function_get_country_name name
 *
 * @param string $orders_status_id , $language_id
 * @return string $orders_status['orders_status_name'],  name of the status
 *
 */

use ClicShopping\OM\Registry;

/**
 * Country name name
 *
 * @param string $country_id country_id
 * @return string $country['countries_name'] the country name
 *
 */

function clic_cfg_use_function_get_country_name($country_id)
{

  $Qcountry = Registry::get('Db')->get('countries', 'countries_name', ['countries_id' => (int)(int)$country_id],
    ['status' => 1]
  );


  if ($Qcountry->fetch() === false) {
    return $country_id;
  } else {
    return $Qcountry->value('countries_name');
  }
}