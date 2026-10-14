<?php
  /**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

  namespace ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\CockpitAI\SubOrchestrator;

  /**
   * ReportBuilder
   *
   * Assembles the final Analysis_Report JSON structure and the embedding metadata
   * from the completed pipeline context.
   * (Requirements 18.1–18.8, 16.1–16.10, 11.1–11.5)
   *
   * Extracted from CockpitAIOrchestrator to keep assembly logic independently
   * testable and the orchestrator focused on sequencing.
   *
   * ── Analysis_Report structure (Req. 18) ─────────────────────────────────────
   *
   * {
   *   "header"           : product_id, language_id, analysis_date, pipeline_duration_ms
   *   "score_x"          : value, quadrant, factors_x
   *   "score_y"          : value, factors_y
   *   "quadrant"         : code, label, strategy
   *   "analysis"         : text, fallback_used
   *   "action_plan"      : actions[], total_rules_triggered, conflicts_resolved
   *   "history"          : RAG context (top-3 embeddings)
   *   "inventory_metrics": stock_velocity, demand_mean, demand_stddev, forecast_30d,
   *                        stockout_probability (%), safety_stock  [OPTIONAL — omitted when unavailable]
   *   "technical"        : steps_completed, steps_failed, embedding_id,
   *                        embedding_format_version, pipeline_duration_ms
   * }
   *
   * ── Embedding metadata structure (Req. 16) ──────────────────────────────────
   *
   * {
   *   "version", "schema", "embedding_format_version",
   *   "scores"            : { score_x, score_y, quadrant },
   *   "seo"               : { status, score },
   *   "commercial_metrics": { views_30d, orders, conversion_rate, returns },
   *   "feature_flags"     : { promo_active, feature, reviews, recommendations },
   *   "strategy"          : { strategy_x, strategy_y },
   *   "actions"           : [...],
   *   "inventory_metrics" : { stock_velocity, demand_stats, demand_forecast_30d,
   *                           stockout_probability, safety_stock },  [raw values]
   *   "history"           : { analysis_number, previous_embedding_id, delta_x, delta_y, trend },
   *   "technical"         : { model_used, pipeline_duration_ms, timestamp }
   * }
   */
  class ReportBuilder
  {
    /**
     * Build the Analysis_Report returned to the caller (Hook / AJAX endpoint).
     *
     * Requirements 11.1–11.5: includes 'inventory_metrics' section only when
     * stock_velocity or stockout_probability is non-null in product data.
     *
     * @param array $context   Completed pipeline context
     * @return array           Analysis_Report array (JSON-serializable)
     */
    public function buildReport(array $context): array
    {
      $scores   = $context['scoring_calculation']    ?? [];
      $actions  = $context['rules_engine_execution'] ?? [];
      $analysis = $context['llm_analysis_generation'] ?? [];
      $product  = $context['product_data']           ?? [];
      $ragContext = $context['rag_context_retrieval'] ?? [];

      $duration = round((microtime(true) - ($context['start_time'] ?? microtime(true))) * 1000, 2);

      // --- SECTION HISTORY : Extraction par recherche récursive ---
      $historyProcessed = array_map(function ($ragItem) {
        $findLongestString = function($data) use (&$findLongestString) {
          if (is_string($data) && strlen($data) > 40) return $data;
          if (is_array($data)) {
            if (isset($data['analysis_text'])) return $data['analysis_text'];
            foreach ($data as $val) {
              $res = $findLongestString($val);
              if ($res) return $res;
            }
          }
          return null;
        };

        return $findLongestString($ragItem) ?: "";
      }, $ragContext);

      $historyProcessed = array_unique(array_filter($historyProcessed));

      $historyProcessed = array_values($historyProcessed);

      $report = [
        'header' => [
          'product_id'           => $context['product_id']  ?? null,
          'language_id'          => $context['language_id'] ?? null,
          'analysis_date'        => date('Y-m-d\TH:i:s\Z'),
          'seo_status'           => $product['seo_status'] ?? 'NOT_ANALYZED',
          'pipeline_duration_ms' => $duration,
        ],
        'score_x' => [
          'value'   => $scores['score_x']   ?? 0.0,
          'factors' => $scores['factors_x'] ?? [],
        ],
        'score_y' => [
          'value'   => $scores['score_y']   ?? 0.0,
          'factors' => $scores['factors_y'] ?? [],
        ],
        'quadrant' => [
          'code'     => $scores['quadrant'] ?? 'Q_intermediate',
          'label'    => $this->quadrantLabel($scores['quadrant'] ?? 'Q_intermediate'),
          'strategy' => $this->quadrantStrategy($scores['quadrant'] ?? 'Q_intermediate'),
        ],
        'analysis' => [
          'text'          => $analysis['analysis_text'] ?? '',
          'fallback_used' => (bool) ($analysis['metadata']['analysis']['fallback_used'] ?? $analysis['fallback'] ?? false),
        ],
        'action_plan' => [
          'actions'               => $this->serializeActions($actions),
          'total_rules_triggered' => count($actions),
          'conflicts_resolved'    => 0,
        ],
        'history' => $historyProcessed,
        'technical' => [
          'steps_executed'              => 8,
          'steps_completed'             => count($context['steps_completed']  ?? []),
          'pipeline_duration_ms'        => $duration,
        ],
      ];

      $inventoryMetrics = $this->buildInventoryMetricsSection($product);
      if ($inventoryMetrics !== null) {
        $report['inventory_metrics'] = $inventoryMetrics;
      }

      return $report;
    }

    /**
     * Human-readable label for each quadrant code.
     */
    private function quadrantLabel(string $code): string
    {
      return match ($code) {
        'Q1'             => 'Scaling',
        'Q2'             => 'Acquisition',
        'Q3'             => 'Rework / Kill',
        'Q4'             => 'Optimization',
        'Q_intermediate' => 'Monitoring',
        default          => 'Unknown',
      };
    }

    /**
     * Strategic recommendation sentence for each quadrant.
     */
    private function quadrantStrategy(string $code): string
    {
      return match ($code) {
        'Q1'             => 'Maintain and amplify.',
        'Q2'             => 'Improve visibility and commercial reach.',
        'Q3'             => 'Major rework required or consider removal.',
        'Q4'             => 'Improve product sheet quality to unlock sales potential.',
        'Q_intermediate' => 'Monitor and maintain — no urgent action required.',
        default          => '',
      };
    }

    /**
     * Serialize an array of Action objects (or already-serialized arrays) to plain arrays.
     */
    private function serializeActions(array $actions): array
    {
      return array_map(static function (mixed $action): array {
        if (is_array($action)) {
          return $action;
        }
        if (is_object($action) && method_exists($action, 'toArray')) {
          return $action->toArray();
        }
        return [];
      }, array_values($actions));
    }

    /**
     * Build the inventory_metrics section for the Analysis_Report.
     *
     * Returns null when neither stock_velocity nor stockout_probability is present,
     * so the section is omitted entirely from the report (Req. 11.3).
     *
     * Formatting (Requirements 11.4, 11.5):
     *   - stock_velocity        : float, 2 decimal places
     *   - demand_mean           : float, 2 decimal places
     *   - demand_stddev         : float, 2 decimal places
     *   - forecast_30d          : float, 2 decimal places (mean_total)
     *   - stockout_probability  : string percentage, 2 decimal places  (e.g. "15.00%")
     *   - safety_stock          : float, 2 decimal places
     *
     * @param array $product  Product data array from DataCollector
     * @return array|null     Formatted inventory metrics, or null if unavailable
     */
    private function buildInventoryMetricsSection(array $product): ?array
    {
      // Sentinel check: at least one primary velocity metric must be present
      $hasVelocity    = isset($product['stock_velocity'])       && $product['stock_velocity'] !== null;
      $hasStockout    = isset($product['stockout_probability']) && $product['stockout_probability'] !== null;

      if (!$hasVelocity && !$hasStockout) {
        return null;
      }

      return [
        // Req. 11.4 — velocity as float with 2 decimal places
        'stock_velocity' => $hasVelocity
          ? round((float) $product['stock_velocity'], 2)
          : null,

        // Demand statistics (mean and stddev, 2 decimal places)
        'demand_mean' => isset($product['demand_stats']['mean'])
          ? round((float) $product['demand_stats']['mean'], 2)
          : null,

        'demand_stddev' => isset($product['demand_stats']['stddev'])
          ? round((float) $product['demand_stats']['stddev'], 2)
          : null,

        // 30-day forecast total (2 decimal places)
        'forecast_30d' => isset($product['demand_forecast_30d']['mean_total'])
          ? round((float) $product['demand_forecast_30d']['mean_total'], 2)
          : null,

        // Req. 11.5 — stockout probability as percentage string with 2 decimal places
        'stockout_probability' => $hasStockout
          ? round((float) $product['stockout_probability'] * 100, 2) . '%'
          : null,

        // Safety stock (2 decimal places)
        'safety_stock' => isset($product['safety_stock']) && $product['safety_stock'] !== null
          ? round((float) $product['safety_stock'], 2)
          : null,
      ];
    }

    /**
     * Build the metadata array stored alongside the embedding (Step 8).
     *
     * Includes raw (unformatted) inventory_metrics for storage in the embedding,
     * so downstream components can re-use the data without parsing formatted strings.
     * (Requirements 11.1, 11.2, 16.4)
     *
     * @param array  $context                Completed pipeline context
     * @param string $embeddingFormatVersion Version string from EmbeddingService
     * @return array                         Metadata array (JSON-serializable)
     */
    public function buildMetadata(array $context, string $embeddingFormatVersion = '1.0'): array
    {
      $scores   = $context['scoring_calculation']     ?? [];
      $actions  = $context['rules_engine_execution']  ?? [];
      $analysis = $context['llm_analysis_generation'] ?? [];
      $product  = $context['product_data']            ?? [];
      $strategy = $context['strategy_preferences']    ?? [];
      $duration = round((microtime(true) - ($context['start_time'] ?? microtime(true))) * 1000, 2);
// Sécurisation : On cherche factors_x ou valid_factors_x, sinon tableau vide
      $factorsX = $scores['factors_x'] ?? $scores['valid_factors_x'] ?? [];
      $factorsY = $scores['factors_y'] ?? $scores['valid_factors_y'] ?? [];

      if (\defined('CLICSHOPPING_APP_ECOMMERCE_CAI_DEBUG') && CLICSHOPPING_APP_ECOMMERCE_CAI_DEBUG === 'True') {
        error_log('[ReportBuilder] buildMetadata - analysis data: ' . json_encode($analysis));
        error_log('[ReportBuilder] buildMetadata - analysis_text: ' . ($analysis['analysis_text'] ?? 'NOT SET'));
      }

      $metadata = [
        'version'                  => '1.0',
        'schema'                   => 'CockpitAI.product.analysis',
        'embedding_format_version' => $embeddingFormatVersion,

        'scores' => [
          'score_x'  => $scores['score_x']  ?? 0,
          'score_y'  => $scores['score_y']  ?? 0,
          'quadrant' => $scores['quadrant'] ?? 'Q_intermediate',
        ],

        'factors_x' => $factorsX,
        'factors_y' => $factorsY,

        'seo' => [
          'status' => $product['seo_status'] ?? 'NOT_ANALYZED',
          'score'  => $product['seo_score']  ?? null,
        ],

        'commercial_metrics' => [
          'views_30d'       => $product['views_30d']       ?? 0,
          'orders'          => $product['order_count']     ?? 0,
          'conversion_rate' => $product['conversion_rate'] ?? 0.0,
          'returns'         => $product['return_count']    ?? 0,
        ],

        'feature_flags' => [
          'promo_active'    => (bool) ($product['promo_active']          ?? false),
          'feature'         => (bool) ($product['feature']               ?? false),
          'reviews'         => (int)  ($product['review_count']          ?? 0),
          'recommendations' => (int)  ($product['recommendation_count']  ?? 0),
        ],

        'strategy' => [
          'strategy_x' => $strategy['axis_x'] ?? 'quality',
          'strategy_y' => $strategy['axis_y'] ?? 'performance',
        ],

        'actions' => $this->serializeActions($actions),

        'analysis' => [
          'text'          => $analysis['analysis_text'] ?? '',
          'fallback_used' => (bool) ($analysis['metadata']['analysis']['fallback_used'] ?? $analysis['fallback'] ?? false),
        ],

        'history' => [
          'analysis_number'       => null,
          'previous_embedding_id' => null,
          'delta_x'               => null,
          'delta_y'               => null,
          'trend'                 => null,
        ],

        'technical' => [
          'model_used'           => \defined('CLICSHOPPING_APP_CHATGPT_AI_MODEL') ? CLICSHOPPING_APP_CHATGPT_AI_MODEL
            : 'unknown',
          'pipeline_duration_ms' => $duration,
          'timestamp'            => date('Y-m-d\TH:i:s\Z'),
        ],
      ];

      // ── Inventory metrics in metadata — raw values, no formatting (Req. 11.1, 11.2) ──
      // Always included in metadata when any velocity key exists, so EmbeddingService
      // can store the full picture. Null values are preserved as-is.
      if ($this->productHasVelocityData($product)) {
        $metadata['inventory_metrics'] = [
          'stock_velocity'      => $product['stock_velocity']      ?? null,
          'demand_stats'        => $product['demand_stats']        ?? null,
          'demand_forecast_30d' => $product['demand_forecast_30d'] ?? null,
          'stockout_probability'=> $product['stockout_probability'] ?? null,
          'safety_stock'        => $product['safety_stock']        ?? null,
        ];
      }

      return $metadata;
    }

    /**
     * Determine whether the product data array contains any velocity metrics.
     *
     * Used internally to guard both the report section and the metadata section.
     * The presence of the key (even with null value) indicates that the DataCollector
     * attempted collection — but we only include the section when at least one
     * primary metric (velocity or stockout probability) is non-null.
     *
     * @param array $product Product data array
     * @return bool
     */
    private function productHasVelocityData(array $product): bool
    {
      return (isset($product['stock_velocity'])       && $product['stock_velocity'] !== null)
          || (isset($product['stockout_probability']) && $product['stockout_probability'] !== null);
    }
  }
