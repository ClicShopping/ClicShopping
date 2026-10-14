<?php
  /**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

  namespace ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\CockpitAI;

  use ClicShopping\OM\Registry;

  /**
   * FeedbackCollector
   *
   * Job cron qui mesure l'impact réel de chaque action AutoPilot
   * N jours après son exécution, en comparant le Score Y avant et après.
   *
   * Flux :
   *   1. Sélectionne les actions 'executed' sans score_y_after
   *      dont date_created >= N jours
   *   2. Pour chaque action, relit le Score Y actuel depuis products_cockpit_ai_embedding 
   *   3. Calcule le delta : score_y_after - score_y_at_trigger
   *   4. Écrit score_y_after + feedback_collected_at dans products_cockpit_ai_action_log
   *
   * Appel cron recommandé : 1x par jour, heure creuse
   *   php cron/cockpit_ai_feedback.php
   *
   * Garde-fous :
   *   - N jours configurables (défaut : 7)
   *   - Traitement par batch de 50 actions max par run
   *   - Ne modifie jamais les scores en base, lecture seule sur embedding
   */
  class FeedbackCollector
  {
    private mixed $db;
    private bool $debug;

    /** Nombre de jours à attendre avant de mesurer l'impact */
    private int $feedbackDelayDays;

    /** Nombre max d'actions traitées par run cron */
    private int $batchSize;

    public function __construct()
    {
      $this->db = Registry::get('Db');
      $this->debug = \defined('CLICSHOPPING_APP_ECOMMERCE_CAI_DEBUG') && CLICSHOPPING_APP_ECOMMERCE_CAI_DEBUG === 'True';

      $this->feedbackDelayDays = \defined('CLICSHOPPING_APP_ECOMMERCE_CAI_FEEDBACK_DELAY_DAYS')
        ? (int)CLICSHOPPING_APP_ECOMMERCE_CAI_FEEDBACK_DELAY_DAYS
        : 7;

      $this->batchSize = 50;
    }

    /**
     * Point d'entrée principal — appelé par le cron.
     *
     * @return array Résumé du run : ['processed' => int, 'skipped' => int, 'errors' => int]
     */
    public function run(): array
    {
      $stats = ['processed' => 0, 'skipped' => 0, 'errors' => 0];

      if ($this->debug) {
        error_log("[FeedbackCollector] Run started | delay={$this->feedbackDelayDays}d | batch={$this->batchSize}");
      }

      // 1. Récupérer les actions éligibles au feedback
      $actions = $this->fetchEligibleActions();

      if (empty($actions)) {
        if ($this->debug) {
          error_log("[FeedbackCollector] No eligible actions found");
        }
        return $stats;
      }

      foreach ($actions as $action) {
        try {
          $result = $this->processAction($action);

          if ($result === true) {
            $stats['processed']++;
          } else {
            $stats['skipped']++;
          }
        } catch (\Throwable $e) {
          $stats['errors']++;
          error_log("[FeedbackCollector] Error on log_id={$action['log_id']}: " . $e->getMessage());
        }
      }

      if ($this->debug) {
        error_log("[FeedbackCollector] Run done | " . json_encode($stats));
      }

      return $stats;
    }

    /**
     * Récupère les actions exécutées il y a >= N jours sans feedback collecté.
     * Inclut trigger_strategy pour segmenter flash vs standard dans les stats.
     */
    private function fetchEligibleActions(): array
    {
      $Q = $this->db->prepare('SELECT log_id,
                                      product_id,
                                      language_id,
                                      action_type,
                                      action_code,
                                      trigger_strategy,
                                      score_y_at_trigger,
                                      date_created
                               FROM :table_products_cockpit_ai_action_log
                               WHERE status = "executed"
                               AND feedback_collected_at IS NULL
                               AND score_y_at_trigger IS NOT NULL
                               AND date_created <= DATE_SUB(NOW(), INTERVAL :delay DAY)
                               ORDER BY date_created ASC
                               LIMIT :batch');

      $Q->bindInt(':delay', $this->feedbackDelayDays);
      $Q->bindInt(':batch', $this->batchSize);
      $Q->execute();

      $actions = [];
      while ($row = $Q->fetch()) {
        $actions[] = $row;
      }

      return $actions;
    }

    /**
     * Traite une action : relit le Score Y actuel, calcule delta et conversion_velocity.
     *
     * conversion_velocity = ventes réalisées depuis l'action / jours écoulés
     * Permet à RuleAdjuster de comparer l'efficacité flash vs standard.
     *
     * @return bool true si feedback enregistré, false si embedding introuvable
     */
    private function processAction(array $action): bool
    {
      $productId    = (int)$action['product_id'];
      $languageId   = (int)($action['language_id'] ?? 1);
      $logId        = (int)$action['log_id'];
      $scoreYBefore = (float)$action['score_y_at_trigger'];
      $dateCreated  = $action['date_created'];

      // Lire le Score Y actuel depuis l'embedding
      $currentScoreY = $this->readCurrentScoreY($productId, $languageId);

      if ($currentScoreY === null) {
        if ($this->debug) {
          error_log("[FeedbackCollector] No embedding found for product=$productId, skipping log_id=$logId");
        }
        return false;
      }

      $deltaY = round($currentScoreY - $scoreYBefore, 2);

      // Calcul de conversion_velocity : ventes / jours depuis l'action
      $conversionVelocity = $this->computeConversionVelocity($productId, $dateCreated);

      if ($this->debug) {
        $strategy = $action['trigger_strategy'] ?? 'standard';
        error_log("[FeedbackCollector] log_id=$logId product=$productId strategy=$strategy"
          . " score_y_before=$scoreYBefore score_y_after=$currentScoreY delta=$deltaY"
          . " velocity=$conversionVelocity action={$action['action_code']}");
      }

      // Écrire le feedback dans la table de log
      $this->db->save(':table_products_cockpit_ai_action_log', [
        'score_y_after'         => $currentScoreY,
        'conversion_velocity'   => $conversionVelocity,
        'feedback_collected_at' => 'now()'
      ], [
        'log_id' => $logId
      ]);

      return true;
    }

    /**
     * Calcule la vélocité de conversion depuis une date donnée.
     * conversion_velocity = nb_ventes / jours_écoulés
     *
     * @param int    $productId   Produit à mesurer
     * @param string $sinceDate   Date de départ (date_created de l'action)
     * @return float              Ventes par jour [0..∞]
     */
    private function computeConversionVelocity(int $productId, string $sinceDate): float
    {
      try {
        $Qsales = $this->db->prepare('SELECT COUNT(*) as sales_count
                                      FROM :table_orders_products op
                                      INNER JOIN :table_orders o ON op.orders_id = o.orders_id
                                      WHERE op.products_id = :pid
                                      AND o.date_purchased >= :since
                                      AND o.orders_status >= 3');
        $Qsales->bindInt(':pid', $productId);
        $Qsales->bindValue(':since', $sinceDate);
        $Qsales->execute();

        $salesCount  = (int)$Qsales->valueInt('sales_count');
        $daysElapsed = max(1, (time() - strtotime($sinceDate)) / 86400);

        return round($salesCount / $daysElapsed, 4);

      } catch (\Throwable $e) {
        if ($this->debug) {
          error_log("[FeedbackCollector] computeConversionVelocity error: " . $e->getMessage());
        }
        return 0.0;
      }
    }

    /**
     * Relit le Score Y le plus récent depuis products_cockpit_ai_embedding .
     *
     * @return float|null Score Y actuel, ou null si aucun embedding trouvé
     */
    private function readCurrentScoreY(int $productId, int $languageId): ?float
    {
      $Q = $this->db->prepare('SELECT metadata
                               FROM :table_products_cockpit_ai_embedding 
                               WHERE entity_id = :entity_id
                               AND language_id = :language_id
                               ORDER BY date_modified DESC
                               LIMIT 1');

      $Q->bindInt(':entity_id', $productId);
      $Q->bindInt(':language_id', $languageId);
      $Q->execute();

      if (!$Q->fetch()) {
        return null;
      }

      $meta = json_decode($Q->value('metadata'), true);

      if (!is_array($meta) || !isset($meta['scores']['score_y'])) {
        return null;
      }

      return (float)$meta['scores']['score_y'];
    }

    /**
     * Retourne les statistiques de feedback pour un type d'action donné.
     * Utilisé par RuleAdjuster pour décider si un ajustement est justifié.
     *
     * @param string $actionType  'specials' | 'featured' | 'favorites'
     * @param int    $minSamples  Nombre minimum de cycles pour être significatif
     * @return array|null ['sample_size', 'avg_delta_y', 'positive_rate'] ou null si insuffisant
     */
    public function getActionStats(string $actionType, int $minSamples = 30): ?array
    {
      $Q = $this->db->prepare('SELECT COUNT(*) as sample_size,
                                      AVG(score_y_after - score_y_at_trigger) as avg_delta_y,
                                      SUM(CASE WHEN score_y_after > score_y_at_trigger THEN 1 ELSE 0 END) as positive_count
                               FROM :table_products_cockpit_ai_action_log
                               WHERE action_type = :action_type
                               AND status = "executed"
                               AND feedback_collected_at IS NOT NULL
                               AND score_y_after IS NOT NULL
                               AND score_y_at_trigger IS NOT NULL');

      $Q->bindValue(':action_type', $actionType);
      $Q->execute();

      if (!$Q->fetch()) {
        return null;
      }

      $sampleSize = (int)$Q->valueInt('sample_size');

      if ($sampleSize < $minSamples) {
        if ($this->debug) {
          error_log("[FeedbackCollector] getActionStats: insufficient samples for action_type=$actionType ($sampleSize < $minSamples)");
        }
        return null;
      }

      $avgDeltaY     = (float)$Q->valueDecimal('avg_delta_y');
      $positiveCount = (int)$Q->valueInt('positive_count');
      $positiveRate  = $sampleSize > 0 ? round($positiveCount / $sampleSize * 100, 1) : 0;

      return [
        'action_type'   => $actionType,
        'sample_size'   => $sampleSize,
        'avg_delta_y'   => round($avgDeltaY, 2),
        'positive_rate' => $positiveRate,
      ];
    }

    /**
     * Retourne les statistiques segmentées par trigger_strategy.
     * Permet à RuleAdjuster de comparer flash vs standard et d'ajuster
     * le seuil high_intent_threshold en conséquence.
     *
     * @param string $actionType 'specials' | 'featured' | 'favorites'
     * @param int    $minSamples Volume minimum par groupe pour être significatif
     * @return array{
     *   flash: array|null,    — stats du groupe 'high_intent_flash'
     *   standard: array|null  — stats du groupe 'standard'
     * }
     */
    public function getActionStatsByStrategy(string $actionType, int $minSamples = 20): array
    {
      $result = ['flash' => null, 'standard' => null];

      foreach (['high_intent_flash' => 'flash', 'standard' => 'standard'] as $strategy => $key) {
        try {
          $Q = $this->db->prepare('SELECT COUNT(*) as sample_size,
                                          AVG(score_y_after - score_y_at_trigger) as avg_delta_y,
                                          AVG(conversion_velocity) as avg_velocity,
                                          SUM(CASE WHEN score_y_after > score_y_at_trigger THEN 1 ELSE 0 END) as positive_count
                                   FROM :table_products_cockpit_ai_action_log
                                   WHERE action_type = :action_type
                                   AND trigger_strategy = :strategy
                                   AND status = "executed"
                                   AND feedback_collected_at IS NOT NULL
                                   AND score_y_after IS NOT NULL
                                   AND score_y_at_trigger IS NOT NULL');

          $Q->bindValue(':action_type', $actionType);
          $Q->bindValue(':strategy', $strategy);
          $Q->execute();

          if (!$Q->fetch()) continue;

          $sampleSize = (int)$Q->valueInt('sample_size');

          if ($sampleSize < $minSamples) {
            if ($this->debug) {
              error_log("[FeedbackCollector] getActionStatsByStrategy: insufficient samples for strategy=$strategy ($sampleSize < $minSamples)");
            }
            continue;
          }

          $avgDeltaY     = (float)$Q->valueDecimal('avg_delta_y');
          $avgVelocity   = (float)$Q->valueDecimal('avg_velocity');
          $positiveCount = (int)$Q->valueInt('positive_count');
          $positiveRate  = $sampleSize > 0 ? round($positiveCount / $sampleSize * 100, 1) : 0;

          $result[$key] = [
            'strategy'      => $strategy,
            'sample_size'   => $sampleSize,
            'avg_delta_y'   => round($avgDeltaY, 2),
            'avg_velocity'  => round($avgVelocity, 4),
            'positive_rate' => $positiveRate,
          ];

        } catch (\Throwable $e) {
          error_log("[FeedbackCollector] getActionStatsByStrategy error strategy=$strategy: " . $e->getMessage());
        }
      }

      return $result;
    }
  }
