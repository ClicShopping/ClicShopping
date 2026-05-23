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
 * the status name
 *
 * @param $id
 * @return string $orders_status['orders_status_name'],  name of the status
 *
 */

function clic_cfg_use_get_order_status_title($id)
{

  $CLICSHOPPING_Language = Registry::get('Language');
  $CLICSHOPPING_Db = Registry::get('Db');

  if ($id < 1) {
    return CLICSHOPPING::getDef('text_default');
  } else {

    $Qstatus = $CLICSHOPPING_Db->get('orders_status', 'orders_status_name', ['orders_status_id' => (int)$id,
        'language_id' => $CLICSHOPPING_Language->getId()
      ]
    );

    return $Qstatus->value('orders_status_name');
  }
}
