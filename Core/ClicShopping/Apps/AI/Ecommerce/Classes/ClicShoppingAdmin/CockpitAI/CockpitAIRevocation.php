<?php
  /**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

  namespace ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\CockpitAI;

  use ClicShopping\OM\Registry;

  class CockpitAIRevocation {
    protected $db;

    public function __construct() {
      $this->db = Registry::get('Db');
    }

    public function revoke(string $token): array {
      $Qaction = $this->db->prepare('SELECT * 
                                     FROM :table_products_cockpit_ai_action_log 
                                     WHERE revocation_token = :token 
                                     AND status = "executed"
                                     LIMIT 1');
      $Qaction->bindValue(':token', $token);
      $Qaction->execute();

      if ($Qaction->fetch()) {
        $productId = $Qaction->valueInt('product_id');
        $type      = $Qaction->value('action_type');
        $logId     = $Qaction->valueInt('log_id');

        switch ($type) {
          case 'specials':
            $this->db->save(':table_specials',
              ['status' => 0, 'specials_last_modified' => 'now()'],
              ['products_id' => $productId]
            );
            break;
          case 'featured':
            $this->db->save(':table_products_featured',
              ['status' => 0, 'products_featured_date_modified' => 'now()'],
              ['products_id' => $productId]
            );
            break;
          case 'favorites':
            $this->db->save(':table_products_favorites',
              ['status' => 0, 'products_favorites_date_modified' => 'now()'],
              ['products_id' => $productId]
            );
            break;
        }

        $this->db->save(':table_products_cockpit_ai_action_log',
          ['status' => 'no_action', 'date_cancelled' => 'now()'],
          ['log_id' => $logId]
        );

        return ['success' => true, 'message' => 'Action revoked and product status restored.'];
      }

      return ['success' => false, 'message' => 'Invalid token or action already revoked.'];
    }
  }