<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\Infrastructure\Monitoring;

use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;
use ClicShopping\AI\Infrastructure\Monitoring\MonitoringAgent;
use ClicShopping\AI\Infrastructure\Monitoring\MetricsCollector;
use ClicShopping\AI\Infrastructure\Monitoring\AlertManager;
use ClicShopping\AI\Infrastructure\Monitoring\StatsAggregator;
/**
 * DocumentationGenerator Class
 *
 * Automatic documentation generator that:
 * - Analyzes classes via Reflection
 * - Generates Markdown documentation
 * - Creates API references
 * - Produces ASCII diagrams
 * - Exports to HTML and PDF
 */
class DocumentationGenerator
{
  private string $projectName = 'ClicShopping AI';
  private string $projectVersion = '1.0.0';
  private array $analyzedClasses = [];

  /**
   * Constructor
   */
  public function __construct(string $projectName = 'ClicShopping AI', string $version = '1.0.0')
  {
    $this->projectName = $projectName;
    $this->projectVersion = $version;
  }

  /**
   * Analyzes a class and generates its documentation
   * 
   * @param string $className Class name
   * @return array Class analysis
   */
  public function analyzeClass(string $className): array
  {
    try {
      $reflection = new ReflectionClass($className);

      $analysis = [
        'name' => $reflection->getName(),
        'short_name' => $reflection->getShortName(),
        'namespace' => $reflection->getNamespaceName(),
        'description' => $this->extractDocComment($reflection->getDocComment()),
        'methods' => [],
        'properties' => [],
        'constants' => [],
      ];

      // Analyze constants
      foreach ($reflection->getConstants() as $name => $value) {
        $analysis['constants'][] = [
          'name' => $name,
          'value' => $value,
        ];
      }

      // Analyze properties
      foreach ($reflection->getProperties() as $property) {
        $analysis['properties'][] = $this->analyzeProperty($property);
      }

      // Analyze methods
      foreach ($reflection->getMethods() as $method) {
        $analysis['methods'][] = $this->analyzeMethod($method);
      }

      $this->analyzedClasses[$className] = $analysis;

      return $analysis;

    } catch (\Exception $e) {
      return [
        'error' => $e->getMessage(),
      ];
    }
  }

  /**
   * Analyzes a property
   * 
   * @param ReflectionProperty $property Property reflection
   * @return array Property analysis
   */
  private function analyzeProperty(ReflectionProperty $property): array
  {
    return [
      'name' => $property->getName(),
      'type' => $property->getType() ? (string)$property->getType() : 'mixed',
      'default' => null,
      'visibility' => $this->getVisibility($property),
      'static' => $property->isStatic(),
      'description' => $this->extractDocComment($property->getDocComment()),
    ];
  }

  /**
   * Analyzes a method
   * 
   * @param ReflectionMethod $method Method reflection
   * @return array Method analysis
   */
  private function analyzeMethod(ReflectionMethod $method): array
  {
    $params = [];
    foreach ($method->getParameters() as $param) {
      $params[] = [
        'name' => $param->getName(),
        'type' => $param->getType() ? (string)$param->getType() : 'mixed',
        'default' => $param->isDefaultValueAvailable() ? $param->getDefaultValue() : null,
        'required' => !$param->isOptional(),
      ];
    }

    return [
      'name' => $method->getName(),
      'description' => $this->extractDocComment($method->getDocComment()),
      'visibility' => $this->getVisibility($method),
      'static' => $method->isStatic(),
      'return_type' => $method->getReturnType() ? (string)$method->getReturnType() : 'mixed',
      'parameters' => $params,
    ];
  }

