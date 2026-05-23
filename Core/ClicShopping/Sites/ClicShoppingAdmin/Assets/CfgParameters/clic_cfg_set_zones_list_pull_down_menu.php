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
 * @param $zone_id
 * @return string
 */
function clic_cfg_set_zones_list_pull_down_menu($zone_id)
{
  $CLICSHOPPING_Address = Registry::get('Address');

  return HTML::selectMenu('configuration_value', $CLICSHOPPING_Address->getCountryZones(STORE_COUNTRY), $zone_id);
}