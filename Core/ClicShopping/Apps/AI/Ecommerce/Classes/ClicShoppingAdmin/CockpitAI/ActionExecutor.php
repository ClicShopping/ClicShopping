<?php
  /**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

  namespace ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\CockpitAI;

  use ClicShopping\OM\Registry;

  class ActionExecutor
  {
    private mixed $db;
    private mixed $validator;
    private bool $debug;

    public function __construct()
    {
      $this->db = Registry::get('Db');
      $this->validator = new ActionValidator();
      $this->debug = \defined('CLICSHOPPING_APP_ECOMMERCE_CAI_DEBUG') && CLICSHOPPING_APP_ECOMMERCE_CAI_DEBUG === 'True';
    }

    /**
     * Exécute le plan d'action et synchronise les métadonnées pour l'affichage
     */
    public function executePlan(int $productId, array $actions, array &$productData): array
    {
      $results = [];
      $hasChanged = false;

      foreach ($actions as $action) {
        $code = strtoupper($action['code']);
        $params = $action['metadata'] ?? [];

        if ($this->debug) {
          error_log("[CockpitAI ExecutePlan] ---- Action: $code | product=$productId");
        }

        // 1. Validation de l'action via ActionValidator
        $decision = $this->validator->validateAction($code, $productId, $productData, $params);
        $status = $decision['status'] ?? 'SKIP';

        if ($this->debug) {
          error_log("[CockpitAI ExecutePlan] Validator returned status='$status' reason='" . ($decision['reason'] ?? 'none') . "'");
        }

        // 2. Gestion des actions déjà existantes (Nettoyage des recommandations)
        if ($status === 'SKIP' && ($decision['reason'] ?? '') === 'Already exists') {
          $this->logAction($productId, $decision, $productData, 'skipped', $params, $code);
          $this->markActionAsProcessed($productData, $action['code']);
          continue;
        }

        if ($status === 'SKIP') {
          $this->logAction($productId, $decision, $productData, 'skipped', $params, $code);
          continue;
        }

        $tableName = $this->getTableName($code);

        if ($this->debug) {
          error_log("[CockpitAI ExecutePlan] Table resolved: $tableName");
        }

        // 3. Exécution des modifications en base de données
        switch ($status) {
          case 'ADD':
          case 'UPDATE':
            if ($this->debug) {
              error_log("[CockpitAI ExecutePlan] -> $status : applying action on $tableName");
            }


            // Pour specials : on delete avant re-insert seulement si une ligne existe déjà
            // (évite de supprimer une promo créée dans le même run par une autre action)
            // Pour featured/favorites : delete+insert est toujours safe (pas de conflit)
            if ($tableName === ':table_specials' && $status === 'ADD') {
              // Vérifier qu'il n'y a pas déjà une promo active avant de supprimer
              $Qcheck = $this->db->prepare('SELECT COUNT(*) as cnt 
                                            FROM :table_specials 
                                            WHERE products_id = :pid 
                                            AND status = 1
                                            ');
              $Qcheck->bindInt(':pid', $productId);
              $Qcheck->execute();
	      
              if ($Qcheck->valueInt('cnt') > 0) {
                $this->db->delete($tableName, ['products_id' => $productId]);
              }
            } else {
              $this->db->delete($tableName, ['products_id' => $productId]);
            }

            $this->applyAction($tableName, $productId, array_merge($params, $decision));

            if ($this->debug) {
              error_log("[CockpitAI ExecutePlan] -> $status : applyAction done for product=$productId table=$tableName");
            }

            $triggerStrategy = $decision['trigger_strategy'] ?? 'standard';
            $this->logAction($productId, $decision, $productData, strtolower($status), $params, $code, $triggerStrategy);
            $results[] = ['action' => $code, 'status' => 'SUCCESS'];
            $this->markActionAsProcessed($productData, $action['code']);
            $this->updateLocalFlags($productData, $code);
            $hasChanged = true;
            break;

          case 'REMOVE':
            if ($this->debug) {
              error_log("[CockpitAI ExecutePlan] -> REMOVE : looking for revocation token");
            }

            $revoked = false;
            $revocationToken = $this->getRevocationToken($productId, $code);

            if ($this->debug) {
              error_log("[CockpitAI ExecutePlan] -> REMOVE : token=" . ($revocationToken ?? 'null'));
            }

            if ($revocationToken !== null) {
              $revocation = new CockpitAIRevocation();
              $revokeResult = $revocation->revoke($revocationToken);
              $revoked = $revokeResult['success'] ?? false;
	      
              if ($this->debug) {
                error_log("[CockpitAI ExecutePlan] -> REMOVE : revocation result=" . ($revoked ? 'OK' : 'FAILED') . " msg=" . ($revokeResult['message'] ?? ''));
              }
            }

            if (!$revoked) {
              if ($this->debug) {
                error_log("[CockpitAI ExecutePlan] -> REMOVE : fallback db->delete on $tableName for product=$productId");
              }

              $this->db->delete($tableName, ['products_id' => $productId]);
            }

            $this->logAction($productId, $decision, $productData, 'removed', $params, $code);
            $results[] = ['action' => $code, 'status' => 'REMOVED'];
            $this->markActionAsProcessed($productData, $action['code']);
            $hasChanged = true;
            break;

          default:
            if ($this->debug) {
              error_log("[CockpitAI ExecutePlan] -> DEFAULT (unexpected status='$status') for action=$code");
            }

            $this->logAction($productId, $decision, $productData, 'skipped', $params, $code);
            break;
        }
      }

      error_log("[CockpitAI ExecutePlan] Loop done | hasChanged=" . ($hasChanged ? 'true' : 'false') . " | results=" . json_encode($results));

      // 4. Actualisation finale du score et du cache si des actions ont été menées
      if ($hasChanged) {
        if ($this->debug) {
          error_log("[CockpitAI ExecutePlan] Calling updateAnalysisScore for product=$productId");
        }

        $this->updateAnalysisScore($productId, $productData);
      }

      return $results;
    }

    /**
     * Supprime l'action de la liste des recommandations affichées à l'admin
     */
    private function markActionAsProcessed(array &$productData, string $actionCode): void
    {
      if (isset($productData['metadata']['actions'])) {
        foreach ($productData['metadata']['actions'] as $key => $action) {
          if (strtoupper($action['code'] ?? '') === strtoupper($actionCode)) {
            unset($productData['metadata']['actions'][$key]);
          }
        }

        $productData['metadata']['actions'] = array_values($productData['metadata']['actions']);
      }
    }

    private function getTableName(string $code): string {
      if (str_contains($code, 'FEATURED')) return ':table_products_featured';
      if (str_contains($code, 'FAVORITE')) return ':table_products_favorites';
      return ':table_specials';
    }

    private function applyAction(string $table, int $productId, array $params): void {
      $data = ['products_id' => (int)$productId, 'status' => 1];

      if ($table === ':table_specials') {
        if (!isset($params['new_price'])) {
          $Qprice = $this->db->prepare('SELECT products_price 
                                        FROM :table_products 
                                        WHERE products_id = :id
                                        ');
          $Qprice->bindInt(':id', $productId);
          $Qprice->execute();

          $basePrice = (float)$Qprice->valueDecimal('products_price');
          $rate = $params['new_rate'] ?? 5;
          $params['new_price'] = $basePrice * (1 - ($rate / 100));
        }

        $data['specials_new_products_price'] = (float)$params['new_price'];
        $data['specials_date_added'] = 'now()';
        $data['scheduled_date'] = 'now()';
      } else {
        // Correction dynamique du nom de colonne : products_featured_date_added etc.
        $columnName = str_replace(':table_', '', $table) . '_date_added';
        $data[$columnName] = 'now()';
      }
      
      if ($this->debug) {
        error_log("[CockpitAI applyAction] db->save on table=$table | data=" . json_encode($data));
      }

      $this->db->save($table, $data);

      if ($this->debug) {
        error_log("[CockpitAI applyAction] db->save done");
      }
    }

    /**
     * Met à jour les flags locaux pour éviter que l'IA ne tente
     * de re-valider une action qu'on vient de faire dans la même boucle
     */
    private function updateLocalFlags(array &$productData, string $actionCode): void
    {
      if (str_contains($actionCode, 'FAVORITE')) {
        $productData['metadata']['feature_flags']['favorites'] = true;
      }
      if (str_contains($actionCode, 'FEATURE')) {
        $productData['metadata']['feature_flags']['feature'] = true;
      }

      if (str_contains($actionCode, 'PROMOTION') || str_contains($actionCode, 'DISCOUNT')) {
        $productData['metadata']['feature_flags']['promo_active'] = true;
        $productData['specials_active'] = true;
        $productData['promo_active']    = true;
      }
    }

    /**
     * Recherche le token de révocation le plus récent pour un produit et un type d'action.
     * Retourne null si aucun log SUCCESS trouvé (fallback sur delete direct).
     */
    private function getRevocationToken(int $productId, string $actionCode): ?string
    {
      // Mapping code action → action_type ENUM ('featured','favorites','specials')
      $typeMap = [
        'FEATURED'  => 'featured',
        'FAVORITE'  => 'favorites',
        'PROMOTION' => 'specials',
        'DISCOUNT'  => 'specials',
      ];

      $actionType = null;

      foreach ($typeMap as $keyword => $type) {
        if (str_contains($actionCode, $keyword)) {
          $actionType = $type;
          break;
        }
      }

      if ($actionType === null) return null;

      try {
        $Q = $this->db->prepare('SELECT revocation_token
                                 FROM :table_products_cockpit_ai_action_log
                                 WHERE product_id = :pid
                                 AND action_type = :type
                                 AND status = "executed"
                                 AND revocation_token IS NOT NULL
                                 ORDER BY date_created DESC
                                 LIMIT 1');
        $Q->bindInt(':pid', $productId);
        $Q->bindValue(':type', $actionType);
        $Q->execute();

        if ($Q->fetch()) {
          return $Q->value('revocation_token') ?: null;
        }
      } catch (\Exception $e) {
        if ($this->debug) {
          error_log("[CockpitAI] getRevocationToken error: " . $e->getMessage());
        }
      }

      return null;
    }

    private function logAction(int $productId, array $validation, array $ctx, string $status, array $params = [], string $actionCode = '', string $triggerStrategy = 'standard'): void
    {
      try {
        // Mapping code → action_type ENUM ('featured','favorites','specials')
        $actionType = null;
        if (str_contains($actionCode, 'FEATURED'))                                               $actionType = 'featured';
        elseif (str_contains($actionCode, 'FAVORITE'))                                           $actionType = 'favorites';
        elseif (str_contains($actionCode, 'PROMOTION') || str_contains($actionCode, 'DISCOUNT')) $actionType = 'specials';

        if ($actionType === null) {
          if ($this->debug) {
            error_log("[CockpitAI logAction] Skipping log (unmapped action_type) for action=$actionCode");
          }

          return;
        }

        // Mapping status → ENUM ('executed','skipped','pending_admin','no_action','failed')
        $statusMap = [
          'add'     => 'executed',
          'update'  => 'executed',
          'removed' => 'executed',
          'skipped' => 'skipped',
          'failed'  => 'failed',
        ];
        $dbStatus = $statusMap[strtolower($status)] ?? 'no_action';

        if ($this->debug) {
          error_log("[CockpitAI LogAction] -> $dbStatus : deleting existing row then applying action statusMap");
        }

        $subtypeMap = [
          'add'     => 'insert',
          'update'  => 'update',
          'removed' => 'delete',
          'skipped' => 'insert',
        ];
        $actionSubtype = $subtypeMap[strtolower($status)] ?? 'insert';

        if ($this->debug) {
          error_log("[CockpitAI LogAction] -> $actionSubtype : deleting existing row then applying action subtypeMap");
        }

        $sql_array = [
          'product_id'          => (int)$productId,
          'action_type'         => $actionType,
          'action_subtype'      => $actionSubtype,
          'language_id'         => (int)($ctx['language_id'] ?? 1),
          'status'              => $dbStatus,
          'triggered_by'        => (php_sapi_name() == 'cli') ? 'cron' : 'admin',
          'score_x_at_trigger'  => (float)($ctx['metadata']['scores']['score_x'] ?? 0),
          'score_y_at_trigger'  => (float)($ctx['metadata']['scores']['score_y'] ?? 0),
          'quadrant_at_trigger' => (string)($ctx['metadata']['scores']['quadrant'] ?? 'unknown'),
          'validation_reason'   => $validation['reason'] ?? null,
          'date_created'        => 'now()'
        ];

        // Colonnes optionnelles ajoutées en migration — ajoutées si la valeur est pertinente
        // Si la colonne n'existe pas encore en BD, le save() échouera proprement
        // et l'exception sera catchée sans bloquer le flux principal.
        if (!empty($actionCode)) {
          $sql_array['action_code'] = $actionCode;
        }
        if (!empty($triggerStrategy) && $triggerStrategy !== 'standard') {
          $sql_array['trigger_strategy'] = $triggerStrategy;
        }

        if ($this->debug) {
          error_log("[CockpitAI logAction] Saving | action=$actionCode type=$actionType status=$dbStatus strategy=$triggerStrategy product=$productId");
        }

        $this->db->save(':table_products_cockpit_ai_action_log', $sql_array);

        if ($this->debug) {
          error_log("[CockpitAI logAction] Save done");
        }
      } catch (\Exception $e) {
        error_log("[CockpitAI Log Error] " . $e->getMessage());
      }
    }

    /**
     * Met à jour uniquement les feature_flags dans l'embedding existant
     * après exécution des actions, sans toucher aux scores ni au texte LLM.
     *
     * Stratégie : on relit le metadata complet depuis la BD, on met à jour
     * seulement les flags qui ont réellement changé (promo, featured, favorites),
     * puis on réécrit le tout. Les scores et l'analyse LLM restent intacts.
     */
    private function updateAnalysisScore(int $productId, array &$productData): void
    {
      try {
        // 1. Relire le metadata complet depuis la BD (source de vérité)
        $Qmeta = $this->db->prepare('SELECT metadata
                                     FROM :table_products_cockpit_ai_embedding 
                                     WHERE entity_id = :entity_id
                                     ORDER BY date_modified DESC
                                     LIMIT 1
                                     ');
        $Qmeta->bindInt(':entity_id', $productId);
        $Qmeta->execute();

        if (!$Qmeta->fetch()) {
          if ($this->debug) {
            error_log("[CockpitAI] updateAnalysisScore: no embedding found for product=$productId, skipping update");
          }

          return;
        }

        $storedMetadata = json_decode($Qmeta->value('metadata'), true);

        if (!is_array($storedMetadata)) {
          if ($this->debug) {
            error_log("[CockpitAI] updateAnalysisScore: invalid metadata JSON for product=$productId");
          }

          return;
        }

        // 2. Mettre à jour uniquement les feature_flags depuis $productData
        // (ces flags ont été mis à jour par updateLocalFlags() durant la boucle)
        $localFlags = $productData['metadata']['feature_flags'] ?? [];

        if (isset($localFlags['favorites'])) {
          $storedMetadata['feature_flags']['favorites'] = (bool)$localFlags['favorites'];
        }
        if (isset($localFlags['feature'])) {
          $storedMetadata['feature_flags']['feature'] = (bool)$localFlags['feature'];
        }

        // promo_active : on relit depuis $productData (mis à jour par DataCollector au début du run)
        $promoActive = (bool)($productData['specials_active'] ?? $productData['promo_active'] ?? false);
        $storedMetadata['feature_flags']['promo_active'] = $promoActive;

        if ($this->debug) {
          error_log("[CockpitAI] updateAnalysisScore: updating flags for product=$productId"
            . " favorites=" . ($storedMetadata['feature_flags']['favorites'] ? 'true' : 'false')
            . " feature=" . ($storedMetadata['feature_flags']['feature'] ? 'true' : 'false')
            . " promo_active=" . ($promoActive ? 'true' : 'false'));
        }

        // 3. Réécrire le metadata complet (scores + analyse LLM intacts)
        $this->db->save(':table_products_cockpit_ai_embedding ', [
          'metadata'      => json_encode($storedMetadata, JSON_UNESCAPED_UNICODE),
          'date_modified' => 'now()'
        ], [
          'entity_id' => (int)$productId
        ]);

      } catch (\Exception $e) {
        error_log("[CockpitAI] updateAnalysisScore error for product=$productId : " . $e->getMessage());
      }

      // 4. Vider le cache pour que l'UI recharge depuis la BD mise à jour
      if (Registry::exists('Cache')) {
        Registry::get('Cache')->clear('cockpit_ai_analysis_' . $productId);
      }
    }
  }