  /**
   * Extracts DocComment content
   * 
   * @param string|null $docComment DocComment string
   * @return string Extracted content
   */
  private function extractDocComment(?string $docComment): string
  {
    if (!$docComment) {
      return '';
    }

    // Clean DocComment
    $lines = explode("\n", $docComment);
    $content = [];

    foreach ($lines as $line) {
      $line = trim($line);
      $line = preg_replace('/^\/\*+/', '', $line);
      $line = preg_replace('/\*+\/$/', '', $line);
      $line = preg_replace('/^\*\s?/', '', $line);

      if (!empty($line)) {
        $content[] = $line;
      }
    }

    return implode("\n", $content);
  }

  /**
   * Gets visibility of a property/method
   * 
   * @param mixed $member Property or method reflection
   * @return string Visibility (public, protected, private)
   */
  private function getVisibility($member): string
  {
    if ($member->isPublic()) return 'public';
    if ($member->isProtected()) return 'protected';
    if ($member->isPrivate()) return 'private';
    return 'unknown';
  }

  /**
   * Generates Markdown documentation
   * 
   * @return string Markdown documentation
   */
  public function generateMarkdown(): string
  {
    $md = [];

    // Header
    $md[] = "# {$this->projectName}";
    $md[] = "**Version:** {$this->projectVersion}";
    $md[] = "";
    $md[] = "## Overview";
    $md[] = "Documentation auto-générée pour les composants du système de monitoring.";
    $md[] = "";

    // Table of contents
    $md[] = "## Table of Contents";
    $md[] = "";
    foreach (array_keys($this->analyzedClasses) as $className) {
      $shortName = (new ReflectionClass($className))->getShortName();
      $anchor = strtolower(str_replace('\\', '-', $className));
      $md[] = "- [{$shortName}](#{$anchor})";
    }
    $md[] = "";

    // Classes
    foreach ($this->analyzedClasses as $className => $analysis) {
      $md = array_merge($md, $this->generateClassMarkdown($analysis));
    }

    return implode("\n", $md);
  }

  /**
   * Generates class Markdown documentation
   * 
   * @param array $analysis Class analysis
   * @return array Markdown lines
   */
  private function generateClassMarkdown(array $analysis): array
  {
    $md = [];

    $anchor = strtolower(str_replace('\\', '-', $analysis['name']));
    $md[] = "## {$analysis['short_name']}";
    $md[] = "";

    if (!empty($analysis['description'])) {
      $md[] = $analysis['description'];
      $md[] = "";
    }

    $md[] = "**Namespace:** `{$analysis['namespace']}`";
    $md[] = "";

    // Properties
    if (!empty($analysis['properties'])) {
      $md[] = "### Properties";
      $md[] = "";
      $md[] = "| Name | Type | Visibility | Description |";
      $md[] = "|------|------|------------|-------------|";

      foreach ($analysis['properties'] as $prop) {
        $static = $prop['static'] ? ' (static)' : '';
        $md[] = "| `{$prop['name']}{$static}` | {$prop['type']} | {$prop['visibility']} | {$prop['description']} |";
      }

      $md[] = "";
    }

    // Methods
    if (!empty($analysis['methods'])) {
      $md[] = "### Methods";
      $md[] = "";

      foreach ($analysis['methods'] as $method) {
        $signature = $this->generateMethodSignature($method);
        $md[] = "#### `{$signature}`";
        $md[] = "";

        if (!empty($method['description'])) {
          $md[] = $method['description'];
          $md[] = "";
        }

        // Parameters
        if (!empty($method['parameters'])) {
          $md[] = "**Parameters:**";
          foreach ($method['parameters'] as $param) {
            $required = $param['required'] ? '(required)' : '(optional)';
            $md[] = "- `{$param['name']}` ({$param['type']}) {$required}";
            if ($param['default'] !== null) {
              $md[] = "  - Default: `{$param['default']}`";
            }
          }
          $md[] = "";
        }

        // Return type
        $md[] = "**Returns:** `{$method['return_type']}`";
        $md[] = "";
      }
    }

    return $md;
  }

