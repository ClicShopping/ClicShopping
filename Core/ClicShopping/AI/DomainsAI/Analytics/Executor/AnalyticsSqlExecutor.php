<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\DomainsAI\Analytics\Executor;

use ClicShopping\AI\Config\DomainConfig;
use ClicShopping\AI\DomainsAI\Analytics\Helper\AnalyticsErrorHandler;
use ClicShopping\AI\DomainsAI\Analytics\Validator\AggregateSourceFilter;
use ClicShopping\AI\DomainsAI\Analytics\Validator\CompareWindowFilter;
use ClicShopping\AI\DomainsAI\Analytics\Validator\MetricWeightFilter;
use ClicShopping\AI\DomainsAI\Analytics\Validator\SensitiveOutputFilter;
use ClicShopping\AI\DomainsAI\DomainRegistry;
use ClicShopping\AI\Infrastructure\Orm\DoctrineOrm;
use ClicShopping\AI\Infrastructure\Schema\SchemaEmbedder;
use ClicShopping\AI\Security\InputValidator;
use ClicShopping\AI\Security\SecurityLogger;
use ClicShopping\OM\Cache as OMCache;
use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;

/**
 * AnalyticsSqlExecutor — execution stage of AnalyticsAgent: validates, guards against the plan
 * contract, executes, corrects on failure and caches the generated SQL. Generation stays on the agent.
 * Stateless per request: the plan and the SQL cache key are passed per call.
 *
 * @package ClicShopping\AI\DomainsAI\Analytics\Executor
 */
class AnalyticsSqlExecutor
{
  public function __construct(
    private SqlQueryProcessor $queryProcessor,
    private QueryExecutor $queryExecutor,
    private AnalyticsErrorHandler $errorHandler,
    private SecurityLogger $securityLogger,
    private bool $debug = false
  ) {
  }

  /**
   * Replace the cached SQL of the query in flight with the one that actually worked.
   *
   * generateSqlQueries() caches the FIRST DRAFT, before execution. When that draft turns out to
   * be wrong and gets corrected, the correction used to be lost: the result cache kept the fixed
   * SQL, the SQL cache kept the broken one, and the same question replayed the faulty draft while
   * paying for the correction all over again (BACKLOG lot A). Both caches now share one freshness
   * rule (see cachedSqlIsFresh), so they can no longer disagree on what is stale — but they can
   * still disagree on CONTENT, which is what this method fixes.
   *
   * @param string $correctedSql The query that executed successfully
   * @param string|null $sqlCacheKey Key of the SQL cache entry in flight, null when none
   * @return void
   */
  private function promoteCorrectedSqlToCache(string $correctedSql, ?string $sqlCacheKey): void
  {
    if ($sqlCacheKey === null || trim($correctedSql) === '') {
      return;
    }

    (new OMCache($sqlCacheKey, 'Rag/SQL'))->save($correctedSql);
    $this->debugLog("  SQL cache updated with the corrected query", "CACHE");
  }

  /**
   * A comparison plan reads two windows: a WHERE that keeps one of them zeroes the other side
   * in silence. Widened when the range is readable, reported otherwise (0 LLM call).
   *
   * @param string $sql Executable SQL
   * @param array|null $plan Validated analysis plan, null when none
   * @return string The SQL, its WHERE widened to both windows when needed
   */
  private function admitBothCompareWindows(string $sql, ?array $plan): string
  {
    $check = CompareWindowFilter::check($sql, $plan['periods'] ?? []);

    if (!$check['flagged']) {
      return $sql;
    }

    $this->debugLog("COMPARE WINDOW " . ($check['corrected'] ? 'widened' : 'NOT widened') . ": " . $check['reason'], "VALIDATION");

    if (!$check['corrected']) {
      $this->securityLogger->logSecurityEvent('Comparison SQL filters out a plan window: ' . $check['reason'], 'warning');
    }

    return $check['sql'];
  }

