<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\ChatGpt\Module\ClicShoppingAdmin\Dashboard;

use ClicShopping\Apps\Configuration\ChatGpt\ChatGpt as ChatGptApp;
use ClicShopping\AI\Infrastructure\Metrics\ApiCostCalculator;
use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;

/**
 * Centralised provider for all token related chart data used across the
 * ChatGPT dashboard widgets (TotalToken, SumTotalTokenByMonth, CostEstimation)
 * and the tab #5 analytics view. The goal is to avoid duplicated SQL queries
 * and ensure every consumer relies on the exact same payload.
 *
 * This is a pure static data aggregator, NOT a dashboard widget: it must not
 * extend AdminDashboardAbstract (it implements none of its abstract methods).
 */
class TokenChartDataProvider
{
  private static ?array $cache = null;

  /**
   * Returns all chart configurations (already formatted for Chart.js) so that
   * every consumer only needs to JSON encode the structure and feed it to the
   * frontend script. Data is cached per-request to avoid running the queries
   * multiple times.
   */
  public static function getChartsData(): array
  {
    if (self::$cache !== null) {
      return self::$cache;
    }

    $db = Registry::get('Db');

    if (!Registry::exists('ChatGpt')) {
      Registry::set('ChatGpt', new ChatGptApp());
    }

    /** @var \ClicShopping\Apps\Configuration\ChatGpt\ChatGpt $app */
    $app = Registry::get('ChatGpt');
    $app->loadDefinitions('Module/ClicShoppingAdmin/Dashboard/total_token');
    $app->loadDefinitions('Module/ClicShoppingAdmin/Dashboard/sum_total_token');
    $app->loadDefinitions('Module/ClicShoppingAdmin/Dashboard/cost_estimation');

    $dailyMap = [];
    // 🔧 UPDATED: Read from rag_statistics instead of gpt_usage to support all providers (GPT, LMStudio, Ollama, etc.)
    $Qdaily = $db->query(
      'select DATE(date_added) as usage_date,
              sum(tokens_total) as total
       from :table_rag_statistics
       where date_sub(curdate(), interval 30 day) <= date_added
         and tokens_total is not null
       group by usage_date'
    );

    while ($Qdaily->fetch()) {
      $dailyMap[$Qdaily->value('usage_date')] = (int)$Qdaily->value('total');
    }

    $dailyLabels = [];
    $dailyData = [];

    for ($i = 29; $i >= 0; $i--) {
      $timestamp = strtotime("-{$i} days");
      $dateKey = date('Y-m-d', $timestamp);
      $dailyLabels[] = date('d/m', $timestamp);
      $dailyData[] = $dailyMap[$dateKey] ?? 0;
    }

    $monthlyMap = [];
    $Qmonthly = $db->query(
      'select DATE_FORMAT(date_added, "%Y-%m") as month,
              sum(tokens_total) as total
       from :table_rag_statistics
       where date_added >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
         and tokens_total is not null
       group by month'
    );

    while ($Qmonthly->fetch()) {
      $monthlyMap[$Qmonthly->value('month')] = (int)$Qmonthly->value('total');
    }

    $monthlyLabels = [];
    $monthlyData = [];
    for ($i = 11; $i >= 0; $i--) {
      $timestamp = strtotime("-{$i} months");
      $monthKey = date('Y-m', $timestamp);
      $monthlyLabels[] = date('M y', $timestamp);
      $monthlyData[] = $monthlyMap[$monthKey] ?? 0;
    }

    $modelColors = [
      'gpt-5' => 'rgba(30, 64, 175, 0.55)',
      'gpt-5.2' => 'rgba(37, 99, 235, 0.55)',
      'gpt-5.2-pro' => 'rgba(17, 94, 89, 0.55)',
      'gpt-5-mini' => 'rgba(14, 116, 144, 0.55)',
      'gpt-5-nano' => 'rgba(14, 159, 110, 0.55)',
      'gpt-4.1-mini' => 'rgba(13, 148, 136, 0.55)',
      'gpt-4.1-nano' => 'rgba(99, 102, 241, 0.55)',
      'gpt-4' => 'rgba(245, 158, 11, 0.55)',
      'gpt-4-turbo' => 'rgba(251, 146, 60, 0.55)',
      'gpt-3.5-turbo' => 'rgba(148, 163, 184, 0.55)',
      'claude-3-opus' => 'rgba(190, 24, 93, 0.55)',
      'claude-3-sonnet' => 'rgba(219, 39, 119, 0.55)',
      'claude-3-haiku' => 'rgba(236, 72, 153, 0.55)',
      'mistral-large' => 'rgba(234, 179, 8, 0.55)',
      'mistral-medium' => 'rgba(202, 138, 4, 0.55)',
      'mistral-small' => 'rgba(161, 98, 7, 0.55)',
      'local' => 'rgba(34, 197, 94, 0.55)',
    ];

    $rawCostData = [];
    $Qcost = $db->query(
      'select DATE_FORMAT(date_added, "%Y-%m") as month,
              model_used as model,
              sum(tokens_prompt) as prompt_tokens,
              sum(tokens_completion) as completion_tokens
       from :table_rag_statistics
       where date_added >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
         and (tokens_prompt is not null or tokens_completion is not null)
       group by month, model
       order by month asc'
    );

    while ($Qcost->fetch()) {
      $model = $Qcost->value('model') ?: 'gpt-3.5-turbo';
      $month = $Qcost->value('month');
      $promptTokens = (int)$Qcost->value('prompt_tokens');
      $completionTokens = (int)$Qcost->value('completion_tokens');
      $cost = ApiCostCalculator::calculateCost($model, $promptTokens, $completionTokens);
      $rawCostData[$model][$month] = $cost;
    }

    $costDatasets = [];
    $costLabelsKeys = [];
    foreach ($rawCostData as $model => $entries) {
      foreach ($entries as $month => $value) {
        $costLabelsKeys[$month] = true;
      }
    }
    if (empty($costLabelsKeys)) {
      foreach ($monthlyMap as $month => $_) {
        $costLabelsKeys[$month] = true;
      }
    }
    ksort($costLabelsKeys);
    $costLabels = array_map(static fn($month) => date('M y', strtotime("{$month}-01")), array_keys($costLabelsKeys));
    $costMonths = array_keys($costLabelsKeys);

    foreach ($rawCostData as $model => $entries) {
      $dataset = [];
      foreach ($costMonths as $month) {
        $dataset[] = round($entries[$month] ?? 0, 4);
      }

      $costDatasets[] = [
        'label' => $model,
        'data' => $dataset,
        'backgroundColor' => $modelColors[$model] ?? 'rgba(0,0,0,0.3)',
      ];
    }

    self::$cache = [
      'daily_total_tokens' => [
        'title' => $app->getDef('module_admin_dashboard_total_gpt_token_app_chart_link'),
        'chart' => [
          'type' => 'bar',
          'data' => [
            'labels' => $dailyLabels,
            'datasets' => [[
              'label' => 'GPT Tokens',
              'data' => $dailyData,
              'backgroundColor' => 'rgba(14, 116, 144, 0.18)',
              'borderColor' => 'rgba(14, 116, 144, 1)',
              'borderWidth' => 1,
            ]],
          ],
          'options' => [
            'maintainAspectRatio' => true,
            'responsive' => true,
            'plugins' => [
              'legend' => ['display' => false],
            ],
            'scales' => [
              'y' => [
                'beginAtZero' => true,
              ],
              'x' => [
                'reverse' => true,
                'grid' => ['color' => 'rgba(0,0,0,0.05)'],
              ],
            ],
          ],
        ],
      ],
      'monthly_total_tokens' => [
        'title' => $app->getDef('module_admin_dashboard_sum_total_gpt_token_app_chart_link'),
        'chart' => [
          'type' => 'line',
          'data' => [
            'labels' => $monthlyLabels,
            'datasets' => [[
              'label' => 'GPT Tokens',
              'data' => $monthlyData,
              'backgroundColor' => 'rgba(14, 116, 144, 0.18)',
              'borderColor' => 'rgba(14, 116, 144, 1)',
              'borderWidth' => 1,
              'fill' => true,
            ]],
          ],
          'options' => [
            'maintainAspectRatio' => true,
            'responsive' => true,
            'plugins' => [
              'legend' => ['display' => false],
            ],
            'scales' => [
              'y' => [
                'beginAtZero' => true,
                'ticks' => ['stepSize' => 1],
                'grid' => [
                  'color' => 'rgba(0,0,0,0.05)',
                ],
              ],
              'x' => [
                'grid' => [
                  'color' => 'rgba(0,0,0,0.05)',
                ],
              ],
            ],
          ],
        ],
      ],
      'cost_estimation' => [
        'title' => $app->getDef('module_admin_dashboard_total_cost_estimation_app_chart_link'),
        'chart' => [
          'type' => 'bar',
          'data' => [
            'labels' => $costLabels,
            'datasets' => $costDatasets,
          ],
          'options' => [
            'maintainAspectRatio' => true,
            'responsive' => true,
            'scales' => [
              'x' => ['stacked' => true],
              'y' => ['stacked' => true, 'beginAtZero' => true],
            ],
            'plugins' => [
              'legend' => ['display' => true],
            ],
          ],
        ],
      ],
      'assets' => [
        'script' => CLICSHOPPING::link('Shop/ext/javascript/clicshopping/ClicShoppingAdmin/ChatGpt/token_charts.js'),
      ],
    ];

    return self::$cache;
  }
}