  /**
   * Generates method signature
   * 
   * @param array $method Method data
   * @return string Method signature
   */
  private function generateMethodSignature(array $method): string
  {
    $params = [];
    foreach ($method['parameters'] as $param) {
      $required = $param['required'] ? '' : '?';
      $params[] = "{$required}{$param['type']} \${$param['name']}";
    }

    $paramStr = implode(', ', $params);

    return "{$method['name']}({$paramStr}): {$method['return_type']}";
  }

  /**
   * Generates API reference in HTML
   * 
   * @return string HTML documentation
   */
  public function generateHTML(): string
  {
    $markdown = $this->generateMarkdown();
    $htmlContent = $this->markdownToHTML($markdown);

    $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$this->projectName} - API Reference</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            line-height: 1.6;
            color: #333;
            background: #f5f5f5;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px 20px;
            border-radius: 8px;
            margin-bottom: 40px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        header h1 {
            font-size: 32px;
            margin-bottom: 10px;
        }
        header .version {
            opacity: 0.9;
            font-size: 14px;
        }
        main {
            background: white;
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h2 {
            color: #667eea;
            margin-top: 40px;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #667eea;
        }
        h3 {
            color: #764ba2;
            margin-top: 30px;
            margin-bottom: 15px;
        }
        h4 {
            color: #555;
            margin-top: 20px;
            margin-bottom: 10px;
            font-size: 16px;
        }
        code {
            background: #f4f4f4;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
            font-size: 14px;
        }
        pre {
            background: #2d2d2d;
            color: #f8f8f2;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
            margin: 15px 0;
        }
        pre code {
            background: none;
            padding: 0;
            color: inherit;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background: #f9f9f9;
            font-weight: 600;
            color: #333;
        }
        tr:hover {
            background: #f9f9f9;
        }
        ul, ol {
            margin: 15px 0 15px 30px;
        }
        li {
            margin-bottom: 8px;
        }
        .toc {
            background: #f9f9f9;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 30px;
        }
        .toc h3 {
            margin-top: 0;
        }
        .toc ul {
            margin-bottom: 0;
        }
        .toc a {
            color: #667eea;
            text-decoration: none;
        }
        .toc a:hover {
            text-decoration: underline;
        }
        footer {
            text-align: center;
            padding: 20px;
            color: #666;
            font-size: 14px;
            margin-top: 40px;
        }
    </style>
</head>
<body>
    <header>
        <h1>{$this->projectName}</h1>
        <div class="version">Version {$this->projectVersion}</div>
    </header>

    <div class="container">
        <main>
            {$htmlContent}
        </main>

        <footer>
            <p>Generated automatically with DocumentationGenerator</p>
            <p>© 2024 ClicShopping</p>
        </footer>
    </div>
</body>
</html>
HTML;

    return $html;
  }

  /**
   * Converts Markdown to simple HTML
   * 
   * @param string $markdown Markdown content
   * @return string HTML content
   */
  private function markdownToHTML(string $markdown): string
  {
    $html = htmlspecialchars($markdown);

    // Headers
    $html = preg_replace('/^## (.*?)$/m', '<h2>$1</h2>', $html);
    $html = preg_replace('/^### (.*?)$/m', '<h3>$1</h3>', $html);
    $html = preg_replace('/^#### (.*?)$/m', '<h4>$1</h4>', $html);

    // Bold
    $html = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $html);

    // Italic
    $html = preg_replace('/\*(.*?)\*/', '<em>$1</em>', $html);

    // Code inline
    $html = preg_replace('/`(.*?)`/', '<code>$1</code>', $html);

    // Lists
    $html = preg_replace('/^\- (.*?)$/m', '<li>$1</li>', $html);
    $html = preg_replace('/(<li>.*?<\/li>)/s', '<ul>$1</ul>', $html);

