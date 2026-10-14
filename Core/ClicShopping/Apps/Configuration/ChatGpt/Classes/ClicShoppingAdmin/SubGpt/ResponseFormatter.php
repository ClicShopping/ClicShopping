<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\SubGpt;

use ClicShopping\OM\Registry;
use ClicShopping\AI\DomainsAI\Hybrid\Helper\Formatter\ResultFormatter;

/**
 * ResponseFormatter
 *
 * Formats AI responses for display.
 * Extracted from chatGpt.php AJAX handler as part of code refactoring (Task 12).
 *
 * Responsibilities:
 * - Format response using ResultFormatter
 * - Handle different response types (analytics, semantic, web_search, hybrid)
 * - Apply memory context
 * - Generate HTML output
 */
class ResponseFormatter
{
  /**
   * Format AI response for display
   *
   * @param array $aiResponse AI response from orchestrator
   * @param string $userQuery Original user query
   * @param array $metadata Entity metadata (entity_id, entity_type)
   * @param array|null $memoryContext Optional memory context for display
   * @return array Formatted result with 'content' and 'formatted' keys
   */
  public static function format(array $aiResponse, string $userQuery, array $metadata, ?array $memoryContext = null): array
  {
    // Initialize ResultFormatter
    if (!Registry::exists('ResultFormatter')) {
      Registry::set('ResultFormatter', new ResultFormatter());
    }
    $resultFormatter = Registry::get('ResultFormatter');
    
    // Prepare data to format
    // For hybrid queries, components are at root level, not in 'data'
    // Check if we have hybrid components (analytics_component, semantic_component)
    $hasHybridComponents = isset($aiResponse['analytics_component']) || isset($aiResponse['semantic_component']);
    
    if ($hasHybridComponents) {
      // Use root level for hybrid queries (contains analytics_component, semantic_component)
      $dataToFormat = $aiResponse;
    } else {
      // For other types, use 'data' key
      $dataToFormat = $aiResponse['data'] ?? [];
      // Hybrid responses may store structured data under 'result'
      if (empty($dataToFormat) && (($aiResponse['type'] ?? '') === 'hybrid') && isset($aiResponse['result']) && is_array($aiResponse['result'])) {
        $dataToFormat = $aiResponse['result'];
      }
    }
    
    if (defined('CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER') && CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER === 'True') {
      error_log('[INFO] FORMATTING RESPONSE:');
      error_log('   Has hybrid components: ' . ($hasHybridComponents ? 'YES' : 'NO'));
      error_log('   Type: ' . gettype($dataToFormat));
      error_log('   Is array: ' . (is_array($dataToFormat) ? 'YES' : 'NO'));

      if (is_array($dataToFormat)) {
        error_log('   Keys: ' . implode(', ', array_keys($dataToFormat)));
        error_log('   Has analytics_component: ' . (isset($dataToFormat['analytics_component']) ? 'YES' : 'NO'));
        error_log('   Has semantic_component: ' . (isset($dataToFormat['semantic_component']) ? 'YES' : 'NO'));
      }
    }
    
    // Ensure dataToFormat is an array
    if (!is_array($dataToFormat)) {
      $dataToFormat = ['type' => 'error', 'message' => 'Format invalide'];
    }
    
    // Set default type if missing
    // CRITICAL: Check aiResponse type first (for hybrid queries)
    if (!isset($dataToFormat['type'])) {
      $dataToFormat['type'] = $aiResponse['type'] ?? 'semantic_results';
    }
    
    // Add query if missing
    if (!isset($dataToFormat['query'])) {
      $dataToFormat['query'] = $userQuery;
    }
    
    // Add entity metadata
    $dataToFormat['entity_id'] = $metadata['entity_id'] ?? 0;
    $dataToFormat['entity_type'] = $metadata['entity_type'] ?? 'unknown';
    
    // Preserve source_attribution if present
    if (isset($aiResponse['source_attribution'])) {
      $dataToFormat['source_attribution'] = $aiResponse['source_attribution'];
    } elseif (isset($aiResponse['data']['source_attribution'])) {
      $dataToFormat['source_attribution'] = $aiResponse['data']['source_attribution'];
    }
    
    // Add text_response if available but no structured data
    if (isset($aiResponse['text_response']) && !empty($aiResponse['text_response'])) {
      if (!isset($dataToFormat['response']) || empty($dataToFormat['response'])) {
        $dataToFormat['response'] = $aiResponse['text_response'];
      }
    }
    
    // Handle different response types
    $responseType = $dataToFormat['type'];
    
    if ($responseType === 'web_search_response' && isset($aiResponse['text_response'])) {
      // Use pre-formatted HTML for web search
      $formatted = $aiResponse['text_response'];
      $formattedResult = ['content' => $formatted];
      
      if (defined('CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER') && CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER === 'True') {
        error_log('✅ Using pre-formatted WebSearch HTML: ' . strlen($formatted) . ' chars');
      }
    } else {
      // Use ResultFormatter for other types
      if ($memoryContext !== null && !empty($memoryContext)) {
        $formattedResult = $resultFormatter->formatWithMemory($dataToFormat, $memoryContext);
      } else {
        $formattedResult = $resultFormatter->format($dataToFormat);
      }
      
      $formatted = $formattedResult['content'] ?? '';
      
      if (defined('CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER') && CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER === 'True') {
        error_log('✅ Using ResultFormatter: ' . strlen($formatted) . ' chars');
        if (isset($formattedResult['has_memory_context'])) {
          error_log('   Memory context integrated: YES');
        }
      }
    }
    
    return [
      'content' => $formatted,
      'formatted_result' => $formattedResult,
      'data_to_format' => $dataToFormat
    ];
  }
  
  /**
   * Format response with memory context
   *
   * @param array $aiResponse AI response from orchestrator
   * @param string $userQuery Original user query
   * @param array $metadata Entity metadata
   * @param array $context Memory context
   * @return array Formatted result
   */
  public static function formatWithMemory(array $aiResponse, string $userQuery, array $metadata, array $context): array
  {
    $memoryContext = ContextManager::transformContextForDisplay($context);
    return self::format($aiResponse, $userQuery, $metadata, $memoryContext);
  }
}
