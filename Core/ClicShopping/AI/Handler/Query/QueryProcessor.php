<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\Handler\Query;

use ClicShopping\AI\InterfacesAI\QueryProcessorInterface;
use ClicShopping\AI\Security\SecurityLogger;
use ClicShopping\AI\CoreAI\Orchestrator\SubOrchestrator\ContextManager;
use ClicShopping\AI\CoreAI\Query\QueryAnalyzer;
use ClicShopping\AI\Handler\Error\ErrorHandler;
use ClicShopping\AI\CoreAI\Memory\ConversationMemory;

/**
 * QueryProcessor Class
 *
 * Handles orchestration-level query processing operations including:
 * - Query validation and retry logic for temporary errors
 * - Parallel execution of context retrieval and translation
 * - Context decision and enrichment
 * - Query-context relation analysis
 *
 * Extracted from OrchestratorAgent.php as part of refactoring to reduce file size
 *
 * ARCHITECTURAL LOCATION:
 * - Core/ClicShopping/AI/Handler/Query/ (correct location per AGENTS.md)
 * - NOT in CoreAI/Orchestrator/SubQueryProcessing/ (wrong location)
 *
 * DISTINCTION FROM Apps QueryProcessor:
 * - This class: Core AI orchestration-level query processing (retry, parallel, context)
 * - Apps QueryProcessor: AJAX handler wrapper for OrchestratorAgent
 * - Different namespaces, different responsibilities, no conflict
 *
 */
class QueryProcessor implements QueryProcessorInterface
{
  private SecurityLogger $securityLogger;
  private ContextManager $contextManager;
  private QueryAnalyzer $queryAnalyzer;
  private ErrorHandler $errorHandler;
  private ?ConversationMemory $conversationMemory;
  private bool $debug;

  /**
   * Constructor
   *
   * @param ContextManager $contextManager Context management component
   * @param QueryAnalyzer $queryAnalyzer Query analysis component
   * @param ErrorHandler $errorHandler Error handling component
   * @param ConversationMemory|null $conversationMemory Conversation memory (optional)
   * @param bool $debug Enable debug logging
   */
  public function __construct(
    ContextManager $contextManager,
    QueryAnalyzer $queryAnalyzer,
    ErrorHandler $errorHandler,
    ?ConversationMemory $conversationMemory = null,
    bool $debug = false
  ) {
    $this->contextManager = $contextManager;
    $this->queryAnalyzer = $queryAnalyzer;
    $this->errorHandler = $errorHandler;
    $this->conversationMemory = $conversationMemory;
    $this->debug = $debug;
    $this->securityLogger = new SecurityLogger();

    if ($this->debug) {
      $this->securityLogger->logStructured('info', 'QueryProcessor', 'initialized', [
        'has_conversation_memory' => $this->conversationMemory !== null,
        'components' => [
          'context_manager' => get_class($this->contextManager),
          'query_analyzer' => get_class($this->queryAnalyzer),
          'error_handler' => get_class($this->errorHandler)
        ]
      ]);
    }
  }

