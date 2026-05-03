<?php
/**
 *
 * @copyright 2008 - https://www.clicshopping.org
 * @Brand : ClicShoppingAI(TM) at Inpi all right Reserved
 * @Licence GPL 2 & MIT
 * @Info : https://www.clicshopping.org/forum/trademark/
 *
 */

namespace ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\CockpitAI\SubOrchestrator;

use ClicShopping\Apps\AI\Ecommerce\Ecommerce as EcommerceApp;
use ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\Gpt;
use ClicShopping\OM\Registry;

/**
 * LlmAnalysisGenerator
 *
 * Responsible for Step 6: LLM Analysis Generation
 * (Requirements 10.6, 12.1-12.8)
 *
 * This component constructs a structured prompt from:
 * - Score_X and Score_Y with valid factors only
 * - Quadrant classification and strategy
 * - RAG context (content field only, Top-3 historical analyses)
 * - Pre-calculated action plan from Rules Engine
 * - Historical comparison metrics (delta_x, delta_y, trend)
 * - Inventory metrics when available (stock velocity, stockout risk, safety stock)
 *
 * Then invokes the LLM via Gpt::class to generate a natural language analysis.
 *
 * Timeout: 5s (handled by PipelineRunner)
 * Fallback: Generic analysis text if LLM fails
 */
class LlmAnalysisGenerator
{
  private const VELOCITY_THRESHOLD = 2.0;
  private bool $debug;

  // Velocity threshold: products selling ≥ 2× their stock in 90 days = fast-moving
  private mixed $app;

  public function __construct()
  {
    $this->debug = \defined('CLICSHOPPING_APP_ECOMMERCE_CAI_DEBUG') && CLICSHOPPING_APP_ECOMMERCE_CAI_DEBUG === 'True';

    if (!Registry::exists('Ecommerce')) {
      Registry::set('Ecommerce', new EcommerceApp());
    }

    $this->app = Registry::get('Ecommerce');

    $this->app->loadDefinitions('Sites/ClicShoppingAdmin/llm_analysis_generator');
  }

  /**
   * Generate LLM analysis from pipeline context
   *
   * @param array $context Pipeline context with scores, RAG, actions
   * @return array ['analysis_text' => string, 'historical_comparison' => string, 'fallback' => bool]
   */
  /**
   * Executer l'analyse LLM (Etape 6 de la pipeline)
   * Modifié pour gérer le cache intelligent et le versioning.
   */
  public function generate(array $context): array
  {
    $productId = (int)($context['product_id'] ?? 0);
    $languageId = (int)($context['language_id'] ?? 1);

    $embeddingService = Registry::get('EmbeddingService');
    $forceRefresh = isset($context['force_refresh']) && $context['force_refresh'] === true;

    // Si on ne force pas le refresh, on vérifie si une analyse récente existe
    if (!$forceRefresh) {
      $existingAnalysis = $embeddingService->getLatestEmbedding($productId, $languageId);

      if (!empty($existingAnalysis)) {
        if ($this->debug) {
          error_log("[CockpitAI] Cache Hit: Utilisation de l'analyse existante pour le produit $productId");
        }
        return [
          'analysis_text' => $existingAnalysis['analysis_text'],
          'metadata' => $existingAnalysis['metadata'],
          'source' => 'cache'
        ];
      }
    }

    if ($this->debug) {
      error_log("[CockpitAI] Generation LLM: " . ($forceRefresh ? "Rafraîchissement forcé" : "Nouvelle analyse"));
    }

    // Construction du prompt (ton code actuel)
    $prompt = $this->buildPrompt($context);

    try {
      $analysisText = Gpt::getGptResponse($prompt);

      if (empty($analysisText)) {
        throw new \Exception("LLM returned empty response");
      }

      $flags = $context['scoring_calculation']['feature_flags'] ?? [];

      return [
        'analysis_text' => $analysisText,
        'metadata' => [
          'scores' => [
            'score_x' => $context['scoring_calculation']['score_x'] ?? 0,
            'score_y' => $context['scoring_calculation']['score_y'] ?? 0,
            'quadrant' => $context['scoring_calculation']['quadrant'] ?? 'unknown',
          ],
          'feature_flags' => [
            'promo_active' => $flags['promo_active'] ?? false,
            'feature'      => $flags['feature'] ?? $flags['featured'] ?? false,
            'favorites'    => $flags['favorites'] ?? false,
          ],
          'analysis' => [
            'text' => $analysisText,
            'fallback_used' => false  // Valeur explicitement false : cette branche est le succès LLM
          ],
          'product_name' => $context['product_name'] ?? '',
          'entity_id'    => $productId
        ],
        'source' => 'llm_generation'
      ];

    } catch (\Exception $e) {
      error_log("[CockpitAI LlmAnalysis] Failure: " . $e->getMessage());

      // Le PipelineRunner appellera le fallback si nous retournons une erreur ou un format spécifique
      return [
        'analysis_text' => "Analyse temporairement indisponible.",
        'metadata' => [],
        'source' => 'fallback_error'
      ];
    }
  }

