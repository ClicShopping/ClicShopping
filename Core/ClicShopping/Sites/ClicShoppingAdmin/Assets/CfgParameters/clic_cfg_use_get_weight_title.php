<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\Registry;

/**
 * the weight title
 *
 * @param int $id id
 * @return string $orders_status['orders_status_name'],  name of the status
 */

function clic_cfg_use_get_weight_title($id)
{

  $CLICSHOPPING_Language = Registry::get('Language');
  $CLICSHOPPING_Db = Registry::get('Db');

  $Qweight_title = $CLICSHOPPING_Db->get('weight_classes', 'weight_class_title', ['weight_class_id' => (int)$id,
      'language_id' => $CLICSHOPPING_Language->getId()
    ]
  );

  return $Qweight_title->value('weight_class_title');
}