    // Tables
    $html = preg_replace('/\|(.*?)\|/m', function($matches) {
      $cells = array_map('trim', explode('|', $matches[1]));
      return '<tr><td>' . implode('</td><td>', $cells) . '</td></tr>';
    }, $html);

    // Paragraphs
    $html = preg_replace('/\n\n+/', '</p><p>', $html);
    $html = '<p>' . $html . '</p>';

    // Line breaks
    $html = str_replace("\n", "<br>\n", $html);

    return $html;
  }

  /**
   * Generates architecture diagram in ASCII
   * 
   * @return string ASCII diagram
   */
  public function generateArchitectureDiagram(): string
  {
    return <<<ASCII
╔====================================================================╗
║                      ClicShopping AI - Architecture                ║
╚====================================================================╝

+-----------------------------------------------------------------+
|                     OrchestratorAgent                           |
|            (Entry point for all queries)                        |
+--------------------┬--------------------------------------------+
                     |
        +------------┼------------┬--------------+
        |            |            |              |
        ▼            ▼            ▼              ▼
    +--------+  +--------+  +----------+  +----------+
    |Planner |  |Executor|  |Reasoning |  |Correction|
    +--------+  +--------+  +----------+  +----------+
        |            |            |              |
        +------------┼------------┴--------------+
                     |
        +------------┴------------+
        |                         |
        ▼                         ▼
    +--------------+      +-------------+
    |   RAG Stack  |      |  Analytics  |
    |              |      |   Engine    |
    | - Semantic   |      |             |
    | - Vector DB  |      | - SQL Query |
    | - Cache      |      | - Analytics |
    +--------------+      +-------------+

+-----------------------------------------------------------------+
|                  Monitoring Layer (Phase 4)                      |
├-----------------------------------------------------------------┤
|  +--------------+  +--------------+  +--------------+          |
|  |  Collector   |→ |  Monitoring  |→ |  Aggregator  |          |
|  |  (Metrics)   |  |  (Health)    |  |  (Reports)   |          |
|  +--------------+  +--------------+  +--------------+          |
|                           |                                      |
|                           ▼                                      |
|                  +------------------+                           |
|                  |  AlertManager    |                           |
|                  |  (Rules & Events)|                           |
|                  +------------------+                           |
+-----------------------------------------------------------------+

Data Flow:
  Events → Collector → Monitoring → Alerts
           |              |
           +------→ Aggregator → Reports
ASCII;
  }

  /**
   * Generates best practices guide
   * 
   * @return string Best practices guide
   */
  public function generateBestPractices(): string
  {
    return <<<MARKDOWN
# Best Practices Guide

## 1. Monitoring

### Record important events
- Always record before/after each critical operation
- Include tags for grouping (component, operation_type, etc.)
- Measure execution times with startTimer/stopTimer

### Alert configuration
- Start with few rules, add progressively
- Avoid false positives by adjusting thresholds
- Configure escalation for critical alerts
- Test notification channels (email, Slack, etc.)

## 2. Performance

### Collection optimization
- Use buffering (bufferSize > 1)
- Flush cache regularly (flush())
- Clean old metrics (cleanOldMetrics())
- Disable debug in production

### Aggregation
- Increase cache lifetime in production (300-600s)
- Limit history (maxHistorySize)
- Parallelize data queries

## 3. Analyse des données

### Report interpretation
- Check trends, not just current values
- Correlate metrics (errors + latency)
- Investigate anomalies immediately
- Document incidents and causes

### Escalation
- Automate escalation for critical issues
- Notify the right people at the right time
- Update runbooks with solutions

## 4. Security

### Sensitive data
- Do not log user data
- Encrypt sensitive metrics
- Limit access to reports
- Audit trail for modifications

### API Security
- Validate all alert parameters
- Limit API request rate
- Monitor suspicious API usage
- Rotate keys regularly

## 5. Troubleshooting

### Debugging
- Enable debug mode for development
- Use detailed logs
- Export metrics for offline analysis
- Compare periods (before/after)

### Common issues

**High memory**
- Reduce maxHistorySize
- Flush buffer more often
- Clean old metrics

**Missed alerts**
- Check that rules are enabled
- Test condition manually
- Check notification channels

**Degraded performance**
- Increase cache lifetime
- Reduce collection frequency
- Parallelize operations

MARKDOWN;
  }

  /**
   * Generates complete API reference
   * 
   * @return string API reference
   */
  public function generateAPIReference(): string
  {
    return <<<MARKDOWN
# API Reference

## MonitoringAgent

### Register components
\`\`\`php
$monitoring->registerComponent(string $componentName, object $component, array $metricsToTrack = []): void
\`\`\`

### Collect metrics
\`\`\`php
$metrics = $monitoring->collectMetrics(): array
\`\`\`

### Rapports
\`\`\`php
$health = $monitoring->getHealthReport(): array
$summary = $monitoring->getQuickSummary(): array
\`\`\`

## MetricsCollector

### Timers
\`\`\`php
$collector->startTimer(string $name, array $tags = []): void
$elapsed = $collector->stopTimer(string $name): ?float
\`\`\`

### Compteurs
\`\`\`php
$collector->increment(string $name, int $value = 1, array $tags = []): void
$collector->decrement(string $name, int $value = 1, array $tags = []): void
\`\`\`

### Gauges
\`\`\`php
$collector->gauge(string $name, float $value, array $tags = []): void
\`\`\`

### Histogrammes
\`\`\`php
$collector->recordHistogram(string $name, float $value, array $tags = []): void
$stats = $collector->getHistogramStats(string $name, array $tags = []): ?array
\`\`\`

## AlertManager

### Règles
\`\`\`php
$alertManager->addRule(string $ruleId, array $ruleConfig): void
$alertManager->enableRule(string $ruleId): bool
$alertManager->disableRule(string $ruleId): bool
\`\`\`

### Alertes
\`\`\`php
$triggered = $alertManager->triggerAlert(string $alertId, array $alertData): bool
$alertManager->acknowledgeAlert(string $alertId, string $acknowledgedBy = 'system'): bool
$alertManager->resolveAlert(string $alertId, string $resolution = ''): bool
\`\`\`

### Notifications
\`\`\`php
$alertManager->addNotificationChannel(string $channelName, callable $handler): void
\`\`\`

## StatsAggregator

### Agrégation
\`\`\`php
$aggregated = $aggregator->aggregate(): array
$report = $aggregator->getFullReport(): array
$summary = $aggregator->getExecutiveSummary(): array
\`\`\`

### Export
\`\`\`php
$json = $aggregator->exportJSON(): string
$csv = $aggregator->exportCSV(): string
\`\`\`

MARKDOWN;
  }

  /**
   * Exports complete documentation
   * 
   * @param string $outputDir Output directory
   * @return array Export results
   */
  public function exportComplete(string $outputDir): array
  {
    $results = [];

    // Markdown
    $markdownFile = $outputDir . '/API_REFERENCE.md';
    file_put_contents($markdownFile, $this->generateMarkdown());
    $results['markdown'] = $markdownFile;

    // HTML
    $htmlFile = $outputDir . '/index.html';
    file_put_contents($htmlFile, $this->generateHTML());
    $results['html'] = $htmlFile;

    // Best Practices
    $practicesFile = $outputDir . '/BEST_PRACTICES.md';
    file_put_contents($practicesFile, $this->generateBestPractices());
    $results['practices'] = $practicesFile;

    // API Reference
    $apiFile = $outputDir . '/API.md';
    file_put_contents($apiFile, $this->generateAPIReference());
    $results['api'] = $apiFile;

    // Architecture Diagram
    $diagramFile = $outputDir . '/ARCHITECTURE.txt';
    file_put_contents($diagramFile, $this->generateArchitectureDiagram());
    $results['diagram'] = $diagramFile;

    return $results;
  }

  /**
   * Generates complete README
   * 
   * @return string README content
   */
  public function generateREADME(): string
  {
    return <<<MARKDOWN
# {$this->projectName} - Phase 4: Monitoring & Analytics

## Overview

Phase 4 implements a complete monitoring system, intelligent alerting and analytical reports for the ClicShopping AI multi-agent system.

## Components

### 1. MonitoringAgent
Centralized agent that collects and aggregates metrics from all system components. Provides system health overview with anomaly detection.

**Responsibilities:**
- Collect metrics from each component
- Calculate overall system health
- Generate reports
- Detect anomalies

### 2. MetricsCollector
Real-time metrics collector with support for timers, counters, gauges and histograms. Uses buffering for performance.

**Responsibilities:**
- Collect timers (duration measurement)
- Increment/decrement counters
- Record gauges (instantaneous values)
- Create histograms for distributions

### 3. AlertManager
Configurable alert manager with support for custom rules, multiple notification channels and automatic escalation.

**Responsibilities:**
- Manage alert rules
- Trigger and group alerts
- Notify via multiple channels
- Automatic escalation
- Alert history

### 4. StatsAggregator
Statistics aggregator that combines data from multiple sources and generates synthetic reports with trend detection.

**Responsibilities:**
- Aggregate data from multiple sources
- Calculate consolidated metrics
- Detect trends and anomalies
- Generate executive reports

## Quick Start

### Installation

1. Copier les 4 fichiers PHP dans votre projet
2. Initialiser les composants:

\`\`\`php
use ClicShopping\\Apps\\Configuration\\ChatGpt\\Classes\\Tools\\Monitoring\\{
  MonitoringAgent,
  MetricsCollector,
  AlertManager,
  StatsAggregator
};

$monitoring = new MonitoringAgent();
$collector = new MetricsCollector($monitoring);
$alertManager = new AlertManager();
$aggregator = new StatsAggregator();
\`\`\`

### Register components

\`\`\`php
$monitoring->registerComponent('OrchestratorAgent', $orchestrator);
$monitoring->registerComponent('TaskPlanner', $planner);
$monitoring->registerComponent('PlanExecutor', $executor);
\`\`\`

### Collect metrics

\`\`\`php
$collector->startTimer('operation');
// ... do something ...
$collector->stopTimer('operation');

$collector->increment('requests_total');
$collector->gauge('memory_usage_mb', memory_get_usage(true) / 1024 / 1024);
\`\`\`

### Configure alerts

\`\`\`php
$alertManager->addRule('high_error_rate', [
  'message' => 'Error rate exceeded 10%',
  'severity' => 'error',
  'condition' => fn(\$m) => (\$m['error_rate'] ?? 0) > 0.1,
  'channels' => ['log', 'email'],
]);
\`\`\`

### Generate reports

\`\`\`php
$health = $monitoring->getHealthReport();
$summary = $aggregator->getExecutiveSummary();
$html = $monitoring->exportMetrics('html');
\`\`\`

## Configuration

### Development
\`\`\`php
define('CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER', 'True');
$alertManager->setCooldownPeriod(60);
\`\`\`

### Production
\`\`\`php
define('CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER', 'False');
$alertManager->setCooldownPeriod(1800);
$collector->bufferSize = 50;
\`\`\`

## Documentation

- [API Reference](API.md) - Complete API documentation
- [Best Practices](BEST_PRACTICES.md) - Best practices guide
- [Architecture](ARCHITECTURE.txt) - Architecture diagram
- [Integration Guide](INTEGRATION_GUIDE.md) - Detailed integration guide

## Support

For any question or issue:
1. Consult the [documentation](.)
2. Check logs with debug enabled
3. Export metrics for offline analysis

## License

Licensed under AGPLv3 or commercial license.
 See LICENSE file.
MARKDOWN;
  }
}