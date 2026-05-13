<?php
/**
 *
 * @copyright 2008 - https://www.clicshopping.org
 * @Brand : ClicShoppingAI(TM) at Inpi all right Reserved
 * @Licence GPL 2 & MIT
 * @Info : https://www.clicshopping.org/forum/trademark/
 *
 */

namespace ClicShopping\AI\DomainsAI\Analytics\Executor;

use ClicShopping\OM\Registry;


use ClicShopping\AI\DomainsAI\CoreAI\Helper\AgentResponseHelper;
use ClicShopping\AI\Security\SecurityLogger;
use ClicShopping\AI\DomainsAI\Analytics\Agent\AnalyticsAgent;
use ClicShopping\AI\CoreAI\Memory\ConversationMemory;
use ClicShopping\AI\DomainsAI\Analytics\Agent\ParallelLLMExecutor;
use ClicShopping\AI\Infrastructure\Prompt\PromptBuilder;

/**
 * AnalyticsQueryExecutor Class
 *
 * Responsibility: Execute analytics queries by generating and executing SQL.
 * This class is focused solely on analytics query execution and does not handle
 * other query types or orchestration logic.
 *
 * Extracted from HybridQueryProcessor as part of refactoring (Task 2.11.2)
 */
class AnalyticsQueryExecutor
{
  private SecurityLogger $logger;
  private bool $debug;
  private ?ConversationMemory $conversationMemory;
  private ?ParallelLLMExecutor $parallelExecutor = null;

  /**
   * Constructor
   *
   * @param bool $debug Enable debug logging
   * @param ConversationMemory|null $conversationMemory Optional conversation memory for context
   */
  public function __construct(bool $debug = false, ?ConversationMemory $conversationMemory = null)
  {
    $this->logger = new SecurityLogger();
    $this->debug = $debug;
    $this->conversationMemory = $conversationMemory;
    
    // Initialize ParallelLLMExecutor for parallel SQL generation
    // This will be used when multiple SQL interpretations are needed
    $this->parallelExecutor = new ParallelLLMExecutor(null, $this->debug);
  }

