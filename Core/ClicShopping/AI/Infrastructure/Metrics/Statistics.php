<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\Infrastructure\Metrics;

use ClicShopping\OM\Registry;
use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\AI\Infrastructure\Orm\DoctrineOrm;

/**
 * Statistics - Legacy token usage statistics
 *
 * DEPRECATED: This class uses the old gpt_usage table
 * For new implementations, use StatisticsTracker with rag_statistics table
 *
 * Kept for backward compatibility with existing dashboard components
 */
class Statistics {

  /**
   * Retrieves the total number of tokens (promptTokens, completionTokens, totalTokens) used in the last month.
   *
   * @return array An associative array containing promptTokens, completionTokens, totalTokens, and date_added.
   */
  public static function getTotalTokenByMonth(): array
  {
    
    $prefix = CLICSHOPPING::getConfig('db_table_prefix');
    
    $sql = "SELECT sum(tokens_prompt) as promptTokens,
                   sum(tokens_completion) as completionTokens,
                   sum(tokens_total) as totalTokens,
                   max(date_added) as date_added
            FROM {$prefix}rag_statistics
            WHERE date_added >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";

    $result = DoctrineOrm::selectOne($sql);

    return $result ?: [];
  }

  /**
   * Retrieves token usage statistics for a specified period (days)
   *
   * @param int $days Number of days to look back
   * @return array Statistics for the specified period
   */
  public static function getTokenUsageByPeriod(int $days = 7): array
  {
    
    $prefix = CLICSHOPPING::getConfig('db_table_prefix');
    
    $sql = "SELECT sum(tokens_prompt) as promptTokens,
                   sum(tokens_completion) as completionTokens,
                   sum(tokens_total) as totalTokens,
                   count(*) as requests_count,
                   max(date_added) as last_request
            FROM {$prefix}rag_statistics
            WHERE date_added >= DATE_SUB(NOW(), INTERVAL :days DAY)";

    $result = DoctrineOrm::selectOne($sql, ['days' => $days]);

    return $result ?: [];
  }

  /**
   * Get daily token usage for the last N days
   *
   * @param int $days Number of days to retrieve
   * @return array Daily usage statistics
   */
  public static function getDailyTokenUsage(int $days = 7): array
  {
    
    $prefix = CLICSHOPPING::getConfig('db_table_prefix');
    
    $sql = "SELECT DATE(date_added) as usage_date,
                   sum(tokens_prompt) as daily_prompt_tokens,
                   sum(tokens_completion) as daily_completion_tokens,
                   sum(tokens_total) as daily_total_tokens,
                   count(*) as daily_requests
            FROM {$prefix}rag_statistics
            WHERE date_added >= DATE_SUB(NOW(), INTERVAL :days DAY)
            GROUP BY DATE(date_added)
            ORDER BY usage_date DESC";

    $rows = DoctrineOrm::select($sql, ['days' => $days]);

    $results = [];
    foreach ($rows as $row) {
      $results[$row['usage_date']] = [
        'prompt_tokens' => (int)$row['daily_prompt_tokens'],
        'completion_tokens' => (int)$row['daily_completion_tokens'],
        'total_tokens' => (int)$row['daily_total_tokens'],
        'requests' => (int)$row['daily_requests']
      ];
    }

    return $results;
  }

  /**
   * Get usage statistics by model
   *
   * @param int $days Number of days to look back
   * @return array Usage statistics grouped by model
   */
  public static function getUsageByModel(int $days = 7): array
  {
    
    $prefix = CLICSHOPPING::getConfig('db_table_prefix');
    
    $sql = "SELECT model_used as model,
                   sum(tokens_prompt) as model_prompt_tokens,
                   sum(tokens_completion) as model_completion_tokens,
                   sum(tokens_total) as model_total_tokens,
                   count(*) as model_requests,
                   avg(tokens_total) as avg_tokens_per_request
            FROM {$prefix}rag_statistics
            WHERE date_added >= DATE_SUB(NOW(), INTERVAL :days DAY)
            GROUP BY model_used
            ORDER BY model_total_tokens DESC";

    $rows = DoctrineOrm::select($sql, ['days' => $days]);

    $results = [];
    foreach ($rows as $row) {
      $results[] = [
        'model' => $row['model'],
        'prompt_tokens' => (int)$row['model_prompt_tokens'],
        'completion_tokens' => (int)$row['model_completion_tokens'],
        'total_tokens' => (int)$row['model_total_tokens'],
        'requests' => (int)$row['model_requests'],
        'avg_tokens_per_request' => round((float)$row['avg_tokens_per_request'], 1)
      ];
    }

    return $results;
  }

