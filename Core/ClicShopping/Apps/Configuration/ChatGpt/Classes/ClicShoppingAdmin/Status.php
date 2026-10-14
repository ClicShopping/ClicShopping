<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin;

use ClicShopping\OM\Registry;

class Status
{
  public static function getWebSearchRagStatus(int $id, int $status)
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    if ($status == 1) {
      return $CLICSHOPPING_Db->save('rag_websearch', ['status' => 1],  ['id' => (int)$id] );
    } elseif ($status == 0) {
      return $CLICSHOPPING_Db->save('rag_websearch', ['status' => 0], ['id' => (int)$id]);

    } else {
      return -1;
    }
  }
}