  /**
   * Execute an analytics query
   *
   * This method generates and executes SQL queries from natural language.
   * Supports multiple SQL queries in a single request (e.g., "stock of iPhone 17 AND stock by EAN").
   * All database operations are wrapped in try-catch to prevent server 500 errors.
   *
   * @param string $query Analytics query in natural language
   * @param array $context Context information (language_id, entity_id, etc.)
   * @return array Result with structured data
   */
  public function execute(string $query, array $context = []): array
  {
    try {
      if ($this->debug) {
        $this->logger->logSecurityEvent(
          "AnalyticsQueryExecutor: Executing analytics query: {$query}",
          'info'
        );
      }

      // Resolve contextual references using ConversationMemory
      $resolvedQuery = $query;
      $contextUsed = null;
      
      if ($this->conversationMemory !== null) {
        try {
          $resolutionResult = $this->conversationMemory->resolveContextualReferences($query);
          
          if ($resolutionResult['has_references'] && !empty($resolutionResult['resolved_query'])) {
            $resolvedQuery = $resolutionResult['resolved_query'];
            $contextUsed = $resolutionResult['context_used'];
            
            if ($this->debug) {
              $this->logger->logSecurityEvent(
                "Contextual references resolved in analytics: '{$query}' -> '{$resolvedQuery}'",
                'info'
              );
            }
          }
        } catch (\Exception $e) {
          $this->logger->logSecurityEvent(
            "Error resolving contextual references: " . $e->getMessage(),
            'warning'
          );
          // Continue with original query
        }
      }

      // Retrieve last entity from memory if available
      $lastEntity = null;
      if ($this->conversationMemory !== null) {
        try {
          $lastEntity = $this->conversationMemory->getLastEntity();
          
          if ($lastEntity !== null && $this->debug) {
            $this->logger->logSecurityEvent(
              "Last entity from memory for analytics: {$lastEntity['type']} (ID: {$lastEntity['id']})",
              'info'
            );
          }
        } catch (\Exception $e) {
          $this->logger->logSecurityEvent(
            "Error retrieving last entity: " . $e->getMessage(),
            'warning'
          );
        }
      }

      // Initialize AnalyticsAgent for SQL generation and execution
      $languageId = $context['language_id'] ?? null;
      if ($languageId === null && Registry::exists('Language')) {
        $languageId = Registry::get('Language')->getId();
      }

      $userId = $context['user_id'] ?? 'system';
      
      // Create AnalyticsAgent instance
      $analyticsAgent = new AnalyticsAgent(
        $languageId,
        true, // enable prompt cache
        $userId
      );

      if ($this->debug) {
        $this->logger->logSecurityEvent(
          "AnalyticsAgent initialized for query processing",
          'info'
        );
      }

      // Process the business query with resolved query and entity context
      $feedbackContext = $context['feedback_context'] ?? [];
      
      // Add last entity to context if available
      if ($lastEntity !== null) {
        $context['last_entity_id'] = $lastEntity['id'];
        $context['last_entity_type'] = $lastEntity['type'];
      }
      
      $analyticsResult = $analyticsAgent->processBusinessQuery(
        $resolvedQuery,
        true, // include SQL in response
        $feedbackContext
      );

      if ($this->debug) {
        $this->logger->logSecurityEvent(
          "Analytics query processed. Type: " . ($analyticsResult['type'] ?? 'unknown'),
          'info'
        );
      }

      // Check if the result is an error or not_analytics
      if (isset($analyticsResult['type'])) {
        if ($analyticsResult['type'] === 'error') {
          if ($this->debug) {
            $this->logger->logSecurityEvent(
              "Analytics query returned error: " . ($analyticsResult['error'] ?? $analyticsResult['message'] ?? 'Unknown error'),
              'warning'
            );
          }

          return AgentResponseHelper::createErrorResponse(
            $query,
            $analyticsResult['error'] ?? $analyticsResult['message'] ?? 'Analytics query execution failed',
            'analytics',
            [
              'error_details' => $analyticsResult['error_details'] ?? null,
              'error_type' => 'analytics_error',
              'component' => 'AnalyticsQueryExecutor::execute',
            ]
          );
        }
        
        if ($analyticsResult['type'] === 'not_analytics') {
          if ($this->debug) {
            $this->logger->logSecurityEvent(
              "AnalyticsAgent returned 'not_analytics'",
              'warning'
            );
          }

          return AgentResponseHelper::createErrorResponse(
            $query,
            'This query was not recognized as an analytics query by the SQL generator. Please rephrase with more specific data-related terms (e.g., "show", "count", "total", "stock", "sales").',
            'analytics',
            [
              'error_details' => 'Query classification mismatch',
              'error_type' => 'classification_error',
              'component' => 'AnalyticsQueryExecutor::execute',
            ]
          );
        }
      }

      // Extract results from analytics response
      // Handle ambiguous results specially - Use parallel execution for multiple interpretations
      if (isset($analyticsResult['type']) && $analyticsResult['type'] === 'analytics_results_ambiguous') {
        // For ambiguous results, we have multiple interpretations
        $interpretationResults = $analyticsResult['interpretation_results'] ?? [];
        
        if (!empty($interpretationResults) && \count($interpretationResults) > 1) {
          // Detect when multiple interpretations are beneficial
          // Check if parallel execution is available, fall back to sequential if not
          $parallelStatus = $this->isParallelExecutionAvailable();
          
          if ($parallelStatus['available']) {
            if ($this->debug) {
              $this->logger->logSecurityEvent(
                "TASK 1.4: Using parallel execution for " . \count($interpretationResults) . " interpretations",
                'info'
              );
            }
            
            // Extract interpretation descriptions for parallel processing
            $interpretations = [];
            foreach ($interpretationResults as $idx => $interp) {
              $key = 'interpretation_' . $idx;
              $interpretations[$key] = $interp['description'] ?? $interp['label'] ?? "Interpretation {$idx}";
            }
            
            // Execute parallel SQL generation
            $parallelResult = $this->executeParallelSQLGeneration(
              $resolvedQuery,
              $interpretations,
              $context
            );
            
            if ($parallelResult['success']) {
              // Process parallel results and select best interpretation
              $sqlResults = $parallelResult['results'];
              $successfulResults = \array_filter($sqlResults, fn($r) => $r['success'] && !empty($r['sql_query']));
              
              if (!empty($successfulResults)) {
                // Use the first successful interpretation as primary result
                $primaryKey = \array_key_first($successfulResults);
                $primaryResult = $successfulResults[$primaryKey];
                
                // Execute the SQL query to get actual results
                try {
                  // Re-use AnalyticsAgent to execute the SQL and get results
                  $sqlExecutionResult = $analyticsAgent->processBusinessQuery(
                    $resolvedQuery,
                    true,
                    $feedbackContext
                  );
                  
                  $results = $sqlExecutionResult['results'] ?? [];
                  $sqlQuery = $primaryResult['sql_query'];
                  $interpretation = $primaryResult['interpretation'];
                  
                  // Add information about other interpretations
                  $otherInterpretations = \array_slice(\array_values($successfulResults), 1);
                  if (!empty($otherInterpretations)) {
                    $interpretation .= "\n\nNote: This query is ambiguous. Other possible interpretations:";
                    foreach ($otherInterpretations as $idx => $interp) {
                      $interpretation .= "\n" . ($idx + 2) . ". " . $interp['interpretation'];
                    }
                  }
                  
                  if ($this->debug) {
                    $this->logger->logSecurityEvent(
                      "TASK 1.4: Parallel execution completed. Selected interpretation: {$primaryKey}",
                      'info',
                      [
                        'total_interpretations' => \count($interpretations),
                        'successful' => \count($successfulResults),
                        'parallel_time' => $parallelResult['total_time']
                      ]
                    );
                  }
                } catch (\Exception $e) {
                  $this->logger->logSecurityEvent(
                    "Error executing SQL from parallel result: " . $e->getMessage(),
                    'warning'
                  );
                  
                  // Fall back to using original interpretation results
                  $primaryInterpretation = $interpretationResults[0];
                  $results = $primaryInterpretation['results'] ?? [];
                  $sqlQuery = $primaryInterpretation['sql_query'] ?? null;
                  $interpretation = $primaryInterpretation['description'] ?? $primaryInterpretation['label'] ?? null;
                }
              } else {
                // No successful parallel results, fall back to original
                if ($this->debug) {
                  $this->logger->logSecurityEvent(
                    "TASK 1.4: No successful parallel results, using original interpretation",
                    'warning'
                  );
                }
                
                $primaryInterpretation = $interpretationResults[0];
                $results = $primaryInterpretation['results'] ?? [];
                $sqlQuery = $primaryInterpretation['sql_query'] ?? null;
                $interpretation = $primaryInterpretation['description'] ?? $primaryInterpretation['label'] ?? null;
              }
            } else {
              // Parallel execution failed, fall back to sequential
              if ($this->debug) {
                $this->logger->logSecurityEvent(
                  "TASK 1.4: Parallel execution failed, falling back to sequential: " . ($parallelResult['error'] ?? 'Unknown error'),
                  'warning'
                );
              }
              
              $primaryInterpretation = $interpretationResults[0];
              $results = $primaryInterpretation['results'] ?? [];
              $sqlQuery = $primaryInterpretation['sql_query'] ?? null;
              $interpretation = $primaryInterpretation['description'] ?? $primaryInterpretation['label'] ?? null;
              
              // Add information about other interpretations
              $otherInterpretations = \array_slice($interpretationResults, 1);
              $interpretation .= "\n\nNote: This query is ambiguous. Other possible interpretations:";
              foreach ($otherInterpretations as $idx => $interp) {
                $interpretation .= "\n" . ($idx + 2) . ". " . ($interp['label'] ?? 'Interpretation ' . ($idx + 2));
              }
            }
          } else {
            if ($this->debug) {
              $this->logger->logSecurityEvent(
                "TASK 1.5: Parallel execution unavailable, falling back to sequential. Reason: " . $parallelStatus['reason'],
                'info'
              );
            }
            
            // Extract interpretation descriptions for sequential processing
            $interpretations = [];
            foreach ($interpretationResults as $idx => $interp) {
              $key = 'interpretation_' . $idx;
              $interpretations[$key] = $interp['description'] ?? $interp['label'] ?? "Interpretation {$idx}";
            }
            
            // Execute sequential SQL generation
            $sequentialResult = $this->executeSequentialSQLGeneration(
              $resolvedQuery,
              $interpretations,
              $context,
              $analyticsAgent
            );
            
            if ($sequentialResult['success']) {
              // Process sequential results and select best interpretation
              $sqlResults = $sequentialResult['results'];
              $successfulResults = \array_filter($sqlResults, fn($r) => $r['success'] && !empty($r['sql_query']));
              
              if (!empty($successfulResults)) {
                // Use the first successful interpretation as primary result
                $primaryKey = \array_key_first($successfulResults);
                $primaryResult = $successfulResults[$primaryKey];
                
                $results = $interpretationResults[0]['results'] ?? [];
                $sqlQuery = $primaryResult['sql_query'];
                $interpretation = $primaryResult['interpretation'];
                
                // Add information about other interpretations
                $otherInterpretations = \array_slice(\array_values($successfulResults), 1);
                if (!empty($otherInterpretations)) {
                  $interpretation .= "\n\nNote: This query is ambiguous. Other possible interpretations:";
                  foreach ($otherInterpretations as $idx => $interp) {
                    $interpretation .= "\n" . ($idx + 2) . ". " . $interp['interpretation'];
                  }
                }
                
                if ($this->debug) {
                  $this->logger->logSecurityEvent(
                    "TASK 1.5: Sequential execution completed. Selected interpretation: {$primaryKey}",
                    'info',
                    [
                      'total_interpretations' => \count($interpretations),
                      'successful' => \count($successfulResults),
                      'sequential_time' => $sequentialResult['total_time']
                    ]
                  );
                }
              } else {
                // No successful sequential results, use original
                if ($this->debug) {
                  $this->logger->logSecurityEvent(
                    "TASK 1.5: No successful sequential results, using original interpretation",
                    'warning'
                  );
                }
                
                $primaryInterpretation = $interpretationResults[0];
                $results = $primaryInterpretation['results'] ?? [];
                $sqlQuery = $primaryInterpretation['sql_query'] ?? null;
                $interpretation = $primaryInterpretation['description'] ?? $primaryInterpretation['label'] ?? null;
              }
            } else {
              // Sequential execution also failed, use original
              if ($this->debug) {
                $this->logger->logSecurityEvent(
                  "TASK 1.5: Sequential execution failed, using original interpretation",
                  'warning'
                );
              }
              
              $primaryInterpretation = $interpretationResults[0];
              $results = $primaryInterpretation['results'] ?? [];
              $sqlQuery = $primaryInterpretation['sql_query'] ?? null;
              $interpretation = $primaryInterpretation['description'] ?? $primaryInterpretation['label'] ?? null;
              
              // Add information about other interpretations
              $otherInterpretations = \array_slice($interpretationResults, 1);
              $interpretation .= "\n\nNote: This query is ambiguous. Other possible interpretations:";
              foreach ($otherInterpretations as $idx => $interp) {
                $interpretation .= "\n" . ($idx + 2) . ". " . ($interp['label'] ?? 'Interpretation ' . ($idx + 2));
              }
            }
          }
        } else {
          // Single interpretation or empty results
          $primaryInterpretation = $interpretationResults[0] ?? [];
          $results = $primaryInterpretation['results'] ?? [];
          $sqlQuery = $primaryInterpretation['sql_query'] ?? null;
          $interpretation = $primaryInterpretation['description'] ?? $primaryInterpretation['label'] ?? null;
        }
        
        $entityId = $analyticsResult['entity_id'] ?? null;
        $entityType = $analyticsResult['entity_type'] ?? null;
      } else {
        // Normal single result
        $results = $analyticsResult['results'] ?? [];
        $sqlQuery = $analyticsResult['sql_query'] ?? null;
        $interpretation = $analyticsResult['interpretation'] ?? null;
        $entityId = $analyticsResult['entity_id'] ?? null;
        $entityType = $analyticsResult['entity_type'] ?? null;
      }

      // This is critical for hybrid query validation - ResultSynthesizer requires
      // either interpretation or text_response to generate the final text response.
      // Without this fallback, queries that don't generate interpretations would fail
      // validation in hybrid scenarios.
      if (empty($interpretation)) {
        $interpretation = 'Query executed successfully';
        
        if ($this->debug) {
          $this->logger->logSecurityEvent(
            "TASK 1.2: Applied fallback interpretation (original was empty)",
            'info'
          );
        }
      }

      if (!empty($sqlQuery)) {
        $dateValidator = new \ClicShopping\AI\DomainsAI\Analytics\Validator\SqlDateValidator($this->debug);
        $validationResult = $dateValidator->validateAndFix($sqlQuery, $query);
        
        if ($validationResult['corrected']) {
          $sqlQuery = $validationResult['sql'];
          
          if ($this->debug) {
            $this->logger->logSecurityEvent(
              "TASK 1.3: SQL date logic corrected: " . $validationResult['reason'],
              'info',
              [
                'original_query' => $query,
                'correction' => $validationResult['reason']
              ]
            );
          }
          
          // Add correction note to interpretation
          $interpretation .= "\n\n(Note: SQL was automatically corrected for date accuracy: " . $validationResult['reason'] . ")";
        }
      }

      // Handle multiple SQL queries (if the query contains AND/multiple parts)
      $multipleQueries = $this->detectMultipleSqlQueries($query);
      
      if ($multipleQueries && count($multipleQueries) > 1) {
        if ($this->debug) {
          $this->logger->logSecurityEvent(
            "Detected multiple SQL queries in request: " . count($multipleQueries),
            'info'
          );
        }

        $startParallelTime = microtime(true);
        
        $parallelStatus = $this->isParallelExecutionAvailable();
        
        $allResults = [];
        $allSqlQueries = [];
        
        if ($parallelStatus['available'] && count($multipleQueries) > 1) {
          if ($this->debug) {
            $this->logger->logSecurityEvent(
              "TASK 3: Using parallel execution for " . count($multipleQueries) . " sub-queries",
              'info'
            );
          }
          
          // For now, we fall back to sequential execution because full parallel implementation
          // requires extracting and refactoring SQL generation logic from AnalyticsAgent
          // This is a partial implementation that demonstrates the integration point
          
          // TODO: Full parallel implementation would require:
          // 1. Extract SQL generation logic from AnalyticsAgent into a reusable component
          // 2. Build prompts for each sub-query using that component
          // 3. Execute prompts in parallel using ParallelLLMExecutor
          // 4. Process results and execute SQL queries
          
          if ($this->debug) {
            $this->logger->logSecurityEvent(
              "TASK 3: Falling back to sequential execution (full parallel implementation requires refactoring)",
              'info'
            );
          }
          
          // Sequential execution with performance tracking
          foreach ($multipleQueries as $index => $subQuery) {
            $subQueryStart = microtime(true);
            
            try {
              $subResult = $analyticsAgent->processBusinessQuery(
                $subQuery,
                true,
                $feedbackContext
              );

              $subQueryDuration = microtime(true) - $subQueryStart;

              if (isset($subResult['type']) && $subResult['type'] !== 'error') {
                $subData = $subResult['results'] ?? [];
                $subColumns = !empty($subData) ? array_keys($subData[0]) : [];
                
                $allResults[] = [
                  'query' => $subQuery,
                  'sql' => $subResult['sql_query'] ?? null,
                  'columns' => $subColumns,
                  'rows' => $subData,
                  'row_count' => count($subData),
                  'interpretation' => $subResult['interpretation'] ?? null,
                  'execution_time' => $subQueryDuration,
                ];
                
                if (!empty($subResult['sql_query'])) {
                  $allSqlQueries[] = $subResult['sql_query'];
                }
                
                if ($this->debug) {
                  $this->logger->logSecurityEvent(
                    "Sub-query {$index} completed in " . number_format($subQueryDuration, 3) . "s",
                    'info'
                  );
                }
              }
            } catch (\Exception $e) {
              $this->logger->logSecurityEvent(
                "Error executing sub-query {$index}: " . $e->getMessage(),
                'warning'
              );
              
              $allResults[] = [
                'query' => $subQuery,
                'error' => $e->getMessage(),
                'success' => false,
              ];
            }
          }
          
          $totalDuration = microtime(true) - $startParallelTime;
          
          // Calculate theoretical parallel time (max of individual times)
          $individualTimes = array_column($allResults, 'execution_time');
          $theoreticalParallelTime = !empty($individualTimes) ? max($individualTimes) : 0;
          $potentialTimeSaved = $totalDuration - $theoreticalParallelTime;
          
          if ($this->debug && $potentialTimeSaved > 0) {
            $this->logger->logSecurityEvent(
              "TASK 3: Sequential execution took " . number_format($totalDuration, 3) . "s. " .
              "Parallel execution could reduce to ~" . number_format($theoreticalParallelTime, 3) . "s " .
              "(saving ~" . number_format($potentialTimeSaved, 3) . "s, " . 
              number_format(($potentialTimeSaved / $totalDuration) * 100, 1) . "% faster)",
              'info'
            );
          }
        } else {      
          if ($this->debug) {
            $reason = !$parallelStatus['available'] 
              ? "parallel execution unavailable: " . $parallelStatus['reason']
              : "only one query";
            $this->logger->logSecurityEvent(
              "TASK 1.5: Using sequential execution for sub-queries ({$reason})",
              'info'
            );
          }
          
          foreach ($multipleQueries as $index => $subQuery) {
            try {
              $subResult = $analyticsAgent->processBusinessQuery(
                $subQuery,
                true,
                $feedbackContext
              );

              if (isset($subResult['type']) && $subResult['type'] !== 'error') {
                $subData = $subResult['results'] ?? [];
                $subColumns = !empty($subData) ? array_keys($subData[0]) : [];
                
                $allResults[] = [
                  'query' => $subQuery,
                  'sql' => $subResult['sql_query'] ?? null,
                  'columns' => $subColumns,
                  'rows' => $subData,
                  'row_count' => count($subData),
                  'interpretation' => $subResult['interpretation'] ?? null,
                ];
                
                if (!empty($subResult['sql_query'])) {
                  $allSqlQueries[] = $subResult['sql_query'];
                }
              }
            } catch (\Exception $e) {
              $this->logger->logSecurityEvent(
                "Error executing sub-query {$index}: " . $e->getMessage(),
                'warning'
              );
              
              $allResults[] = [
                'query' => $subQuery,
                'error' => $e->getMessage(),
                'success' => false,
              ];
            }
          }
        }

        // Return combined results using standardized format
        return AgentResponseHelper::createAnalyticsResponse(
          $query,
          [
            'multiple_results' => $allResults,
            'sql_queries' => $allSqlQueries,
            'multiple_queries' => true,
            'query_count' => count($allResults),
            'entity_id' => $entityId,
            'entity_type' => $entityType,
            // This field is required for ResultSynthesizer to properly validate and merge results
            // from multiple sub-queries. It identifies the data source and provides metadata
            // for display in the UI.
            'source_attribution' => [
              'source_type' => 'Analytics Database',
              'source_icon' => '📊',
              'source_details' => 'Data retrieved from transactional database'
            ],
          ],
          true,
          [
            'execution_time' => microtime(true) - ($context['start_time'] ?? microtime(true)),
            'multiple_queries' => true,
            'parallel_executor_available' => $this->parallelExecutor !== null,
            'parallel_enabled' => $useParallel ?? false,
            'note' => 'TASK 3: ParallelLLMExecutor integrated. Full parallel execution requires refactoring SQL generation logic from AnalyticsAgent.',
          ]
        );
      }

      // Single query result - format for consistency
      $formattedResult = [];
      
      if (!empty($results)) {
        // Extract column names from first row
        $columns = !empty($results) ? array_keys($results[0]) : [];
        
        // Ensure interpretation is always present
        if (empty($interpretation)) {
          $interpretation = 'Query executed successfully';
        }
        
        $formattedResult = [
          'columns' => $columns,
          'rows' => $results,
          'row_count' => count($results),
          'sql' => $sqlQuery,
          'interpretation' => $interpretation,
        ];

        if ($this->debug) {
          $this->logger->logSecurityEvent(
            "Analytics query returned " . count($results) . " rows with " . count($columns) . " columns",
            'info'
          );
        }
      } else {
        if ($this->debug) {
          $this->logger->logSecurityEvent(
            "Analytics query returned no results",
            'info'
          );
        }

        $formattedResult = [
          'columns' => [],
          'rows' => [],
          'row_count' => 0,
          'sql' => $sqlQuery,
          'interpretation' => $interpretation ?? 'No results found for this query.',
        ];
      }

      // Store entity in memory if found in results
      if ($entityId !== null && $entityType !== null && $this->conversationMemory !== null) {
        try {
          //Extract entity name from results for context enrichment
          $entityName = null;
	  
          if (!empty($results) && is_array($results[0])) {
            // Try to find entity name in first result row
            $firstRow = $results[0];
            $nameFields = ['products_name', 'name', 'manufacturers_name', 'categories_name', 'title'];
            foreach ($nameFields as $field) {
              if (isset($firstRow[$field]) && !empty($firstRow[$field])) {
                $entityName = $firstRow[$field];
                break;
              }
            }
          }
          
          $this->conversationMemory->setLastEntity((int)$entityId, $entityType, $entityName);
          
          if ($this->debug) {
            $nameInfo = $entityName ? " (name: {$entityName})" : "";
            $this->logger->logSecurityEvent(
              "Stored entity in memory from analytics: {$entityType} (ID: {$entityId}){$nameInfo}",
              'info'
            );
          }
        } catch (\Exception $e) {
          $this->logger->logSecurityEvent(
            "Error storing entity in memory: " . $e->getMessage(),
            'warning'
          );
        }
      }

      // Return standardized response using AgentResponseHelper
      return AgentResponseHelper::createAnalyticsResponse(
        $query,
        array_merge($formattedResult, [
          'sql_queries' => !empty($sqlQuery) ? [$sqlQuery] : [],
          'entity_id' => $entityId,
          'entity_type' => $entityType,
          // This field is required for ResultSynthesizer to properly validate and merge results
          // from multiple sub-queries. It identifies the data source and provides metadata
          // for display in the UI.
          'source_attribution' => [
            'source_type' => 'Analytics Database',
            'source_icon' => '📊',
            'source_details' => 'Data retrieved from transactional database'
          ],
        ]),
        true,
        [
          'cached' => $analyticsResult['cached'] ?? false,
          'execution_time' => microtime(true) - ($context['start_time'] ?? microtime(true)),
          'context_used' => $contextUsed !== null,
          'query_resolved' => $resolvedQuery !== $query,
        ]
      );

    } catch (\Exception $e) {
      $errorId = uniqid('ana_', true);
      $this->logger->logSecurityEvent(
        "Error executing analytics query [ID: {$errorId}]: " . $e->getMessage() . "\nQuery: {$query}\nStack: " . $e->getTraceAsString(),
        'error'
      );

      return AgentResponseHelper::createErrorResponse(
        $query,
        'Unable to execute analytics query. Please try again.',
        'analytics',
        [
          'error_id' => $errorId,
          'error_type' => 'execution_error',
          'component' => 'AnalyticsQueryExecutor::execute',
        ]
      );
    }
  }

