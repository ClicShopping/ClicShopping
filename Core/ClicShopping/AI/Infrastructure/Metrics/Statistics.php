<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\Infrastructure\Metrics;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\AI\Infrastructure\Orm\DoctrineOrm;

/**
 * Statistics - token usage statistics read from the rag_statistics table.
 *
 * Provides aggregate token metrics (month, period, daily, per-model, dashboard) consumed by the
 * ChatGpt admin dashboard. The write path lives in StatisticsTracker; this class is read-only.
 * The legacy gpt_usage-based methods were removed with the gpt/gpt_usage tables (catalogue LLM §U).
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

}