  /**
   * Deterministic plan-vs-SQL contract: a `weighted_by` metric keeps its weight-1 rows, and a
   * comparison reads no date outside its windows. Throws: before execution it routes to the
   * correction path, after a correction it fails.
   *
   * @param string $sql SQL about to be executed, or the corrected one
   * @param array|null $plan Validated analysis plan, null when none
   * @return void
   * @throws \Exception When the SQL breaches the plan
   */
  private function assertPlanContract(string $sql, ?array $plan): void
  {
    $plan = $plan ?? [];
    $domainApp = DomainRegistry::getInstance()->getActiveApp();
    $catalog = ($domainApp !== null && method_exists($domainApp, 'getMetricCatalog')) ? $domainApp->getMetricCatalog() : [];
    $weights = MetricWeightFilter::violations(
      $sql,
      $plan['metrics'] ?? [],
      $catalog,
      ($domainApp !== null && method_exists($domainApp, 'getPopulationPinColumns')) ? $domainApp->getPopulationPinColumns() : [],
      ($plan['filters'] ?? []) !== []
    );
    $dates = CompareWindowFilter::foreignDates($sql, $plan);
    $sources = AggregateSourceFilter::violations(
      $sql,
      ($domainApp !== null && method_exists($domainApp, 'getForbiddenAggregateSources')) ? $domainApp->getForbiddenAggregateSources() : []
    );

    if ($weights === [] && $dates === [] && $sources === []) {
      return;
    }

    DomainConfig::loadAgnosticLanguageFile('rag_sql_correction');
    $language = Registry::get('Language');
    $messages = [];

    if ($weights !== []) {
      $messages[] = $language->getDef('text_weight_contract_error', [
        'metrics' => implode(', ', array_keys($weights)),
        'column' => implode(', ', array_unique($weights)),
      ]);
    }

    if ($dates !== []) {
      $messages[] = $language->getDef('text_window_contract_error', [
        'dates' => implode(', ', $dates),
        'windows' => ($plan['periods']['current']['from'] ?? '') . '..' . ($plan['periods']['current']['to'] ?? '')
          . ', ' . ($plan['periods']['previous']['from'] ?? '') . '..' . ($plan['periods']['previous']['to'] ?? ''),
      ]);
    }

    if ($sources !== []) {
      $messages[] = $language->getDef('text_aggregate_source_contract_error', [
        'sources' => implode(', ', $sources),
      ]);
    }

    $message = implode(' ', $messages);
    $this->debugLog("PLAN CONTRACT violated: " . $message, "VALIDATION");

    throw new \Exception($message);
  }