  /**
   * Build structured prompt for LLM
   *
   * Requirements 12.1-12.8:
   * - Score_X, Score_Y, quadrant
   * - Valid factors only (exclude missing and not_analyzed)
   * - RAG context (content field only)
   * - Strategy preferences
   * - Pre-calculated actions
   * - Historical comparison
   * - Inventory metrics section injected after STRATEGIC CLASSIFICATION
   *   when velocity data is available (Req. 12.1-12.5)
   *
   * @param array $context Pipeline context
   * @return string Structured prompt
   */
  public function buildPrompt(array $context): string
  {
    $scores     = $context['scoring_calculation']    ?? [];
    $product    = $context['product_data']            ?? [];
    $ragContext = $context['rag_context_retrieval']  ?? [];
    $actions    = $context['rules_engine_execution'] ?? [];
    $strategies = $context['strategy_preferences']   ?? [];
    $flags      = $scores['feature_flags']           ?? [];

    $scoreX        = $scores['score_x'] ?? 0;
    $scoreY        = $scores['score_y'] ?? 0;
    $quadrant      = $scores['quadrant'] ?? 'Q_intermediate';
    $quadrantLabel = $this->getQuadrantLabel($quadrant);

    // Normalization confidence flag
    $normFallback = (bool) ($scores['normalization_fallback_used'] ?? false);
    $normWarning  = $normFallback
      ? "\n⚠ NOTE: Catalog normalization used default values. Scores may be less reliable.\n"
      : '';

    // Strategy
    $strategyX = $strategies['strategy_x'] ?? 'quality';
    $strategyY = $strategies['strategy_y'] ?? 'performance';

    // Factors & Content extraction
    $validFactorsX = $this->extractValidFactors($scores['factors_x'] ?? []);
    $validFactorsY = $this->extractValidFactors($scores['factors_y'] ?? []);
    $historicalContext = $this->extractRagContent($ragContext);
    $actionList = $this->formatActions($actions);
    $historicalMetrics = $this->buildHistoricalMetrics($ragContext, $scoreX, $scoreY);

    // Identifiers
    $productId   = $product['products_id'] ?? ($product['product_id'] ?? 0);
    $productName = $product['products_name'] ?? ($product['name'] ?? 'Unknown');

    // Inventory block (Velocity, stockout risk...)
    $inventoryBlock = $this->buildInventoryBlock($product);

    // Préparation du tableau pour getDef (Remplacement des variables {{key}})
    $replace = [
      'entity_id'         => $productId,
      'productId'         => $productId,
      'productName'       => $productName,
      'scoreX'            => number_format((float)$scoreX, 2),
      'scoreY'            => number_format((float)$scoreY, 2),
      'validFactorsX'     => $validFactorsX,
      'favorites'         => ($flags['favorites'] ?? false) ? 'YES' : 'NO', // AJOUT
      'featured'          => ($flags['feature'] ?? false) ? 'YES' : 'NO',   // AJOUT
      'factorsY'          => $validFactorsY,
      'normWarning'       => $normWarning,
      'quadrant'          => $quadrant,
      'quadrantLabel'     => $quadrantLabel,
      'strategyX'         => $strategyX,
      'strategyY'         => $strategyY,
      'inventoryBlock'    => $inventoryBlock,
      'actionList'        => $actionList,
      'historicalContext' => $historicalContext,
      'historicalComparison' => $historicalMetrics,
    ];

    return $this->app->getDef('llm_analysis_generator', $replace);
  }