  /**
   * Get dashboard-ready statistics
   *
   * @param int $days Number of days for the statistics
   * @return array Dashboard statistics
   */
  public static function getDashboardStats(int $days = 7): array
  {
    $periodStats = self::getTokenUsageByPeriod($days);
    $dailyUsage = self::getDailyTokenUsage($days);
    $modelUsage = self::getUsageByModel($days);

    if (empty($periodStats)) {
      return [];
    }

    $prefix = CLICSHOPPING::getConfig('db_table_prefix');
    $costRows = DoctrineOrm::select(
      "SELECT model_used as model,
              sum(tokens_prompt) as promptTokens,
              sum(tokens_completion) as completionTokens
       FROM {$prefix}rag_statistics
       WHERE date_added >= DATE_SUB(NOW(), INTERVAL :days DAY)
       GROUP BY model_used",
      ['days' => $days]
    );

    $totalCost = 0.0;
    
    foreach ($costRows as $row) {
      $model = $row['model'] ?? '';
      $promptTokens = (int)($row['promptTokens'] ?? 0);
      $completionTokens = (int)($row['completionTokens'] ?? 0);
      $totalCost += ApiCostCalculator::calculateCost($model, $promptTokens, $completionTokens);
    }

    // Prepare daily usage for charts (simple array of totals)
    $dailyTotals = [];
    foreach ($dailyUsage as $date => $data) {
      $dailyTotals[$date] = $data['total_tokens'];
    }

    return [
      'total_tokens' => (int)$periodStats['totalTokens'],
      'input_tokens' => (int)$periodStats['promptTokens'],
      'output_tokens' => (int)$periodStats['completionTokens'],
      'requests_count' => (int)$periodStats['requests_count'],
      'cost_estimate' => round($totalCost, 4),
      'avg_tokens_per_request' => $periodStats['requests_count'] > 0 ?
        round($periodStats['totalTokens'] / $periodStats['requests_count'], 1) : 0,
      'daily_usage' => $dailyTotals,
      'model_breakdown' => $modelUsage,
      'period' => $days === 1 ? 'Aujourd\'hui' : ($days === 7 ? '7 derniers jours' : "$days derniers jours"),
      'last_updated' => $periodStats['last_request'] ?? date('Y-m-d H:i:s')
    ];
  }

  /**
   * Calculate estimated cost based on token usage and model
   *
   * @param array $tokenData Token usage data
   * @param string $model Model name (default: gpt-4)
   * @return float Estimated cost in USD
   */
  public static function calculateEstimatedCost(array $tokenData, string $model = 'gpt-5-mini'): float
  {
    $inputTokens = $tokenData['promptTokens'] ?? $tokenData['prompt_tokens'] ?? 0;
    $outputTokens = $tokenData['completionTokens'] ?? $tokenData['completion_tokens'] ?? 0;

    return ApiCostCalculator::estimateCost($model, (int)$inputTokens, (int)$outputTokens);
  }

  /**
   * Saves the token usage statistics to the database.
   *
   * @param array|null $usage
   * @param string $engine The engine used for the response.
   * @return void
   */
  public static function saveStats(array|null $usage, string $engine): void
  {
    $prefix = CLICSHOPPING::getConfig('db_table_prefix');
    
    $sql = "SELECT gpt_id
            FROM {$prefix}gpt
            ORDER BY gpt_id DESC
            LIMIT 1";

    $lastId = DoctrineOrm::selectValue($sql);

    $promptTokens = 0;
    $completionTokens = 0;
    $totalTokens = 0;

    if ($usage !== null) {
      $promptTokens = $usage['prompt_tokens'] ?? 0;
      $completionTokens = $usage['completion_tokens'] ?? 0;
      $totalTokens = $usage['total_tokens'] ?? 0;
    }

    $array_usage_sql = [
      'gpt_id' => (int)$lastId,
      'promptTokens' => $promptTokens,
      'completionTokens' => $completionTokens,
      'totalTokens' => $totalTokens,
      'ia_type' => 'GPT',
      'model' => $engine,
      'date_added' => 'now()'
    ];

    DoctrineOrm::insert('gpt_usage', $array_usage_sql);
  }

  /**
   * Retrieves token usage data for a specified GPT ID.
   *
   * @param int $id The unique identifier of the GPT entry.
   * @return array An associative array containing token usage data: promptTokens, completionTokens, totalTokens, and the date added.
   */
  public static function getTokenbyId(int $id): array
  {
    $prefix = CLICSHOPPING::getConfig('db_table_prefix');
    
    $sql = "SELECT sum(promptTokens) as promptTokens,
                   sum(completionTokens) as completionTokens,
                   sum(totalTokens) as totalTokens,
                   max(date_added) as date_added
            FROM {$prefix}gpt_usage
            WHERE gpt_id = :gpt_id";

    $result = DoctrineOrm::selectOne($sql, ['gpt_id' => $id]);

    return $result ?: [];
  }

