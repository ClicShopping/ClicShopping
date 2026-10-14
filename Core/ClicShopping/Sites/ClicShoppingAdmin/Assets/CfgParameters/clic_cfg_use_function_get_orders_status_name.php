<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

/**
 * the status name
 *
 * @param string $orders_status_id , $language_id
 * @return string $orders_status['orders_status_name'],  name of the status
 *
 */

use ClicShopping\OM\Registry;

function clic_cfg_use_function_get_orders_status_name($orders_status_id, $language_id = '')
{
  $CLICSHOPPING_Language = Registry::get('Language');
  $CLICSHOPPING_Db = Registry::get('Db');

  if (!$language_id) $language_id = $CLICSHOPPING_Language->getId();

  $Qstatus = $CLICSHOPPING_Db->get('orders_status', 'orders_status_name', ['orders_status_id' => (int)$orders_status_id, 'language_id' => $language_id]);

  return $Qstatus->value('orders_status_name');
}