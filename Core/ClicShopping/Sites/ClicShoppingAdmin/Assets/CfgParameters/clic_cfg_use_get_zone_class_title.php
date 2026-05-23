<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */


use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;

/**
 * @param $id
 * @return string
 */
function clic_cfg_use_get_zone_class_title($id)
{
  $CLICSHOPPING_Db = Registry::get('Db');

  if ($id == 0) {
    return CLICSHOPPING::getDef('text_none');
  } else {
    $Qclass = $CLICSHOPPING_Db->prepare('select geo_zone_name
                                     from :table_geo_zones
                                     where geo_zone_id = :geo_zone_id
                                     ');
    $Qclass->bindInt(':geo_zone_id', $id);
    $Qclass->execute();

    return $Qclass->value('geo_zone_name');
  }
}