  /**
   * Get top request types by token usage
   *
   * @param int $days Number of days to look back
   * @param int $limit Number of top results to return
   * @return array Top request types
   */
  public static function getTopRequestTypes(int $days = 7, int $limit = 5): array
  {
    // This would need a request_type column in the gpt_usage table
    // For now, we'll simulate based on model usage
    $modelUsage = self::getUsageByModel($days);

    $requestTypes = [];
    foreach ($modelUsage as $model) {
      $requestTypes[] = [
        'request_type' => $model['model'],
        'count' => $model['requests'],
        'tokens' => $model['total_tokens'],
        'avg_tokens' => $model['avg_tokens_per_request']
      ];
    }

    return array_slice($requestTypes, 0, $limit);
  }

  /**
   * Clean up old statistics data
   *
   * @param int $keepDays Number of days to keep (default: 90)
   * @return int Number of deleted records
   */
  public static function cleanupOldData(int $keepDays = 90): int
  {  
    $prefix = CLICSHOPPING::getConfig('db_table_prefix');
    
    $sql = "DELETE FROM {$prefix}gpt_usage
            WHERE date_added < DATE_SUB(NOW(), INTERVAL :keep_days DAY)";

    return DoctrineOrm::execute($sql, ['keep_days' => $keepDays]);
  }

