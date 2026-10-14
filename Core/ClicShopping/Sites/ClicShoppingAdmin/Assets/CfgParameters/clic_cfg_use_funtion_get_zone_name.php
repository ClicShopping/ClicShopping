<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\Registry;

/**
 * Function select a zone
 *
 * @param string $zone_id text
 * @return string zone['zone_name'], the zone name of the country
 */
function clic_cfg_use_funtion_get_zone_name($zone_id)
{
  $Qzone = Registry::get('Db')->get('zones', 'zone_name', ['zone_id' => (int)$zone_id]);


  if ($Qzone->fetch() === false) {
    return $zone_id;
  } else {
    return $Qzone->value('zone_name');
  }
}