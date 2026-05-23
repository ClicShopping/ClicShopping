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
function clic_cfg_use_get_tax_class_title($id)
{
  $CLICSHOPPING_Db = Registry::get('Db');

  if ($id < 1) {
    return CLICSHOPPING::getDef('text_none');
  } else {

    $Qclass = $CLICSHOPPING_Db->prepare('select tax_class_title 
                                     from :table_tax_class 
                                     where tax_class_id = :tax_class_id
                                   ');
    $Qclass->bindInt(':tax_class_id', $id);
    $Qclass->execute();

    return $Qclass->value('tax_class_title');
  }
}