  /**
   * Get sum of total tokens by month
   *
   * @return int Total tokens for the current month
   */
  public static function getSumTotalTokenByMonth(): int
  {
    $prefix = CLICSHOPPING::getConfig('db_table_prefix');
    
    $sql = "SELECT sum(totalTokens) as total_tokens
            FROM {$prefix}gpt_usage
            WHERE MONTH(date_added) = MONTH(NOW()) 
            AND YEAR(date::selectV YEAR(NOW())";

    $result = DoctrineOrm::selectValue($sql);

    return (int)($result ?? 0);
  }

  /**
   * Get total tokens across all time
   *
   * @return int Total tokens ever recorded
   */
  public static function getTotalToken(): int
  {
    $prefix = CLICSHOPPING::getConfig('db_table_prefix');
    
    $sql = "SELECT sum(totalTokens) as total_tokens
            FROM {$prefix}gpt_usage";

    $result = DoctrineOrm::selectValue($sql);

    return (int)($result ?? 0);
  }

  /**
   * Calculate cost estimation for given token usage
   *
   * @param array $tokenData Array containing token usage data
   * @param string $model Model name for pricing calculation
   * @return array Cost estimation details
   */
  public static function getCostEstimation(array $tokenData, string $model = 'gpt-5-mini'): array
  {
    $inputTokens = $tokenData['promptTokens'] ?? $tokenData['input_tokens'] ?? 0;
    $outputTokens = $tokenData['completionTokens'] ?? $tokenData['output_tokens'] ?? 0;
    $totalTokens = $tokenData['totalTokens'] ?? $tokenData['total_tokens'] ?? ($inputTokens + $outputTokens);

    // Pricing per 1K tokens (updated 2024 rates)
    $pricing = [
      'gpt-5.2'        => ['input' => 0.00175, 'output' => 0.01400],
      'gpt-5.2-pro'    => ['input' => 0.02100, 'output' => 0.16800],
      'gpt-5-mini'     => ['input' => 0.00025, 'output' => 0.00200],
      'gpt-4.1'        => ['input' => 0.00300, 'output' => 0.01200],
      'gpt-4.1-mini'   => ['input' => 0.00080, 'output' => 0.00320],
      'gpt-4.1-nano'   => ['input' => 0.00020, 'output' => 0.00080],
      'gpt-4'          => ['input' => 0.03,    'output' => 0.06],
      'gpt-4-turbo'    => ['input' => 0.01,    'output' => 0.03],
      'gpt-4o'         => ['input' => 0.005,   'output' => 0.015],
      'claude-3-opus'  => ['input' => 0.015,   'output' => 0.075],
      'claude-3-sonnet'=> ['input' => 0.003,   'output' => 0.015],
      'claude-3-haiku' => ['input' => 0.00025, 'output' => 0.00125],
      'mistral-large'  => ['input' => 0.008,   'output' => 0.024],
      'mistral-medium' => ['input' => 0.0027,  'output' => 0.0081],
      'default'        => ['input' => 0.03,    'output' => 0.06],
    ];

    $modelPricing = $pricing[$model] ?? $pricing['default'];

    $inputCost = ($inputTokens / 1000) * $modelPricing['input'];
    $outputCost = ($outputTokens / 1000) * $modelPricing['output'];
    $totalCost = $inputCost + $outputCost;

    return [
      'model' => $model,
      'input_tokens' => $inputTokens,
      'output_tokens' => $outputTokens,
      'total_tokens' => $totalTokens,
      'input_cost' => round($inputCost, 6),
      'output_cost' => round($outputCost, 6),
      'total_cost' => round($totalCost, 6),
      'cost_per_token' => $totalTokens > 0 ? round($totalCost / $totalTokens, 8) : 0,
      'pricing_model' => $modelPricing,
      'currency' => 'USD',
      'calculated_at' => date('Y-m-d H:i:s')
    ];
  }

  /**
   * Get comprehensive cost analysis for a period
   *
   * @param int $days Number of days to analyze
   * @return array Detailed cost analysis
   */
  public static function getCostAnalysis(int $days = 30): array
  {   
    $prefix = CLICSHOPPING::getConfig('db_table_prefix');
    
    $sql = "SELECT model,
                   sum(promptTokens) as total_input_tokens,
                   sum(completionTokens) as total_output_tokens,
                   sum(totalTokens) as total_tokens,
                   count(*) as request_count,
                   min(date_added) as first_request,
                   max(date_added) as last_request
            FROM {$prefix}gpt_usage
            WHERE date_added >= DATE_SUB(NOW(), INTERVAL :days DAY)
            GROUP BY model
            ORDER BY total_tokens DESC";

    $rows = DoctrineOrm::select($sql, ['days' => $days]);

    $modelAnalysis = [];
    $totalCost = 0;
    $totalTokens = 0;
    $totalRequests = 0;

    foreach ($rows as $row) {
      $modelData = [
        'input_tokens' => (int)$row['total_input_tokens'],
        'output_tokens' => (int)$row['total_output_tokens'],
        'total_tokens' => (int)$row['total_tokens']
      ];

      $costEstimation = self::getCostEstimation($modelData, $row['model']);

      $modelAnalysis[$row['model']] = [
        'usage' => $modelData,
        'cost' => $costEstimation,
        'requests' => (int)$row['request_count'],
        'period' => [
          'first_request' => $row['first_request'],
          'last_request' => $row['last_request']
        ],
        'efficiency' => [
          'avg_tokens_per_request' => $modelData['total_tokens'] > 0 ?
            round($modelData['total_tokens'] / (int)$row['request_count'], 1) : 0,
          'cost_per_request' => $costEstimation['total_cost'] > 0 ?
            round($costEstimation['total_cost'] / (int)$row['request_count'], 6) : 0
        ]
      ];

      $totalCost += $costEstimation['total_cost'];
      $totalTokens += $modelData['total_tokens'];
      $totalRequests += (int)$row['request_count'];
    }

    return [
      'period_days' => $days,
      'summary' => [
        'total_cost' => round($totalCost, 4),
        'total_tokens' => $totalTokens,
        'total_requests' => $totalRequests,
        'avg_cost_per_request' => $totalRequests > 0 ? round($totalCost / $totalRequests, 6) : 0,
        'avg_cost_per_1k_tokens' => $totalTokens > 0 ? round(($totalCost / $totalTokens) * 1000, 6) : 0
      ],
      'by_model' => $modelAnalysis,
      'recommendations' => self::generateCostRecommendations($modelAnalysis, $totalCost),
      'generated_at' => date('Y-m-d H:i:s')
    ];
  }

  /**
   * Generate cost optimization recommendations
   *
   * @param array $modelAnalysis Analysis by model
   * @param float $totalCost Total cost
   * @return array Recommendations
   */
  private static function generateCostRecommendations(array $modelAnalysis, float $totalCost): array
  {
    $recommendations = [];

    // High cost warning
    if ($totalCost > 50) {
      $recommendations[] = [
        'type' => 'warning',
        'message' => 'High monthly cost detected ($' . number_format($totalCost, 2) . '). Consider optimizing usage.',
        'priority' => 'high'
      ];
    }

    // Model efficiency analysis
    $mostExpensive = null;
    $highestCost = 0;

    foreach ($modelAnalysis as $model => $analysis) {
      if ($analysis['cost']['total_cost'] > $highestCost) {
        $highestCost = $analysis['cost']['total_cost'];
        $mostExpensive = $model;
      }

      // Check for inefficient usage patterns
      if ($analysis['efficiency']['avg_tokens_per_request'] > 2000) {
        $recommendations[] = [
          'type' => 'optimization',
          'message' => "Model $model has high token usage per request (" .
            $analysis['efficiency']['avg_tokens_per_request'] . " tokens). Consider optimizing prompts.",
          'priority' => 'medium'
        ];
      }
    }

    // Suggest cheaper alternatives
    if ($mostExpensive === 'gpt-4' && isset($modelAnalysis['gpt-4'])) {
      $recommendations[] = [
        'type' => 'suggestion',
        'message' => 'Consider using GPT-4 Turbo or GPT-3.5 Turbo for less complex tasks to reduce costs.',
        'priority' => 'low'
      ];
    }

    return $recommendations;
  }
}