  /**
   * Process query with retry logic for temporary errors
   *
   * Automatically retries queries on temporary errors (network issues, timeouts).
   * Distinguishes between temporary errors (should retry) and permanent errors (should not retry).
   *
   * Extracted from OrchestratorAgent::processWithRetry()
   *
   * @param string $query User query to process
   * @param array $options Processing options (max_retries, retry_delay, etc.)
   * @param callable $processCallback Callback function to execute
   * @return array Processing result with status and data
   * @throws \Exception If max retries exceeded or permanent error occurs
   */
  public function processWithRetry(string $query, array $options, callable $processCallback): array
  {
    $maxRetries = $options['max_retries'] ?? 2;
    $retryDelay = $options['retry_delay'] ?? 1; // seconds
    $attempt = 0;
    $lastError = null;

    if ($this->debug) {
      $this->securityLogger->logStructured('info', 'QueryProcessor', 'retry_start', [
        'query_length' => strlen($query),
        'max_retries' => $maxRetries,
        'retry_delay' => $retryDelay,
        'options_keys' => array_keys($options)
      ]);
    }

    while ($attempt <= $maxRetries) {
      try {
        $attemptStart = microtime(true);
        $result = $processCallback($query, $options);
        $attemptDuration = (microtime(true) - $attemptStart) * 1000;

        // If success, return result
        if ($result['success'] ?? false) {
          // Add retry info if needed
          if ($attempt > 0) {
            $result['retry_info'] = [
              'attempts' => $attempt + 1,
              'succeeded_on_retry' => true
            ];

            if ($this->debug) {
              $this->securityLogger->logStructured('info', 'QueryProcessor', 'retry_success', [
                'attempt' => $attempt + 1,
                'total_attempts' => $attempt + 1,
                'duration_ms' => round($attemptDuration, 2)
              ]);
            }
          } else {
            if ($this->debug) {
              $this->securityLogger->logStructured('info', 'QueryProcessor', 'first_attempt_success', [
                'duration_ms' => round($attemptDuration, 2)
              ]);
            }
          }
          return $result;
        }

        // If failure but not temporary error, don't retry
        if (!$this->errorHandler->isTemporaryError($result['error'] ?? '')) {
          if ($this->debug) {
            $this->securityLogger->logStructured('warning', 'QueryProcessor', 'permanent_error', [
              'error' => $result['error'] ?? 'Unknown error',
              'attempt' => $attempt + 1,
              'no_retry' => true
            ]);
          }
          return $result;
        }

        $lastError = $result;

        if ($this->debug) {
          $this->securityLogger->logStructured('warning', 'QueryProcessor', 'temporary_error', [
            'error' => $result['error'] ?? 'Unknown error',
            'attempt' => $attempt + 1,
            'will_retry' => $attempt < $maxRetries
          ]);
        }

      } catch (\Exception $e) {
        $lastError = [
          'success' => false,
          'error' => $e->getMessage()
        ];

        // If not temporary error, don't retry
        if (!$this->errorHandler->isTemporaryError($e->getMessage())) {
          if ($this->debug) {
            $this->securityLogger->logStructured('error', 'QueryProcessor', 'exception_permanent', [
              'exception' => get_class($e),
              'message' => $e->getMessage(),
              'attempt' => $attempt + 1,
              'no_retry' => true
            ]);
          }

          $errorContext = [
            'query' => $query,
            'intent' => []
          ];
          return $this->errorHandler->buildErrorResponse($e->getMessage(), $errorContext);
        }

        if ($this->debug) {
          $this->securityLogger->logStructured('warning', 'QueryProcessor', 'exception_temporary', [
            'exception' => get_class($e),
            'message' => $e->getMessage(),
            'attempt' => $attempt + 1,
            'will_retry' => $attempt < $maxRetries
          ]);
        }
      }

      $attempt++;

      // If more retries available, wait and log
      if ($attempt <= $maxRetries) {
        if ($this->debug) {
          $this->securityLogger->logStructured('info', 'QueryProcessor', 'retry_attempt', [
            'attempt' => $attempt,
            'max_retries' => $maxRetries,
            'retry_delay_seconds' => $retryDelay
          ]);
        }

        sleep($retryDelay);
      }
    }

    // All retries failed
    if ($this->debug) {
      $this->securityLogger->logStructured('error', 'QueryProcessor', 'all_retries_failed', [
        'total_attempts' => $maxRetries + 1,
        'last_error' => is_array($lastError) ? ($lastError['error'] ?? 'Unknown') : 'Unknown'
      ]);
    }

    if ($lastError) {
      if (is_array($lastError) && isset($lastError['error'])) {
        $errorContext = [
          'query' => $query,
          'intent' => []
        ];
        $response = $this->errorHandler->buildErrorResponse($lastError['error'], $errorContext);
        $response['retry_info'] = [
          'attempts' => $maxRetries + 1,
          'all_failed' => true
        ];
        return $response;
      }
    }

    // Fallback
    return $this->errorHandler->buildErrorResponse('Failed after multiple attempts', ['query' => $query]);
  }

