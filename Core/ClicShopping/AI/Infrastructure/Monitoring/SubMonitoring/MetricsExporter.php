<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\Infrastructure\Monitoring\SubMonitoring;

/**
 * MetricsExporter
 *
 * Stateless rendering of a monitoring metrics snapshot to JSON / CSV / HTML.
 * Extracted verbatim from MonitoringAgent (2026-06-20) — pure formatting, no
 * dependency on monitoring state: every method operates on the $data array
 * built by MonitoringAgent::exportMetrics().
 */
class MetricsExporter
{
  /**
   * Render a metrics snapshot in the requested format.
   *
   * @param array $data Snapshot built by MonitoringAgent::exportMetrics()
   * @param string $format json|csv|html (defaults to json for unknown formats)
   * @return string Rendered output
   */
  public function export(array $data, string $format = 'json'): string
  {
    switch ($format) {
      case 'json':
        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

      case 'csv':
        return $this->exportToCsv($data);

      case 'html':
        return $this->exportToHtml($data);

      default:
        return json_encode($data);
    }
  }

  /**
   * Export au format CSV
   */
  public function exportToCsv(array $data): string
  {
    $timestamp = date('Y-m-d H:i:s');
    $output = '';
    
    // Section 1: System Metrics
    $output .= "=== SYSTEM METRICS ===\n";
    $output .= "Timestamp,Metric,Value\n";
    
    $healthReport = $data['health_report'] ?? [];
    $systemMetrics = $healthReport['system_metrics'] ?? [];
    
    // Overall health
    if (isset($healthReport['overall_health'])) {
      $output .= "$timestamp,health_score," . ($healthReport['overall_health']['score'] ?? 0) . "\n";
      $output .= "$timestamp,health_status," . ($healthReport['overall_health']['status'] ?? 'unknown') . "\n";
    }
    
    // System metrics
    $output .= "$timestamp,total_requests," . ($systemMetrics['total_requests'] ?? 0) . "\n";
    $output .= "$timestamp,error_rate," . ($systemMetrics['error_rate'] ?? 0) . "\n";
    $output .= "$timestamp,avg_response_time," . ($systemMetrics['avg_response_time'] ?? 0) . "\n";
    $output .= "$timestamp,total_errors," . ($systemMetrics['total_errors'] ?? 0) . "\n";
    
    if (isset($systemMetrics['memory_usage'])) {
      $output .= "$timestamp,memory_usage_percentage," . ($systemMetrics['memory_usage']['percentage'] ?? 0) . "\n";
      $output .= "$timestamp,memory_usage_current," . ($systemMetrics['memory_usage']['current'] ?? 0) . "\n";
      $output .= "$timestamp,memory_usage_limit," . ($systemMetrics['memory_usage']['limit'] ?? 0) . "\n";
    }
    
    // Section 2: Component Health
    $output .= "\n=== COMPONENT HEALTH ===\n";
    $output .= "Timestamp,Component,Status,Total_Calls,Successful_Calls,Success_Rate,Avg_Execution_Time\n";
    
    $componentHealth = $healthReport['component_health'] ?? [];
    $systemReport = $data['system_report'] ?? [];
    $components = $systemReport['components'] ?? [];
    
    foreach ($componentHealth as $comp) {
      $name = $comp['name'] ?? 'unknown';
      $status = $comp['status'] ?? 'unknown';
      $compData = $components[$name] ?? [];
      
      $totalCalls = $compData['total_calls'] ?? 0;
      $successfulCalls = $compData['successful_calls'] ?? 0;
      $successRate = $totalCalls > 0 ? round(($successfulCalls / $totalCalls) * 100, 2) : 0;
      $avgTime = $compData['avg_execution_time'] ?? 0;
      
      $output .= "$timestamp,$name,$status,$totalCalls,$successfulCalls,$successRate,$avgTime\n";
    }
    
    // Section 3: Token Statistics
    $output .= "\n=== TOKEN STATISTICS ===\n";
    $output .= "Timestamp,Metric,Value\n";
    
    $tokenStats = $data['token_stats'] ?? [];
    $output .= "$timestamp,total_tokens," . ($tokenStats['total_tokens'] ?? 0) . "\n";
    $output .= "$timestamp,input_tokens," . ($tokenStats['input_tokens'] ?? 0) . "\n";
    $output .= "$timestamp,output_tokens," . ($tokenStats['output_tokens'] ?? 0) . "\n";
    $output .= "$timestamp,cost_estimate," . ($tokenStats['cost_estimate'] ?? 0) . "\n";
    $output .= "$timestamp,total_requests," . ($tokenStats['total_requests'] ?? 0) . "\n";
    $output .= "$timestamp,avg_tokens_per_request," . ($tokenStats['avg_tokens_per_request'] ?? 0) . "\n";
    $output .= "$timestamp,cost_per_request," . ($tokenStats['cost_per_request'] ?? 0) . "\n";
    
    // Section 4: Feedback Statistics
    $output .= "\n=== FEEDBACK STATISTICS ===\n";
    $output .= "Timestamp,Metric,Value\n";
    
    $feedbackStats = $data['feedback_stats'] ?? [];
    $output .= "$timestamp,satisfaction_rate," . ($feedbackStats['satisfaction_rate'] ?? 0) . "\n";
    $output .= "$timestamp,feedback_ratio," . ($feedbackStats['feedback_ratio'] ?? 0) . "\n";
    $output .= "$timestamp,positive_feedback," . ($feedbackStats['positive'] ?? 0) . "\n";
    $output .= "$timestamp,negative_feedback," . ($feedbackStats['negative'] ?? 0) . "\n";
    $output .= "$timestamp,total_feedback," . ($feedbackStats['total_feedback'] ?? 0) . "\n";
    $output .= "$timestamp,total_interactions," . ($feedbackStats['total_interactions'] ?? 0) . "\n";
    
    // Section 5: Source Statistics
    $output .= "\n=== SOURCE STATISTICS ===\n";
    $output .= "Timestamp,Source,Count,Percentage,Success_Rate,Avg_Response_Time\n";
    
    $sourceStats = $data['source_stats'] ?? [];
    $sources = $sourceStats['sources'] ?? [];
    
    foreach ($sources as $source => $sourceData) {
      $count = $sourceData['count'] ?? 0;
      $percentage = $sourceData['percentage'] ?? 0;
      $successRate = $sourceData['success_rate'] ?? 0;
      $avgTime = $sourceData['avg_response_time'] ?? 0;
      
      $output .= "$timestamp,$source,$count,$percentage,$successRate,$avgTime\n";
    }
    
    // Section 6: Active Alerts
    $output .= "\n=== ACTIVE ALERTS ===\n";
    $output .= "Timestamp,Alert_Type,Severity,Message,Value,Threshold\n";
    
    $activeAlerts = $healthReport['active_alerts'] ?? [];
    
    if (empty($activeAlerts)) {
      $output .= "$timestamp,none,none,No active alerts,0,0\n";
    } else {
      foreach ($activeAlerts as $alert) {
        $type = $alert['type'] ?? 'unknown';
        $severity = $alert['severity'] ?? 'unknown';
        $message = str_replace([',', "\n", "\r"], [';', ' ', ' '], $alert['message'] ?? '');
        $value = $alert['value'] ?? 0;
        $threshold = $alert['threshold'] ?? 0;
        
        $output .= "$timestamp,$type,$severity,\"$message\",$value,$threshold\n";
      }
    }
    
    // Section 7: Global Statistics
    $output .= "\n=== GLOBAL STATISTICS ===\n";
    $output .= "Timestamp,Query_Type,Count,Percentage,Avg_Response_Time,Success_Rate\n";
    
    $globalStats = $data['global_stats'] ?? [];
    $queryTypes = $globalStats['query_types'] ?? [];
    
    foreach ($queryTypes as $type => $typeData) {
      $count = $typeData['count'] ?? 0;
      $percentage = $typeData['percentage'] ?? 0;
      $avgTime = $typeData['avg_response_time'] ?? 0;
      $successRate = $typeData['success_rate'] ?? 0;
      
      $output .= "$timestamp,$type,$count,$percentage,$avgTime,$successRate\n";
    }
    
    return $output;
  }