  /**
   * Get quadrant label
   *
   * @param string $quadrant Quadrant code
   * @return string Quadrant label
   */
  private function getQuadrantLabel(string $quadrant): string
  {
    $labels = [
      'Q1'             => 'Scaling - High Quality + High Performance',
      'Q2'             => 'Acquisition - High Quality + Low Performance',
      'Q3'             => 'Rework/Kill - Low Quality + Low Performance',
      'Q4'             => 'Optimization - Low Quality + High Performance',
      'Q_intermediate' => 'Monitoring - Transition Zone',
    ];

    return $labels[$quadrant] ?? 'Unknown';
  }

  /**
   * Extract valid factors from scoring results with enhanced detail
   *
   * @param array $factors Factor array from ScoringEngine
   * @return string Formatted factor list
   */
  private function extractValidFactors(array $factors): string
  {
    $validFactors = [];

    foreach ($factors as $factorName => $factor) {
      if (isset($factor['status']) && $factor['status'] === 'valid') {
        // Support both key variants used across the codebase
        $value = $factor['normalized_value'] ?? ($factor['normalized'] ?? 0);
        $rawValue = $factor['value'] ?? null;

        // Enhanced display for keywords factor to show actual content
        if ($factorName === 'keywords' && $rawValue !== null) {
          $validFactors[] = sprintf('%s(%.2f - quality score)', $factorName, $value);
        } else {
          $validFactors[] = sprintf('%s(%.2f)', $factorName, $value);
        }
      }
    }

    return !empty($validFactors) ? implode(', ', $validFactors) : 'No valid factors';
  }

  /**
   * Extract content field from RAG context
   *
   * Requirement 11.4: Include only content field, exclude raw metadata.
   * Supports both plain string arrays (EmbeddingService::getHistoricalContext output)
   * and associative arrays with a 'content' key.
   *
   * @param array $ragContext RAG embeddings
   * @return string Formatted historical context
   */
  private function extractRagContent(array $ragContext): string
  {
    if (empty($ragContext)) {
      return 'No historical analyses available.';
    }

    $contents = [];
    foreach ($ragContext as $index => $embedding) {
      if (is_string($embedding)) {
        $contents[] = sprintf("Analysis #%d: %s", $index + 1, $embedding);
      } elseif (isset($embedding['content'])) {
        $contents[] = sprintf("Analysis #%d: %s", $index + 1, $embedding['content']);
      }
    }

    return !empty($contents) ? implode("\n", $contents) : 'No historical analyses available.';
  }

  /**
   * Format action list for prompt
   *
   * @param array $actions Actions from RecommendationEngine
   * @return string Formatted action list
   */
  private function formatActions(array $actions): string
  {
    if (empty($actions)) {
      return 'No specific actions recommended.';
    }

    $formatted = [];
    foreach ($actions as $action) {
      if (is_array($action)) {
        $priority = $action['priority'] ?? 'medium';
        $label    = $action['label']    ?? ($action['code'] ?? 'Unknown action');
      } elseif (is_object($action) && method_exists($action, 'toArray')) {
        $arr      = $action->toArray();
        $priority = $arr['priority'] ?? 'medium';
        $label    = $arr['label']    ?? ($arr['code'] ?? 'Unknown action');
      } else {
        continue;
      }
      $formatted[] = sprintf('- [%s] %s', strtoupper($priority), $label);
    }

    return !empty($formatted) ? implode("\n", $formatted) : 'No specific actions recommended.';
  }