  /**
   * Execute parallel operations for query processing
   *
   * Runs context retrieval in parallel to improve performance.
   * Note: PHP doesn't have native async/await, but we can measure operations
   * and simulate parallel execution for performance tracking.
   *
   * Extracted from OrchestratorAgent::handleFullOrchestration()
   *
   * @param string $query User query to process
   * @return array Results from parallel operations with timing metrics
   */
  public function executeParallelOperations(string $query): array
  {
    $parallelStart = microtime(true);

    if ($this->debug) {
      $this->securityLogger->logStructured('info', 'QueryProcessor', 'parallel_start', [
        'query_length' => strlen($query),
        'has_memory' => $this->conversationMemory !== null
      ]);
    }

    // Initialize results
    $rawContext = [];
    $contextError = null;
    $contextDuration = 0;

    // Operation 1: Context retrieval
    try {
      $contextStart = microtime(true);
      $rawContext = $this->conversationMemory ? $this->conversationMemory->getRelevantContext($query) : [];
      $contextDuration = (microtime(true) - $contextStart) * 1000;

      if ($this->debug) {
        $this->securityLogger->logStructured('info', 'QueryProcessor', 'context_retrieval_success', [
          'duration_ms' => round($contextDuration, 2),
          'context_items' => count($rawContext),
          'context_keys' => array_keys($rawContext)
        ]);
      }
    } catch (\Exception $e) {
      $contextError = $e;
      $contextDuration = (microtime(true) - $contextStart) * 1000;

      if ($this->debug) {
        $this->securityLogger->logStructured('warning', 'QueryProcessor', 'context_retrieval_failed', [
          'duration_ms' => round($contextDuration, 2),
          'exception' => get_class($e),
          'message' => $e->getMessage(),
          'fallback' => 'empty_context'
        ]);
      }
    }

    $parallelDuration = (microtime(true) - $parallelStart) * 1000;

    // Log parallel execution results
    if ($this->debug) {
      $this->securityLogger->logStructured('info', 'QueryProcessor', 'parallel_complete', [
        'context_duration_ms' => round($contextDuration, 2),
        'parallel_total_ms' => round($parallelDuration, 2),
        'context_success' => $contextError === null,
        'overhead_ms' => round($parallelDuration - $contextDuration, 2)
      ]);
    }

    return [
      'raw_context' => $rawContext,
      'context_error' => $contextError,
      'context_duration_ms' => $contextDuration,
      'parallel_duration_ms' => $parallelDuration
    ];
  }

  /**
   * Process context decision for query
   *
   * Determines whether to use context for the query based on:
   * - Query-context relevance
   * - Context freshness
   * - Query complexity
   * - Feedback context conflicts
   *
   * Extracted from OrchestratorAgent::handleFullOrchestration()
   *
   * @param string $query User query
   * @param array $rawContext Raw context from memory
   * @return array Processed context decision with filtered context
   */
  public function processContextDecision(string $query, array $rawContext): array
  {
    if ($this->debug) {
      $this->securityLogger->logStructured('info', 'QueryProcessor', 'context_decision_start', [
        'query_length' => strlen($query),
        'raw_context_items' => count($rawContext),
        'has_feedback_context' => isset($rawContext['feedback_context'])
      ]);
    }

    // Intelligent context management (avoid conflict feedback/context)
    $contextDecision = $this->contextManager->decideContextUsage(
      $query,
      $rawContext,
      $rawContext['feedback_context'] ?? []
    );

    if ($this->debug) {
      $this->securityLogger->logStructured('info', 'QueryProcessor', 'context_decision_made', [
        'use_context' => $contextDecision['use_context'] ?? false,
        'clear_conversation_context' => $contextDecision['clear_conversation_context'] ?? false,
        'reason' => $contextDecision['reason'] ?? 'unknown',
        'confidence' => $contextDecision['confidence'] ?? 0.0
      ]);
    }

    // Filter context according to decision
    $context = $this->contextManager->filterConversationContext($rawContext, $contextDecision);
    $context = $this->contextManager->enrichContextWithDecision($context, $contextDecision);

    if ($this->debug && ($contextDecision['clear_conversation_context'] ?? false)) {
      $this->securityLogger->logStructured('warning', 'QueryProcessor', 'context_cleared', [
        'reason' => $contextDecision['reason'] ?? 'unknown',
        'previous_context_items' => count($rawContext),
        'new_context_items' => count($context)
      ]);
    }

    if ($this->debug) {
      $this->securityLogger->logStructured('info', 'QueryProcessor', 'context_decision_complete', [
        'filtered_context_items' => count($context),
        'context_keys' => array_keys($context)
      ]);
    }

    return [
      'context' => $context,
      'context_decision' => $contextDecision
    ];
  }