  /**
   * Detect if query contains multiple SQL queries (AND connector)
   *
   * 
   * This method always returns false in Pure LLM mode.
   * Multi-query detection is handled by the LLM itself.
   * 
   * @param string $query Query to analyze
   * @return array|false Always returns false (multi-query detection disabled)
   */
  private function detectMultipleSqlQueries(string $query): array|false
  {
    // Pure LLM mode: Multi-query detection disabled
    // LLM will handle each query independently
    if ($this->debug) {
      $this->logger->logSecurityEvent(
        "Multi-query detection bypassed (Pure LLM mode)",
        'info'
      );
    }
    return false;
  }
  
  /**
   * Build SQL generation prompt for LLM
   * 
   * This method builds a prompt that asks the LLM to generate SQL for an analytics query.
   * 
   * @param string $query The analytics query in natural language
   * @param string|null $interpretation Specific interpretation to use (for ambiguous queries)
   * @param array $context Query context (language_id, entity_id, feedback_context, etc.)
   * @return string The formatted prompt for the LLM
   */
  private function buildSQLGenerationPrompt(
    string $query, 
    ?string $interpretation = null, 
    array $context = []
  ): string
  {
    // If an interpretation is provided, modify the query to be more specific
    $effectiveQuery = $query;
    if ($interpretation !== null) {
      $effectiveQuery = "{$query} (Interpretation: {$interpretation})";
    }
    
    // Use AnalyticsAgent's PromptBuilder if available
    // This ensures we use the same prompt structure as the main analytics flow
    try {
      $languageId = $context['language_id'] ?? Registry::get('Language')->getId();
      $promptBuilder = new PromptBuilder(
        Registry::get('Language'),
        $languageId,
        $this->debug
      );
      
      // Enrich with feedback context if provided
      $feedbackContext = $context['feedback_context'] ?? [];
      $enrichedPrompt = $promptBuilder->enrichWithFeedback($effectiveQuery, $feedbackContext);
      
      if ($this->debug) {
        $this->logger->logSecurityEvent(
          "Built SQL generation prompt using PromptBuilder",
          'info',
          [
            'query_length' => strlen($effectiveQuery),
            'has_interpretation' => $interpretation !== null,
            'has_feedback' => !empty($feedbackContext)
          ]
        );
      }
      
      return $enrichedPrompt;
      
    } catch (\Exception $e) {
      // Fallback to simple prompt if PromptBuilder fails
      if ($this->debug) {
        $this->logger->logSecurityEvent(
          "PromptBuilder failed, using fallback prompt: " . $e->getMessage(),
          'warning'
        );
      }
      
      return $this->buildFallbackSQLPrompt($effectiveQuery);
    }
  }
  