  /**
   * Build historical metrics comparison
   *
   * @param array $ragContext RAG embeddings with metadata
   * @param float $currentScoreX Current Score X
   * @param float $currentScoreY Current Score Y
   * @return string Formatted historical metrics
   */
  private function buildHistoricalMetrics(array $ragContext, float $currentScoreX, float $currentScoreY): string
  {
    if (empty($ragContext)) {
      return 'This is the first analysis for this product.';
    }

    $previous = $ragContext[0] ?? null;

    // Plain content strings — metadata not available for delta computation
    if (!$previous || is_string($previous)) {
      return 'Historical content available (delta metrics not extractable from content strings).';
    }

    if (!isset($previous['metadata'])) {
      return 'Historical data incomplete.';
    }

    $metadata = is_string($previous['metadata'])
      ? json_decode($previous['metadata'], true)
      : $previous['metadata'];

    if (!$metadata || !isset($metadata['scores'])) {
      return 'Historical data incomplete.';
    }

    $prevScoreX = $metadata['scores']['score_x'] ?? 0;
    $prevScoreY = $metadata['scores']['score_y'] ?? 0;

    $deltaX = $currentScoreX - $prevScoreX;
    $deltaY = $currentScoreY - $prevScoreY;

    $trend = 'stable';
    if ($deltaX > 2 || $deltaY > 2) {
      $trend = 'improving';
    } elseif ($deltaX < -2 || $deltaY < -2) {
      $trend = 'declining';
    }

    $analysisNumber = count($ragContext) + 1;

    return sprintf(
      "Analysis #%d | Previous: X=%d, Y=%d | Change: X%+d pts, Y%+d pts | Trend: %s",
      $analysisNumber,
      (int)$prevScoreX,
      (int)$prevScoreY,
      (int)$deltaX,
      (int)$deltaY,
      $trend
    );
  }

  /**
   * Build the INVENTORY METRICS block for the prompt.
   *
   * Returns an empty string when neither stock_velocity nor stockout_probability
   * is present — the block is then absent from the heredoc (Req. 12.2).
   *
   * The block is preceded by a newline so it appears cleanly after the last
   * STRATEGIC CLASSIFICATION line and before RECOMMENDED ACTIONS.
   *
   * Labels (Requirements 12.3, 12.4):
   *   stock_velocity       → "inventory turnover rate (sales/stock over 90 days)"
   *   stockout_probability → "risk of running out of stock (%)"
   *   safety_stock         → "recommended buffer inventory"
   *
   * @param array $product Product data array from DataCollector
   * @return string Formatted block ready for heredoc interpolation, or ''
   */
  private function buildInventoryBlock(array $product): string
  {
    $hasVelocity = isset($product['stock_velocity'])       && $product['stock_velocity'] !== null;
    $hasStockout = isset($product['stockout_probability']) && $product['stockout_probability'] !== null;

    if (!$hasVelocity && !$hasStockout) {
      return '';
    }

    $velocityValue = $hasVelocity ? number_format((float) $product['stock_velocity'], 2) : 'N/A';

    $stockoutValue = $hasStockout ? number_format((float) $product['stockout_probability'] * 100, 2) . '%' : 'N/A';

    $safetyValue = isset($product['safety_stock']) && $product['safety_stock'] !== null ? number_format((float) $product['safety_stock'], 2) . ' units' : 'N/A';

    // Velocity interpretation hint
    $velocityHint = '';
    if ($hasVelocity) {
      $v = (float) $product['stock_velocity'];
      if ($v >= self::VELOCITY_THRESHOLD) {
        $velocityHint = ' [fast-moving]';
      } elseif ($v > 0.0) {
        $velocityHint = ' [slow-moving]';
      } else {
        $velocityHint = ' [no recent sales]';
      }
    }

    // Optional demand lines
    $demandLine   = '';
    $forecastLine = '';

    if (isset($product['demand_stats']['mean']) && $product['demand_stats']['mean'] !== null) {
      $mean = number_format((float) $product['demand_stats']['mean'], 2);
      $demandLine = "\n- Average Daily Demand: {$mean} units/day";
    }

    if (isset($product['demand_forecast_30d']['mean_total']) && $product['demand_forecast_30d']['mean_total'] !== null) {
      $forecast = number_format((float) $product['demand_forecast_30d']['mean_total'], 2);
      $forecastLine = "\n- 30-Day Demand Forecast: {$forecast} units";
    }

    $array = [
      'velocityValue' => $velocityValue,
      'velocityHint' => $velocityHint,
      'stockoutValue' => $stockoutValue,
      'safetyValue' => $safetyValue,
      'demandLine' => $demandLine,
      'forecastLine' => $forecastLine
    ];

    $text = $this->app->getDef('build_inventory_bock', $array);

    return $text;
  }