  /**
   * Analyze query-context relation
   *
   * Analyzes the relationship between the query and available context:
   * - Semantic similarity
   * - Entity overlap
   * - Temporal relevance
   *
   * Enriches query with context if related.
   *
   * Extracted from OrchestratorAgent::handleFullOrchestration()
   *
   * @param string $query User query
   * @param array $context Available context
   * @return array Analysis result with relevance scores and enriched query
   */
  public function analyzeQueryContextRelation(string $query, array $context): array
  {
    if ($this->debug) {
      $this->securityLogger->logStructured('info', 'QueryProcessor', 'relation_analysis_start', [
        'query_length' => strlen($query),
        'context_items' => count($context)
      ]);
    }

    $analysisStart = microtime(true);
    $contextAnalysis = $this->queryAnalyzer->analyzeQueryContextRelation($query, $context);
    $analysisDuration = (microtime(true) - $analysisStart) * 1000;

    if ($this->debug) {
      $this->securityLogger->logStructured('info', 'QueryProcessor', 'relation_analysis_complete', [
        'duration_ms' => round($analysisDuration, 2),
        'is_related' => $contextAnalysis['is_related_to_context'] ?? false,
        'relation_type' => $contextAnalysis['relation_type'] ?? 'none',
        'similarity_score' => $contextAnalysis['similarity_score'] ?? 0.0
      ]);
    }

    $enrichedQuery = $query;
    if ($contextAnalysis['is_related_to_context']) {
      $enrichStart = microtime(true);
      $enrichedQuery = $this->queryAnalyzer->enrichQueryWithContext($query, $context, $contextAnalysis);
      $enrichDuration = (microtime(true) - $enrichStart) * 1000;

      if ($this->debug) {
        $this->securityLogger->logStructured('info', 'QueryProcessor', 'query_enriched', [
          'duration_ms' => round($enrichDuration, 2),
          'original_length' => strlen($query),
          'enriched_length' => strlen($enrichedQuery),
          'enrichment_added' => strlen($enrichedQuery) - strlen($query)
        ]);
      }
    } else {
      if ($this->debug) {
        $this->securityLogger->logStructured('info', 'QueryProcessor', 'query_not_enriched', [
          'reason' => 'not_related_to_context'
        ]);
      }
    }

    return [
      'context_analysis' => $contextAnalysis,
      'enriched_query' => $enrichedQuery
    ];
  }

  /**
   * Build enriched context from analysis
   *
   * Enriches context with additional information based on analysis:
   * - Related entities
   * - Historical patterns
   * - Domain-specific metadata
   *
   * Extracted from OrchestratorAgent::handleFullOrchestration()
   *
   * @param array $context Base context
   * @param array $contextAnalysis Context analysis results
   * @return array Enriched context with additional metadata
   */
  public function buildEnrichedContext(array $context, array $contextAnalysis): array
  {
    if ($this->debug) {
      $this->securityLogger->logStructured('info', 'QueryProcessor', 'context_enrichment_start', [
        'base_context_items' => count($context),
        'analysis_keys' => array_keys($contextAnalysis)
      ]);
    }

    $enrichedContext = array_merge($context, [
      'context_analysis' => $contextAnalysis,
      'is_related_to_previous' => $contextAnalysis['is_related_to_context'],
      'relation_type' => $contextAnalysis['relation_type'],
    ]);

    if ($this->debug) {
      $this->securityLogger->logStructured('info', 'QueryProcessor', 'context_enrichment_complete', [
        'enriched_context_items' => count($enrichedContext),
        'added_keys' => array_diff(array_keys($enrichedContext), array_keys($context))
      ]);
    }

    return $enrichedContext;
  }
}