  /**
   * STEP 3: execute each generated SQL query (with validation, intelligent correction on
   * failure, and result caching), interpret and assemble the analytics response. Extracted
   * verbatim from AnalyticsAgent::processAnalyticsQuery. Throws on unrecoverable execution failure.
   *
   * @param array $sqlQueries Generated SQL queries (STEP 2)
   * @param string $question Original question
   * @param array $ambiguityMetadata Ambiguity metadata echoed into the response
   * @param array|null $plan Validated analysis plan, null when none
   * @param string|null $sqlCacheKey Key of the SQL cache entry in flight, null when none
   * @return array The assembled analytics_results response
   */
  public function executeSqlQueries(array $sqlQueries, string $question, array $ambiguityMetadata, ?array $plan, ?string $sqlCacheKey): array
  {
    $results = [];
    $correctionLog = [];

    foreach ($sqlQueries as $idx => $sqlQuery) {
      $this->debugLog("Processing SQL query " . ($idx + 1), "EXECUTION");
      $this->debugLog("Original: " . substr($sqlQuery, 0, 150) . "...", "EXECUTION");

      $resolvedQuery = $this->queryProcessor->resolvePlaceholders($sqlQuery);
      $this->debugLog("After placeholder resolution: " . substr($resolvedQuery, 0, 150) . "...", "EXECUTION");

      $likeValidation = $this->queryProcessor->validateLikePatterns($resolvedQuery);
      if (!empty($likeValidation['warnings'])) {
        $this->debugLog("LIKE pattern warnings: " . count($likeValidation['warnings']), "VALIDATION");

        // Log warnings using security logger
        foreach ($likeValidation['warnings'] as $warning) {
          $this->securityLogger->logSecurityEvent(
            "LIKE pattern validation warning: " . $warning,
            'warning',
            [
              'sql_snippet' => substr($resolvedQuery, 0, 200),
              'like_count' => $likeValidation['like_count'],
              'patterns' => $likeValidation['patterns']
            ]
          );
        }

        // Log suggestions if available
        if (!empty($likeValidation['suggestions'])) {
          $this->debugLog("Suggestions: " . implode('; ', $likeValidation['suggestions']), "VALIDATION");
        }
      } else {
        $this->debugLog("LIKE pattern validation: PASSED (" . $likeValidation['like_count'] . " patterns checked)", "VALIDATION");
      }

      $validation = InputValidator::validateSqlQuery($resolvedQuery);
      $this->debugLog("SQL validation: " . ($validation['valid'] ? 'VALID' : 'INVALID'), "VALIDATION");

      if (!$validation['valid']) {
        $this->debugLog("  Validation issues: " . implode(', ', $validation['issues']));
        continue;
      }

      $finalQuery = $resolvedQuery;
      $finalQuery = $this->queryProcessor->fixDateFilters($finalQuery);
      // Schema-level guard: never GROUP BY a GDPR-encrypted column (shatters aggregation).
      $finalQuery = $this->queryProcessor->fixEncryptedGroupBy($finalQuery);
      $finalQuery = $this->admitBothCompareWindows($finalQuery, $plan);

      $this->debugLog("  Final query to execute: " . substr($finalQuery, 0, 150) . "...");

      try {
        $this->debugLog("  Executing query...");
        $this->assertPlanContract($finalQuery, $plan);
        $executionResult = $this->queryExecutor->execute($finalQuery);

        if (!$executionResult['success']) {
          throw new \Exception($executionResult['error'] ?? 'Query execution failed');
        }

        $queryResults = $executionResult['data'];

        $this->debugLog("  Query executed successfully!");
        $this->debugLog("  Rows returned: " . count($queryResults));

        if (!empty($queryResults)) {
          $this->debugLog("  First row keys: " . implode(', ', array_keys($queryResults[0])));
          $this->debugLog("  First row preview: " . json_encode(array_slice($queryResults[0], 0, 3)));
        }

        // Extract entity_id using QueryExecutor
        $entityInfo = $this->queryExecutor->extractEntityIdFromResults($queryResults);
        $entityId = $entityInfo['entity_id'];
        $entityType = $entityInfo['entity_type'];

        if ($entityId !== null) {
          $this->debugLog("  Entity extracted: ID={$entityId}, Type={$entityType}");
        }

        $results = [
          'type' => 'analytics_results',
          'query' => $question,
          'sql_query' => $finalQuery,
          'original_sql_query' => $sqlQuery,
          'corrections' => $correctionLog,
          'results' => $queryResults,
          'count' => count($queryResults),
          'entity_id' => $entityId,
          'entity_type' => $entityType,
          ...$ambiguityMetadata,
        ];
        // No QueryCache write here: AnalyticsAgent caches after guardSensitiveOutput(), capped rows only.
      } catch (\Exception $e) {
        $this->debugLog("  QUERY EXECUTION FAILED: " . $e->getMessage());
        $this->debugLog("  Attempting intelligent correction...");

        $correctionResult = $this->errorHandler->attemptIntelligentCorrection($e, $finalQuery, $sqlQuery, $question);

        if ($correctionResult['success']) {
          $this->debugLog("  Correction successful!");

          // Use the corrected data as the main result (not append to array)
          $correctedData = $correctionResult['data'];

          // A correction still in breach of the plan contract is refused, never served.
          $this->assertPlanContract((string)($correctedData['executed_query'] ?? ''), $plan);

          // Extract entity info from corrected results
          $entityInfo = $this->queryExecutor->extractEntityIdFromResults($correctedData['results']);

          $results = [
            'type' => 'analytics_results',
            'query' => $question,
            'sql_query' => $correctedData['executed_query'],
            'original_sql_query' => $sqlQuery,
            'corrections' => $correctedData['corrections'] ?? [],
            'results' => $correctedData['results'],
            'count' => count($correctedData['results']),
            'entity_id' => $entityInfo['entity_id'],
            'entity_type' => $entityInfo['entity_type'],
            ...$ambiguityMetadata,
          ];

          if (!empty($correctedData['results'])) {
            $this->promoteCorrectedSqlToCache($correctedData['executed_query'], $sqlCacheKey);
          }
        } elseif (!empty($correctionResult['empty_after_correction'])) {
          // Corrected query ran but returned 0 rows: render an honest empty result (like a
          // legitimately-empty query), never a false "correction succeeded" on a broken query.
          $this->debugLog("  Correction returned 0 rows — honest empty result (not a success).");

          $results = [
            'type' => 'analytics_results',
            'query' => $question,
            'sql_query' => $correctionResult['executed_query'] ?? $finalQuery,
            'original_sql_query' => $sqlQuery,
            'corrections' => $correctionResult['corrections'] ?? $correctionLog,
            'results' => [],
            'count' => 0,
            'entity_id' => null,
            'entity_type' => null,
            ...$ambiguityMetadata,
          ];
        } else {
          $this->debugLog("  Correction failed");
          throw new \Exception("Execution failed after intelligent correction attempt: " . $e->getMessage());
        }
      }
    }

    // Every candidate was skipped by validation: returning [] here made the caller report an empty
    // result set, i.e. "no data" for a question no query ever asked.
    if ($results === []) {
      $this->debugLog("  No candidate SQL passed validation — nothing was executed", "EXECUTION");
      throw new \Exception('No generated SQL query passed validation');
    }

    $this->debugLog("\n" . "." . str_repeat(".", 99) . "\n");
    return $results;
  }