  /**
   * Get fallback analysis when LLM fails
   *
   * Requirement 10.6: Fallback to generic analysis if LLM fails
   *
   * @param array $context Pipeline context
   * @return array Fallback analysis structure
   */
  private function getFallbackAnalysis(array $context): array
  {
    $scores       = $context['scoring_calculation'] ?? [];
    $scoreX       = $scores['score_x'] ?? 0;
    $scoreY       = $scores['score_y'] ?? 0;
    $quadrant     = $scores['quadrant'] ?? 'Q_intermediate';

    $quadrantLabel = $this->getQuadrantLabel($quadrant);

    $analysisText = sprintf(
      "Product analysis: Score X (Product Quality) is %d/100, Score Y (Commercial Performance) is %d/100. " .
      "The product is classified in quadrant %s (%s). " .
      "Review the recommended actions to improve performance.",
      (int)$scoreX,
      (int)$scoreY,
      $quadrant,
      $quadrantLabel
    );

    return [
      'analysis_text'          => $analysisText,
      'historical_comparison'  => 'Historical comparison unavailable.',
      'fallback'               => true,
    ];
  }

  /**
   * Extract historical comparison text
   *
   * @param array $context Pipeline context
   * @return string Historical comparison summary
   */
  private function extractHistoricalComparison(array $context): string
  {
    $ragContext = $context['rag_context_retrieval'] ?? [];

    if (empty($ragContext)) {
      return 'First analysis for this product.';
    }

    $scores        = $context['scoring_calculation'] ?? [];
    $currentScoreX = $scores['score_x'] ?? 0;
    $currentScoreY = $scores['score_y'] ?? 0;
    $analysisCount = count($ragContext) + 1;

    // Plain content strings — delta not computable
    if (is_string($ragContext[0] ?? null)) {
      return sprintf("Analysis #%d (historical content available).", $analysisCount);
    }

    // Calculate total evolution from first analysis
    $firstAnalysis = end($ragContext);
    if ($firstAnalysis && isset($firstAnalysis['metadata'])) {
      $metadata = is_string($firstAnalysis['metadata'])
        ? json_decode($firstAnalysis['metadata'], true)
        : $firstAnalysis['metadata'];

      if ($metadata && isset($metadata['scores'])) {
        $firstScoreX = $metadata['scores']['score_x'] ?? 0;
        $firstScoreY = $metadata['scores']['score_y'] ?? 0;

        $totalDeltaX = $currentScoreX - $firstScoreX;
        $totalDeltaY = $currentScoreY - $firstScoreY;

        return sprintf(
          "Analysis #%d shows evolution since first analysis: Score X %+d points, Score Y %+d points.",
          $analysisCount,
          (int)$totalDeltaX,
          (int)$totalDeltaY
        );
      }
    }

    return sprintf("Analysis #%d (historical data available).", $analysisCount);
  }
}
