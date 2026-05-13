<?php
/**
 *
 * @copyright 2008 - https://www.clicshopping.org
 * @Brand : ClicShoppingAI(TM) at Inpi all right Reserved
 * @Licence GPL 2 & MIT
 * @Info : https://www.clicshopping.org/forum/trademark/
 *
 */

namespace ClicShopping\AI\DomainsAI\WebSearch\Executor;

use ClicShopping\AI\DomainsAI\CoreAI\Helper\AgentResponseHelper;
use ClicShopping\AI\InterfacesAI\EntityHelperInterface;
use ClicShopping\AI\Security\SecurityLogger;
use ClicShopping\AI\CoreAI\Memory\ConversationMemory;
use ClicShopping\AI\DomainsAI\WebSearch\WebSearchFacade;

/**
 * WebSearchQueryExecutor Class
 *
 * Responsibility: Execute web search queries by delegating to WebSearchFacade.
 * This class handles context resolution and entity tracking, then delegates
 * actual search execution to the unified websearch engine.
 *
  *
 * This executor works with any domain (Ecommerce, HR, Finance, Trading, etc.)
 * by injecting the appropriate EntityHelper.
 */
class WebSearchQueryExecutor
{
  private SecurityLogger $logger;
  private bool $debug;
  private ?ConversationMemory $conversationMemory;
  private ?EntityHelperInterface $entityHelper;

  /**
   * Constructor
   *
   * @param bool $debug Enable debug logging
   * @param ConversationMemory|null $conversationMemory Optional conversation memory for context
   * @param EntityHelperInterface|null $entityHelper Optional entity helper for domain-specific lookups
   */
  public function __construct(
    bool $debug = false,
    ?ConversationMemory $conversationMemory = null,
    ?EntityHelperInterface $entityHelper = null
  ) {
    $this->logger = new SecurityLogger();
    $this->debug = $debug;
    $this->conversationMemory = $conversationMemory;
    $this->entityHelper = $entityHelper;
  }

  /**
   * Execute a web search query
   *
   * Simplified implementation that delegates to WebSearchFacade (unified engine).
   * Handles context resolution and entity tracking, then delegates search execution.
   *
   * @param string $query Web search query
   * @param array $context Context information (language_id, user_id, etc.)
   * @return array Result with web data
   */
  public function execute(string $query, array $context = []): array
  {
    try {
      if ($this->debug) {
        $this->logger->logSecurityEvent(
          "WebSearchQueryExecutor: Executing web search query: {$query}",
          'info'
        );
      }

      // Step 1: Resolve contextual references
      $resolvedQuery = $query;
      $contextUsed = null;
      $lastEntity = null;
      $isImplicitContext = false;
      
      if ($this->conversationMemory !== null) {
        try {
          $resolutionResult = $this->conversationMemory->resolveContextualReferences($query);
          
          if ($resolutionResult['has_references']) {    
            $isImplicitContext = $resolutionResult['is_implicit_context'] ?? false;
            $lastEntity = $resolutionResult['last_entity'] ?? null;
            
            if (!empty($resolutionResult['resolved_query'])) {
              $resolvedQuery = $resolutionResult['resolved_query'];
              $contextUsed = $resolutionResult['context_used'];
            }
            
            if ($this->debug) {
              if ($isImplicitContext && $lastEntity !== null) {
                $this->logger->logSecurityEvent(
                  "Implicit contextual query detected with last entity: {$lastEntity['type']} (ID: {$lastEntity['id']})",
                  'info'
                );
              } else {
                $this->logger->logSecurityEvent(
                  "Contextual references resolved in web search: '{$query}' -> '{$resolvedQuery}'",
                  'info'
                );
              }
            }
          }
        } catch (\Exception $e) {
          $this->logger->logSecurityEvent(
            "Error resolving contextual references: " . $e->getMessage(),
            'warning'
          );
        }
      }

      // Step 2: Delegate to WebSearchFacade (unified engine)
      if ($this->debug) {
        $this->logger->logSecurityEvent(
          "Using unified engine (WebSearchFacade) for query: {$resolvedQuery}",
          'info'
        );
      }

      // Instantiate WebSearchFacade
      $facade = new WebSearchFacade();
      
      // Prepare options
      $options = [
        'language_id' => $context['language_id'] ?? 1,
        'user_id' => $context['user_id'] ?? null,
        'context_entity' => $lastEntity
      ];
      
      // Delegate to WebSearchFacade
      $result = $facade->search($resolvedQuery, $options);
      
      // CRITICAL: Check if user input is required
      // If WebSearchFacade returns user_input_required type, return it directly
      if (isset($result['type']) && $result['type'] === 'user_input_required') {
        if ($this->debug) {
          $this->logger->logSecurityEvent(
            "User input required for query: {$resolvedQuery}",
            'info'
          );
        }
        
        // Return the UserInputRequiredResponse directly
        // The orchestrator will handle displaying the prompt to the user
        return $result;
      }
      
      // Step 3: Track entity in memory (if product found in internal_product)
      if (isset($result['internal_product']['product_id']) && $this->conversationMemory !== null) {
        try {
          $this->conversationMemory->setLastEntity(
            (int)$result['internal_product']['product_id'],
            'product'
          );
          
          if ($this->debug) {
            $this->logger->logSecurityEvent(
              "Stored product entity in memory: product (ID: {$result['internal_product']['product_id']})",
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
      
      // Step 4: Format response using AgentResponseHelper
      return AgentResponseHelper::createWebSearchResponse(
        $query,
        $result,
        $result['success'] ?? true,
        [
          'context_used' => $contextUsed !== null || $lastEntity !== null,
          'query_resolved' => $resolvedQuery !== $query,
          'context_entity' => $lastEntity,
          'execution_time' => $result['metadata']['execution_time'] ?? 0,
          'modes_used' => $result['metadata']['modes_used'] ?? []
        ]
      );

    } catch (\RuntimeException $e) {
      if ($this->debug) {
        $this->logger->logSecurityEvent(
          "WebSearchFacade not available: " . $e->getMessage(),
          'warning'
        );
      }

      return AgentResponseHelper::createErrorResponse(
        $query,
        'La recherche web n\'est pas disponible actuellement. Veuillez réessayer plus tard.',
        'web_search',
        [
          'error_type' => 'configuration_error',
          'component' => 'WebSearchQueryExecutor::execute',
          'technical_details' => $e->getMessage(),
        ]
      );
    } catch (\Exception $e) {
      $errorId = uniqid('web_', true);
      $this->logger->logSecurityEvent(
        "Error executing web search [ID: {$errorId}]: " . $e->getMessage(),
        'error'
      );

      return AgentResponseHelper::createErrorResponse(
        $query,
        'Unable to execute web search. Please try again.',
        'web_search',
        [
          'error_id' => $errorId,
          'error_type' => 'execution_error',
          'component' => 'WebSearchQueryExecutor::execute',
        ]
      );
    }
  }
}