  /**
   * Validate and fix SQL date logic, re-executing the corrected query when needed
   * (moved verbatim from AnalyticsAgent::processBusinessQuery).
   */
  public function validateAndReexecuteSqlDates(array $results, string $question): array
  {
    if (isset($results['sql_query']) && !empty($results['sql_query'])) {
      $dateValidator = new \ClicShopping\AI\DomainsAI\Analytics\Validator\SqlDateValidator($this->debug);
      $dateValidation = $dateValidator->validateAndFix($results['sql_query'], $question);

      if ($dateValidation['corrected']) {
        $this->debugLog(" SQL date logic corrected in processBusinessQuery: " . $dateValidation['reason']);

        // Update the SQL in results
        $results['original_sql_query'] = $results['sql_query'];
        $results['sql_query'] = $dateValidation['sql'];

        // Re-execute the corrected SQL
        $this->debugLog(" Re-executing corrected SQL...");
        try {
          $executionResult = $this->queryExecutor->execute($dateValidation['sql']);

          if ($executionResult['success']) {
            $results['results'] = $executionResult['data'];
            $results['count'] = count($executionResult['data']);
            $this->debugLog("✅ Corrected SQL executed successfully, returned " . $results['count'] . " rows");

            // Clear cached interpretation so it gets regenerated with new results
            if (isset($results['interpretation'])) {
              unset($results['interpretation']);
              $this->debugLog("Cleared cached interpretation to force regeneration with corrected results");
            }
          } else {
            $this->debugLog("⚠️  Corrected SQL execution failed: " . ($executionResult['error'] ?? 'unknown'));
          }
        } catch (\Exception $e) {
          $this->debugLog("⚠️  Error re-executing corrected SQL: " . $e->getMessage());
        }
      }
    }

    return $results;
  }

  /**
   * A result that SERVES a column its table declares `ai_sensitive_contact` is a listing of people:
   * capped to the active domain's limit. Every sensitive column served is traced, never blocked.
   * Call once per request on what is about to be served, cache replays included.
   *
   * @param array $results Executed (or replayed) analytics results
   * @param string $question Question being answered
   * @param string $userId Caller, for the trace and the cumulated volume
   * @return array The results, capped and flagged `sensitive_output` when they serve sensitive columns
   */
  public function guardSensitiveOutput(array $results, string $question, string $userId): array
  {
    $domainApp = DomainRegistry::getInstance()->getActiveApp();

    if ($domainApp === null || !method_exists($domainApp, 'getSensitiveDataPolicy')) {
      return $results;
    }

    $policy = $domainApp->getSensitiveDataPolicy();

    if (is_array($results['interpretation_results'] ?? null)) {
      foreach ($results['interpretation_results'] as $i => $interpretation) {
        $results['interpretation_results'][$i] = $this->capSensitiveRows($interpretation, $question, $userId, $policy);
      }

      return $results;
    }

    return $this->capSensitiveRows($results, $question, $userId, $policy);
  }