  /**
   * Build fallback SQL generation prompt
   * 
   * Used when PromptBuilder is unavailable or fails.
   * Creates a simple but effective prompt for SQL generation.
   * 
   * @param string $query The analytics query
   * @return string The formatted prompt
   */
  private function buildFallbackSQLPrompt(string $query): string
  {
    $prompt = "Generate a SQL query to answer the following business question:\n\n";
    $prompt .= "Question: {$query}\n\n";
    $prompt .= "Requirements:\n";
    $prompt .= "- Return only the SQL query\n";
    $prompt .= "- Use proper table names and column names\n";
    $prompt .= "- Ensure the query is valid MySQL/MariaDB syntax\n";
    $prompt .= "- Do not include explanations, only the SQL query\n\n";
    $prompt .= "SQL Query:";
    
    return $prompt;
  }
  
  /**
   * Execute parallel SQL generation for multiple interpretations
   * 
   * Generates SQL for multiple interpretations concurrently to reduce execution time.
   * 
   * @param string $query The analytics query
   * @param array $interpretations Array of interpretation descriptions
   * @param array $context Query context (language_id, entity_id, etc.)
   * @return array Results from parallel execution with SQL queries and metadata
   */
  private function executeParallelSQLGeneration(
    string $query, 
    array $interpretations, 
    array $context = []
  ): array
  {
    $startTime = microtime(true);
    
    if ($this->debug) {
      $this->logger->logSecurityEvent(
        "TASK 1.3: Starting parallel SQL generation for " . count($interpretations) . " interpretations",
        'info'
      );
    }
    
    $cachedResults = [];
    $uncachedInterpretations = [];
    $cacheKeys = [];
    
    foreach ($interpretations as $key => $interpretation) {
      // Generate cache key: md5(query + interpretation + context)
      $cacheKey = md5($query . $interpretation . json_encode($context));
      $cacheKeys[$key] = $cacheKey;
      
      // Check cache
      $sqlCache = new \ClicShopping\OM\Cache($cacheKey, 'Rag/SQL');
      
      if ($sqlCache->exists(60)) { // 60 minutes = 1 hour
        $cachedSQL = $sqlCache->get();
        if ($cachedSQL !== null && !empty($cachedSQL)) {
          // Cache hit - store result
          $cachedResults[$key] = [
            'success' => true,
            'interpretation' => $interpretation,
            'sql_query' => $cachedSQL,
            'raw_response' => $cachedSQL,
            'execution_time' => 0,
            'cached' => true
          ];
          
          if ($this->debug) {
            $this->logger->logSecurityEvent(
              "✅ SQL CACHE HIT for interpretation '{$key}' - Duration: < 10ms",
              'info',
              [
                'interpretation' => substr($interpretation, 0, 100),
                'cache_key' => $cacheKey
              ]
            );
          }
        } else {
          // Cache exists but data is invalid - treat as miss
          $uncachedInterpretations[$key] = $interpretation;
        }
      } else {
        // Cache miss - need to generate
        $uncachedInterpretations[$key] = $interpretation;
        
        if ($this->debug) {
          $this->logger->logSecurityEvent(
            "❌ SQL CACHE MISS for interpretation '{$key}' - Will call LLM",
            'info',
            ['interpretation' => substr($interpretation, 0, 100)]
          );
        }
      }
    }
    
    // Log cache statistics
    $cacheHits = count($cachedResults);
    $cacheMisses = count($uncachedInterpretations);
    $cacheHitRate = count($interpretations) > 0 
      ? ($cacheHits / count($interpretations)) * 100 
      : 0;
    
    if ($this->debug) {
      $this->logger->logSecurityEvent(
        "PHASE 4 - TASK 4.2: Cache statistics",
        'info',
        [
          'total_interpretations' => count($interpretations),
          'cache_hits' => $cacheHits,
          'cache_misses' => $cacheMisses,
          'cache_hit_rate' => number_format($cacheHitRate, 1) . '%',
          'llm_calls_saved' => $cacheHits
        ]
      );
    }
    
    // If all interpretations are cached, return immediately
    if (empty($uncachedInterpretations)) {
      $totalDuration = microtime(true) - $startTime;
      
      if ($this->debug) {
        $this->logger->logSecurityEvent(
          "✅ ALL interpretations cached - No LLM calls needed!",
          'info',
          [
            'total_time' => number_format($totalDuration * 1000, 2) . 'ms',
            'interpretations_count' => count($interpretations)
          ]
        );
      }
      
      return [
        'success' => true,
        'results' => $cachedResults,
        'total_time' => $totalDuration,
        'parallel_execution' => false,
        'all_cached' => true,
        'performance_metrics' => [
          'parallel_time' => $totalDuration,
          'theoretical_sequential_time' => 0,
          'time_saved' => 0,
          'percentage_faster' => 100,
          'max_individual_time' => 0,
          'avg_individual_time' => 0,
          'successful_count' => count($cachedResults),
          'failed_count' => 0,
          'total_count' => count($interpretations),
          'cache_hits' => $cacheHits,
          'cache_misses' => 0
        ]
      ];
    }
    
    // Build prompts ONLY for uncached interpretations
    $prompts = [];
    foreach ($uncachedInterpretations as $key => $interpretation) {
      $prompts[$key] = $this->buildSQLGenerationPrompt($query, $interpretation, $context);
      
      if ($this->debug) {
        $this->logger->logSecurityEvent(
          "Built prompt for uncached interpretation '{$key}': " . substr($interpretation, 0, 100),
          'info'
        );
      }
    }
    
    // Execute prompts in parallel (only for uncached interpretations)
    try {
      $parallelResults = $this->parallelExecutor->executeParallel($prompts);
      
      $parallelDuration = microtime(true) - $startTime;
      $individualTimes = array_column($parallelResults, 'execution_time');
      $maxIndividualTime = !empty($individualTimes) ? max($individualTimes) : 0;
      $sumIndividualTimes = !empty($individualTimes) ? array_sum($individualTimes) : 0;
      $avgIndividualTime = !empty($individualTimes) ? $sumIndividualTimes / count($individualTimes) : 0;
      
      // Calculate theoretical sequential time (sum of all individual times)
      $theoreticalSequentialTime = $sumIndividualTimes;
      
      // Calculate time saved vs sequential execution
      $timeSaved = $theoreticalSequentialTime - $parallelDuration;
      $percentageFaster = $theoreticalSequentialTime > 0 ? ($timeSaved / $theoreticalSequentialTime) * 100 : 0;
      
      // Count successes and failures
      $successCount = count(array_filter($parallelResults, fn($r) => $r['success']));
      $failureCount = count(array_filter($parallelResults, fn($r) => !$r['success']));
      
      if ($this->debug) {
        $this->logger->logSecurityEvent(
          "Parallel SQL generation completed in " . number_format($parallelDuration, 3) . "s",
          'info',
          [
            'interpretation_count' => count($uncachedInterpretations),
            'successful' => $successCount,
            'failed' => $failureCount,
            'parallel_time' => number_format($parallelDuration, 3) . 's',
            'theoretical_sequential_time' => number_format($theoreticalSequentialTime, 3) . 's',
            'time_saved' => number_format($timeSaved, 3) . 's',
            'percentage_faster' => number_format($percentageFaster, 1) . '%',
            'max_individual_time' => number_format($maxIndividualTime, 3) . 's',
            'avg_individual_time' => number_format($avgIndividualTime, 3) . 's',
            'execution_mode' => 'parallel',
            'cache_hits' => $cacheHits,
            'cache_misses' => $cacheMisses
          ]
        );
        
        foreach ($parallelResults as $key => $result) {
          $this->logger->logSecurityEvent(
            "Interpretation '{$key}' execution time: " . 
            number_format($result['execution_time'] ?? 0, 3) . "s - " .
            ($result['success'] ? 'SUCCESS' : 'FAILED'),
            'info'
          );
        }
      }
      
      // Process results and extract SQL queries
      $sqlResults = [];
      foreach ($parallelResults as $key => $result) {
        if ($result['success']) {
          // Extract SQL from LLM response
          $rawResponse = $result['response'];
          $sqlQueries = $this->extractSQLFromResponse($rawResponse);
          
          $sqlResults[$key] = [
            'success' => true,
            'interpretation' => $uncachedInterpretations[$key],
            'sql_query' => $sqlQueries[0] ?? null,
            'raw_response' => $rawResponse,
            'execution_time' => $result['execution_time'] ?? 0,
            'cached' => false
          ];
          
          if (!empty($sqlQueries[0])) {
            $cacheKey = $cacheKeys[$key];
            $sqlCache = new \ClicShopping\OM\Cache($cacheKey, 'Rag/SQL');
            $sqlCache->save($sqlQueries[0]);
            
            if ($this->debug) {
              $this->logger->logSecurityEvent(
                "💾 Cached SQL for interpretation '{$key}' (TTL: 1 hour)",
                'info',
                [
                  'cache_key' => $cacheKey,
                  'sql_length' => strlen($sqlQueries[0])
                ]
              );
            }
          }
          
          if ($this->debug && !empty($sqlQueries[0])) {
            $this->logger->logSecurityEvent(
              "Extracted SQL for '{$key}': " . substr($sqlQueries[0], 0, 150),
              'info'
            );
          }
        } else {
          $sqlResults[$key] = [
            'success' => false,
            'interpretation' => $uncachedInterpretations[$key],
            'error' => $result['error'] ?? 'Unknown error',
            'execution_time' => $result['execution_time'] ?? 0,
            'cached' => false
          ];
          
          if ($this->debug) {
            $this->logger->logSecurityEvent(
              "Failed to generate SQL for '{$key}': " . ($result['error'] ?? 'Unknown error'),
              'warning'
            );
          }
        }
      }
      
      // Merge cached results with newly generated results
      $allResults = array_merge($cachedResults, $sqlResults);
      
      return [
        'success' => true,
        'results' => $allResults,
        'total_time' => $parallelDuration,
        'parallel_execution' => true,
        'performance_metrics' => [
          'parallel_time' => $parallelDuration,
          'theoretical_sequential_time' => $theoreticalSequentialTime,
          'time_saved' => $timeSaved,
          'percentage_faster' => $percentageFaster,
          'max_individual_time' => $maxIndividualTime,
          'avg_individual_time' => $avgIndividualTime,
          'successful_count' => $successCount + $cacheHits,
          'failed_count' => $failureCount,
          'total_count' => count($interpretations),
          'cache_hits' => $cacheHits,
          'cache_misses' => $cacheMisses,
          'cache_hit_rate' => $cacheHitRate
        ]
      ];
      
    } catch (\Exception $e) {
      $this->logger->logSecurityEvent(
        "Parallel SQL generation failed: " . $e->getMessage(),
        'error'
      );
      
      // Return cached results if available, even if parallel execution failed
      if (!empty($cachedResults)) {
        return [
          'success' => true,
          'results' => $cachedResults,
          'total_time' => microtime(true) - $startTime,
          'parallel_execution' => false,
          'partial_cache' => true,
          'error' => $e->getMessage()
        ];
      }
      
      return [
        'success' => false,
        'error' => $e->getMessage(),
        'total_time' => microtime(true) - $startTime,
        'parallel_execution' => false
      ];
    }
  }
  
