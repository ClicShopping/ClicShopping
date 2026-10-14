<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\Registry;

/**
 *
 * return class title
 * @param int $id
 * @return string $orders_status['orders_status_name'],  name of the status
 */

function clic_cfg_use_get_products_length_title($id)
{

  $CLICSHOPPING_Language = Registry::get('Language');
  $CLICSHOPPING_Db = Registry::get('Db');

  $Qweight_title = $CLICSHOPPING_Db->get('products_length_classes', 'products_length_class_title', ['products_length_class_id' => (int)$id,
      'language_id' => $CLICSHOPPING_Language->getId()
    ]
  );

  return $Qweight_title->value('products_length_class_title');
}