  /**
   * @param array $result One result set carrying `sql_query` and `results`
   * @param string $question Question being answered
   * @param string $userId Caller
   * @param array{cap: int, escalation_rows: int, window_hours: int} $policy Domain policy
   * @return array
   */
  private function capSensitiveRows(array $result, string $question, string $userId, array $policy): array
  {
    $sql = (string)($result['sql_query'] ?? '');
    $rows = $result['results'] ?? null;

    if ($sql === '' || !is_array($rows) || $rows === []) {
      return $result;
    }

    $served = SensitiveOutputFilter::served($sql, SchemaEmbedder::declaredSensitiveColumns(), (string)CLICSHOPPING::getConfig('db_table_prefix'));

    if ($served['contact'] === [] && $served['identity'] === []) {
      return $result;
    }

    $level = $served['contact'] !== [] ? 'contact' : 'identity';
    $total = count($rows);
    $capped = $level === 'contact' && $total > max(1, $policy['cap']);

    if ($capped) {
      $result['results'] = array_slice($rows, 0, max(1, $policy['cap']));
      $result['count'] = count($result['results']);
    }

    $result['sensitive_output'] = [
      'level' => $level,
      'columns' => [...$served['contact'], ...$served['identity']],
      'served' => count($result['results']),
      'total' => $total,
      'capped' => $capped,
    ];

    $this->traceSensitiveOutput($result['sensitive_output'], $question, $userId, $policy);

    return $result;
  }

  /**
   * One security event per sensitive answer; contact rows cumulated over the window escalate it,
   * which is what a "the next 30" pagination runs into.
   *
   * @param array $output The `sensitive_output` verdict
   * @param string $question Question being answered
   * @param string $userId Caller
   * @param array{cap: int, escalation_rows: int, window_hours: int} $policy Domain policy
   * @return void
   */
  private function traceSensitiveOutput(array $output, string $question, string $userId, array $policy): void
  {
    $threatType = 'sensitive_' . $output['level'];
    $severity = $output['level'] === 'contact' ? 'medium' : 'low';
    $cumulative = null;

    if ($output['level'] === 'contact') {
      try {
        $previous = (int)DoctrineOrm::selectValue("
          SELECT COALESCE(SUM(JSON_VALUE(metadata, '$.served')), 0)
          FROM " . CLICSHOPPING::getConfig('db_table_prefix') . "rag_security_events
          WHERE user_id = ?
            AND threat_type IN ('sensitive_contact', 'sensitive_contact_bulk')
            AND created_at >= NOW() - INTERVAL ? HOUR
        ", [$userId, $policy['window_hours']]);
      } catch (\Exception $e) {
        $previous = 0;
        $this->securityLogger->logApplicationError('Sensitive output: cumulated volume unreadable: ' . $e->getMessage());
      }

      $cumulative = $previous + $output['served'];

      if ($cumulative > $policy['escalation_rows']) {
        $threatType = 'sensitive_contact_bulk';
        $severity = 'high';
      }
    }

    $this->securityLogger->logEvent('query_allowed', [
      'severity' => $severity,
      'threat_type' => $threatType,
      'query' => $question,
      'action_taken' => 'flagged',
      'detection_method' => 'response_validation',
      'detection_layer' => 'SensitiveOutputFilter',
      'user_id' => $userId,
      'request_type' => 'analytics',
      'agent_used' => 'analytics_agent',
      'metadata' => [
        ...$output,
        'cumulative' => $cumulative,
        'window_hours' => $policy['window_hours'],
      ],
    ]);
  }

  private function debugLog(string $message, string $context = '', array $data = []): void
  {
    if (!$this->debug) {
      return;
    }

    $logMessage = $message;

    if (!empty($context)) {
      $logMessage = "[{$context}] {$message}";
    }

    if (!empty($data)) {
      $logMessage .= " | Data: " . json_encode($data, JSON_UNESCAPED_UNICODE);
    }

    error_log($logMessage);
  }
}