  /**
   * Extract SQL queries from LLM response
   * 
   * Helper method that uses SqlQueryProcessor to extract SQL from raw LLM output.
   * 
   * @param string $rawResponse Raw response from LLM
   * @return array Array of extracted SQL queries
   */
  private function extractSQLFromResponse(string $rawResponse): array
  {
    try {
      $queryProcessor = new \ClicShopping\AI\DomainsAI\Analytics\Executor\SqlQueryProcessor(
        $this->logger,
        $this->debug
      );
      
      $sqlQueries = $queryProcessor->extractSqlQueries($rawResponse);
      
      if (empty($sqlQueries)) {
        // Try cleaning the response if extraction failed
        $cleaned = $queryProcessor->cleanSqlResponse($rawResponse);
        if (!empty($cleaned)) {
          $sqlQueries = [$cleaned];
        }
      }
      
      return $sqlQueries;
      
    } catch (\Exception $e) {
      if ($this->debug) {
        $this->logger->logSecurityEvent(
          "SQL extraction failed: " . $e->getMessage(),
          'warning'
        );
      }
      return [];
    }
  }
  
  /**
   * Check if parallel execution is available
   *
   * Checks both ParallelLLMExecutor availability and configuration.
   * 
   * @return array Status array with 'available' flag and 'reason' if unavailable
   */
  private function isParallelExecutionAvailable(): array
  {
    // Check if ParallelLLMExecutor is initialized
    if ($this->parallelExecutor === null) {
      return [
        'available' => false,
        'reason' => 'ParallelLLMExecutor not initialized'
      ];
    }
    
    // Check if parallel execution is enabled in configuration
    $stats = $this->parallelExecutor->getStatistics();
    if (!$stats['parallel_enabled']) {
      return [
        'available' => false,
        'reason' => 'Parallel execution disabled in configuration'
      ];
    }
    
    // All checks passed
    return [
      'available' => true,
      'reason' => null
    ];
  }
  