  /**
   * Export au format HTML (dashboard simple)
   */
  public function exportToHtml(array $data): string
  {
    $health = $data['health_report'];
    $statusColor = match($health['overall_health']['status']) {
      'healthy' => '#10b981',
      'warning' => '#f59e0b',
      'degraded' => '#ef4444',
      'critical' => '#dc2626',
      default => '#6b7280',
    };

    $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Monitoring Report</title>
    <style>
        body { 
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            margin: 0;
            padding: 20px;
            background: #f3f4f6;
        }
        .container { 
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .header {
            border-bottom: 2px solid #e5e7eb;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        h1 { 
            margin: 0;
            color: #111827;
            font-size: 28px;
        }
        .timestamp {
            color: #6b7280;
            font-size: 14px;
            margin-top: 8px;
        }
        .health-score {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 30px;
            padding: 20px;
            background: #f9fafb;
            border-radius: 8px;
        }
        .score-circle {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            font-weight: bold;
            color: white;
            background: {$statusColor};
        }
        .score-details h2 {
            margin: 0 0 8px 0;
            font-size: 20px;
            color: #374151;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            background: {$statusColor};
            color: white;
        }
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .metric-card {
            padding: 20px;
            background: #f9fafb;
            border-radius: 8px;
            border-left: 4px solid #3b82f6;
        }
        .metric-card h3 {
            margin: 0 0 12px 0;
            font-size: 14px;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .metric-value {
            font-size: 28px;
            font-weight: bold;
            color: #111827;
        }
        .metric-unit {
            font-size: 14px;
            color: #6b7280;
            margin-left: 4px;
        }
        .section {
            margin-bottom: 30px;
        }
        .section h2 {
            font-size: 20px;
            color: #111827;
            margin-bottom: 16px;
            padding-bottom: 8px;
            border-bottom: 2px solid #e5e7eb;
        }
        .alert-item {
            padding: 12px 16px;
            margin-bottom: 8px;
            border-radius: 6px;
            border-left: 4px solid #ef4444;
            background: #fef2f2;
        }
        .alert-item.warning {
            border-color: #f59e0b;
            background: #fffbeb;
        }
        .alert-item.info {
            border-color: #3b82f6;
            background: #eff6ff;
        }
        .recommendation {
            padding: 12px 16px;
            margin-bottom: 8px;
            border-radius: 6px;
            background: #f0fdf4;
            border-left: 4px solid #10b981;
        }
        .recommendation strong {
            color: #065f46;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 12px;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }
        th {
            background: #f9fafb;
            font-weight: 600;
            color: #374151;
            font-size: 14px;
        }
        td {
            color: #6b7280;
        }
        .trend-up { color: #10b981; }
        .trend-down { color: #ef4444; }
        .trend-stable { color: #6b7280; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔍 System Monitoring Report</h1>
            <div class="timestamp">Generated: {$data['exported_at']}</div>
        </div>

        <div class="health-score">
            <div class="score-circle">{$health['overall_health']['score']}</div>
            <div class="score-details">
                <h2>Overall System Health</h2>
                <span class="status-badge">{$health['overall_health']['status']}</span>
HTML;

    if (!empty($health['overall_health']['issues'])) {
      $html .= '<ul style="margin-top: 12px; color: #6b7280;">';
      foreach ($health['overall_health']['issues'] as $issue) {
        $html .= "<li>{$issue}</li>";
      }
      $html .= '</ul>';
    }

    $html .= <<<HTML
            </div>
        </div>

        <div class="grid">
            <div class="metric-card">
                <h3>Total Requests</h3>
                <div class="metric-value">{$data['system_metrics']['total_requests']}</div>
            </div>
            <div class="metric-card">
                <h3>Error Rate</h3>
                <div class="metric-value">
HTML;

    $errorRate = round($health['system_metrics']['error_rate'] * 100, 2);
    $html .= $errorRate . '<span class="metric-unit">%</span>';

    $html .= <<<HTML
                </div>
            </div>
            <div class="metric-card">
                <h3>Avg Response Time</h3>
                <div class="metric-value">
HTML;

    $avgTime = round($health['system_metrics']['avg_response_time'], 2);
    $html .= $avgTime . '<span class="metric-unit">s</span>';

    $html .= <<<HTML
                </div>
            </div>
            <div class="metric-card">
                <h3>Memory Usage</h3>
                <div class="metric-value">
HTML;

    $memPct = $health['system_metrics']['memory_usage']['percentage'];
    $html .= $memPct . '<span class="metric-unit">%</span>';

    $html .= <<<HTML
                </div>
            </div>
        </div>
HTML;

    // Alertes actives
    if (!empty($data['active_alerts'])) {
      $html .= '<div class="section"><h2>🚨 Active Alerts</h2>';
      foreach ($data['active_alerts'] as $alert) {
        $alertClass = $alert['severity'] === 'high' ? 'alert-item' : 'alert-item warning';
        $html .= "<div class=\"{$alertClass}\"><strong>{$alert['type']}</strong>: {$alert['message']}</div>";
      }
      $html .= '</div>';
    }

    // Recommandations
    if (!empty($health['recommendations'])) {
      $html .= '<div class="section"><h2>💡 Recommendations</h2>';
      foreach ($health['recommendations'] as $rec) {
        $html .= "<div class=\"recommendation\"><strong>{$rec['category']}</strong>: {$rec['message']}</div>";
      }
      $html .= '</div>';
    }

    // Santé des composants
    $html .= '<div class="section"><h2>🔧 Component Health</h2><table><thead><tr><th>Component</th><th>Status</th><th>Total Calls</th><th>Success Rate</th><th>Avg Time</th></tr></thead><tbody>';

    foreach ($health['component_health'] as $comp) {
      $statusColor = $comp['status'] === 'healthy' ? '#10b981' : '#ef4444';
      $metrics = $data['component_metrics'][$comp['name']] ?? [];
      $totalCalls = $metrics['total_calls'] ?? 0;
      $successfulCalls = $metrics['successful_calls'] ?? 0;
      $successRate = $totalCalls > 0 ? round(($successfulCalls / $totalCalls) * 100, 1) : 0;
      $avgTime = round($metrics['avg_execution_time'] ?? 0, 2);

      $html .= "<tr>";
      $html .= "<td>{$comp['name']}</td>";
      $html .= "<td><span style=\"color: {$statusColor}; font-weight: 600;\">{$comp['status']}</span></td>";
      $html .= "<td>{$totalCalls}</td>";
      $html .= "<td>{$successRate}%</td>";
      $html .= "<td>{$avgTime}s</td>";
      $html .= "</tr>";
    }

    $html .= '</tbody></table></div>';

    // Tendances
    if (!empty($health['trends']) && !isset($health['trends']['insufficient_data'])) {
      $html .= '<div class="section"><h2>📈 Trends</h2><table><thead><tr><th>Metric</th><th>Trend</th><th>Change</th><th>Current Value</th></tr></thead><tbody>';

      foreach ($health['trends'] as $metric => $trend) {
        $trendClass = match($trend['trend']) {
          'increasing' => 'trend-up',
          'decreasing' => 'trend-down',
          default => 'trend-stable',
        };
        $trendIcon = match($trend['trend']) {
          'increasing' => '↗',
          'decreasing' => '↘',
          default => '→',
        };

        $html .= "<tr>";
        $html .= "<td>" . ucfirst(str_replace('_', ' ', $metric)) . "</td>";
        $html .= "<td class=\"{$trendClass}\">{$trendIcon} {$trend['trend']}</td>";
        $html .= "<td>{$trend['percent_change']}%</td>";
        $html .= "<td>{$trend['current_value']}</td>";
        $html .= "</tr>";
      }

      $html .= '</tbody></table></div>';
    }

    $html .= <<<HTML
    </div>
</body>
</html>
HTML;

    return $html;
  }

  //*******************
  // Not used
  //*******************

}