  /**
   * Execute sequential SQL generation fallback
   * 
   * Executes interpretations sequentially using AnalyticsAgent.
   * 
   * @param string $query The analytics query
   * @param array $interpretations Array of interpretation descriptions
   * @param array $context Query context
   * @param AnalyticsAgent $analyticsAgent AnalyticsAgent instance for execution
   * @return array Results from sequential execution
   */
  private function executeSequentialSQLGeneration(
    string $query,
    array $interpretations,
    array $context,
    AnalyticsAgent $analyticsAgent
  ): array
  {
    $startTime = microtime(true);
    
    if ($this->debug) {
      $this->logger->logSecurityEvent(
        "TASK 1.5: Executing sequential SQL generation for " . count($interpretations) . " interpretations",
        'info'
      );
    }
    
    $sqlResults = [];
    $feedbackContext = $context['feedback_context'] ?? [];
    
    foreach ($interpretations as $key => $interpretation) {
      $interpretationStart = microtime(true);
      
      try {
        // Build query with interpretation hint
        $interpretedQuery = "{$query} (Interpretation: {$interpretation})";
        
        // Execute using AnalyticsAgent
        $result = $analyticsAgent->processBusinessQuery(
          $interpretedQuery,
          true,
          $feedbackContext
        );
        
        $interpretationDuration = microtime(true) - $interpretationStart;
        
        if (isset($result['type']) && $result['type'] !== 'error') {
          $sqlResults[$key] = [
            'success' => true,
            'interpretation' => $interpretation,
            'sql_query' => $result['sql_query'] ?? null,
            'raw_response' => $result['interpretation'] ?? '',
            'execution_time' => $interpretationDuration
          ];
          
          if ($this->debug) {
            $this->logger->logSecurityEvent(
              "Sequential interpretation '{$key}' completed in " . number_format($interpretationDuration, 3) . "s",
              'info'
            );
          }
        } else {
          $sqlResults[$key] = [
            'success' => false,
            'interpretation' => $interpretation,
            'error' => $result['error'] ?? $result['message'] ?? 'Unknown error',
            'execution_time' => $interpretationDuration
          ];
          
          if ($this->debug) {
            $this->logger->logSecurityEvent(
              "Sequential interpretation '{$key}' failed: " . ($result['error'] ?? 'Unknown error'),
              'warning'
            );
          }
        }
      } catch (\Exception $e) {
        $interpretationDuration = microtime(true) - $interpretationStart;
        
        $sqlResults[$key] = [
          'success' => false,
          'interpretation' => $interpretation,
          'error' => $e->getMessage(),
          'execution_time' => $interpretationDuration
        ];
        
        $this->logger->logSecurityEvent(
          "Exception in sequential interpretation '{$key}': " . $e->getMessage(),
          'error'
        );
      }
    }
    
    $totalDuration = microtime(true) - $startTime;
    
    if ($this->debug) {
      $successCount = count(array_filter($sqlResults, fn($r) => $r['success']));
      $this->logger->logSecurityEvent(
        "Sequential SQL generation completed in " . number_format($totalDuration, 3) . "s " .
        "({$successCount}/" . count($interpretations) . " successful)",
        'info'
      );
    }
    
    return [
      'success' => true,
      'results' => $sqlResults,
      'total_time' => $totalDuration,
      'parallel_execution' => false
    ];
  }
}