<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\DomainsAI\Semantic\Processor;


use ClicShopping\AI\Security\SecurityLogger;
use ClicShopping\AI\DomainsAI\Semantic\Patterns\ClassificationEnginePatterns;
use ClicShopping\Sites\Common\HTMLOverrideCommon;
use ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\Gpt;
use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;
use ClicShopping\AI\Config\DomainConfig;

/**
 * ClassificationEngine
 * 
 * Core classification logic for determining query types using Pure LLM mode.
 * 
 * REFACTORED 2025-12-30:
 * - Removed unused pattern-based functions (Pure LLM mode)
 * - Uses HTMLOverrideCommon::cleanJsonEntities() for entity cleaning
 * - Uses ClassificationEnginePatterns for JSON fixing patterns
 * - Reduced from 577 lines to ~350 lines (39% reduction)
 * 
 * @version 2.0 - Refactored for Pure LLM mode
 */

class ClassificationEngine
{
  private static ?SecurityLogger $logger = null;
  
  /**
   * Initialize logger
   */
  private static function initLogger(): void
  {
    if (self::$logger === null) {
      self::$logger = new SecurityLogger();
    }
  }
  
  /**
   * Fix malformed JSON array closing
   * 
   * This causes JSON parsing to fail for hybrid queries
   * 
   * Uses ClassificationEnginePatterns for the fix logic.
   * 
   * @param string $json JSON string to fix
   * @return string Fixed JSON string
   */
  private static function fixMalformedArrayClosing(string $json): string
  {
    $fixed = ClassificationEnginePatterns::fixMalformedArrayClosing($json);
    
    // Log if we made a fix
    if ($fixed !== $json && self::$logger && 
        defined('CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER') && CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER === 'True') {
      self::$logger->logStructured(
        'info',
        'ClassificationEngine',
        'json_array_closing_fixed',
        [
          'original' => $json,
          'fixed' => $fixed,
          'note' => 'Fixed malformed array closing: removed } before ]'
        ]
      );
    }
    
    return $fixed;
  }
  
  /**
   * Classifies query using GPT with improved prompt and JSON response
   * 
   * 
   * IMPROVEMENTS:
   * - Loads prompt from language file (rag_classification.txt)
   * - Returns structured array with type, confidence, reasoning
   * - Supports 4 categories: analytics, semantic, hybrid, web_search
   * - Validates confidence scores (0.0-1.0)
   * - Fallback to old prompt if new prompt fails
   * 
   * @param string $text Text to classify
   * @return array ['type' => string, 'confidence' => float, 'reasoning' => string, 'sub_types' => array]
   */
  public static function checkSemantics(string $text): array
  {
    self::initLogger();

    try {
      // Load language definitions for classification prompt
      $CLICSHOPPING_Language = Registry::get('Language');
      DomainConfig::loadLanguageFile('rag_classification');
      $prompt = $CLICSHOPPING_Language->getDef('text_rag_classification', ['QUERY' => $text]);

/*
      $CLICSHOPPING_Language->loadDefinitions('rag_classification', 'en', null, 'ClicShoppingAdmin');
      
      // Load new classification prompt from language file
      $promptTemplate = CLICSHOPPING::getDef('text_rag_classification');
      
      if (!$promptTemplate || $promptTemplate === 'text_rag_classification') {
        // Language definition not found, use fallback
        throw new \Exception('Classification prompt not found in language file');
      }
      
      // Replace {{QUERY}} placeholder with actual query
      $prompt = str_replace('{{QUERY}}', $text, $promptTemplate);
*/ 
      // Get GPT response (expecting JSON)
      $response = Gpt::getGptResponse($prompt, 200); // Increased max tokens for JSON response
      
      // Log raw response for debugging
      if (self::$logger && defined('CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER') &&  CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER === 'True') {
        self::$logger->logStructured(
          'info',
          'ClassificationEngine',
          'checkSemantics_raw_response',
          [
            'query' => $text,
            'response' => $response
          ]
        );
      }
      
      // Clean response: Remove markdown code blocks if present
      // LLMs often wrap JSON in ```json ... ``` despite instructions
      $cleanResponse = trim($response);
      
      // Remove markdown code blocks (```json ... ``` or ``` ... ```)
      $cleanResponse = preg_replace('/^```(?:json)?\s*/m', '', $cleanResponse);
      $cleanResponse = preg_replace('/\s*```$/m', '', $cleanResponse);
      
      // Remove any leading/trailing whitespace again
      $cleanResponse = trim($cleanResponse);
      
      // Comprehensive HTML entity cleanup
      // Uses HTMLOverrideCommon::cleanJsonEntities() for consistent entity handling
      $cleanResponse = HTMLOverrideCommon::cleanJsonEntities($cleanResponse);
      
      // LLMs sometimes close arrays with }] which is invalid JSON
      // Example: ["analytics", "semantic"}] → ["analytics", "semantic"]]
      $cleanResponse = self::fixMalformedArrayClosing($cleanResponse);
      
      // Log cleaned response if different from original
      if ($cleanResponse !== trim($response) && self::$logger && defined('CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER') && CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER === 'True') {
        self::$logger->logStructured(
          'info',
          'ClassificationEngine',
          'checkSemantics_cleaned_response',
          [
            'query' => $text,
            'original' => $response,
            'cleaned' => $cleanResponse,
            'note' => 'Removed markdown code blocks, decoded HTML entities, and fixed malformed array closing'
          ]
        );
      }
      
      // Try to parse JSON response
      $result = json_decode($cleanResponse, true);
      
      // Check if JSON parsing succeeded
      if (json_last_error() !== JSON_ERROR_NONE) {
        // Log JSON error for debugging
        if (self::$logger && defined('CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER') && CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER === 'True') {
          self::$logger->logStructured(
            'warning',
            'ClassificationEngine',
            'json_parse_error',
            [
              'query' => $text,
              'error' => json_last_error_msg(),
              'cleaned_response' => $cleanResponse
            ]
          );
        }
        $result = null;
      }
      
      // If JSON parsing failed, try to extract classification from markdown/text response
      if ($result === null) {
        // Try multiple patterns to extract type and confidence from markdown response
        $type = null;
        $confidence = 0.5;
        $reasoning = '';
        $sub_types = [];
        
        // Pattern 1: Classification: **TYPE** followed by Confidence: X.X (new format)
        if (preg_match('/Classification:\s*\*\*(\w+)\*\*.*?Confidence:\s*([\d.]+)/is', $response, $matches)) {
          $type = strtolower(trim($matches[1]));
          $confidence = (float)$matches[2];
        }
        // Pattern 2: **Classification:** TYPE (confidence: X.X) - most common format
        elseif (preg_match('/\*\*Classification:\*\*\s*(\w+)\s*\(confidence:\s*([\d.]+)\)/i', $response, $matches)) {
          $type = strtolower(trim($matches[1]));
          $confidence = (float)$matches[2];
        }
        // Pattern 3: **Classification: TYPE (confidence: X.X)** - with closing **
        elseif (preg_match('/\*\*Classification:\s*(\w+)\s*\(confidence:\s*([\d.]+)\)\*\*/i', $response, $matches)) {
          $type = strtolower(trim($matches[1]));
          $confidence = (float)$matches[2];
        }
        // Pattern 4: TYPE (confidence: X.X) at start of line
        elseif (preg_match('/^[\s\-\*]*(\w+)\s*\(confidence:\s*([\d.]+)\)/im', $response, $matches)) {
          $type = strtolower(trim($matches[1]));
          $confidence = (float)$matches[2];
        }
        // Pattern 5: **Intent Type:** TYPE followed by **Confidence:** X.X
        elseif (preg_match('/\*\*Intent Type:\*\*\s*(\w+)/i', $response, $typeMatches) &&
                preg_match('/\*\*Confidence:\*\*\s*([\d.]+)/i', $response, $confMatches)) {
          $type = strtolower(trim($typeMatches[1]));
          $confidence = (float)$confMatches[1];
        }
        // Pattern 6: **Classification:** TYPE on separate line, then **Confidence:** X.X
        elseif (preg_match('/\*\*Classification:\*\*\s*(\w+)/i', $response, $typeMatches) &&
                preg_match('/\*\*Confidence:\*\*\s*([\d.]+)/i', $response, $confMatches)) {
          $type = strtolower(trim($typeMatches[1]));
          $confidence = (float)$confMatches[1];
        }
        // Pattern 7: "classified as **TYPE**" followed by "**Confidence: X.X**"
        elseif (preg_match('/classified\s+as\s+\*\*(\w+)\*\*/i', $response, $typeMatches) &&
                preg_match('/\*\*Confidence:\s*([\d.]+)\*\*/i', $response, $confMatches)) {
          $type = strtolower(trim($typeMatches[1]));
          $confidence = (float)$confMatches[1];
        }
        // Pattern 8: "should be classified as **TYPE**" followed by "**Confidence: X.X**"
        elseif (preg_match('/should\s+be\s+classified\s+as\s+\*\*(\w+)\*\*/i', $response, $typeMatches) &&
                preg_match('/\*\*Confidence:\s*([\d.]+)\*\*/i', $response, $confMatches)) {
          $type = strtolower(trim($typeMatches[1]));
          $confidence = (float)$confMatches[1];
        }
        
        if (!$type) {
          throw new \Exception('Could not extract classification from response');
        }
        
        // Extract reasoning (text after "Reason:")
        if (preg_match('/\*\*Reason[^:]*:\*\*\s*(.+?)(?:\n\n|$)/s', $response, $reasonMatches)) {
          $reasoning = trim($reasonMatches[1]);
        } elseif (preg_match('/Reason:\s*(.+?)(?:\n\n|$)/s', $response, $reasonMatches)) {
          $reasoning = trim($reasonMatches[1]);
        }
        
        // Extract sub_types for hybrid queries
        if ($type === 'hybrid' && preg_match('/\(([^)]+)\s*\+\s*([^)]+)\)/i', $response, $subMatches)) {
          $sub_types = [trim($subMatches[1]), trim($subMatches[2])];
        }
        
        $result = [
          'type' => $type,
          'confidence' => $confidence,
          'reasoning' => $reasoning,
          'sub_types' => $sub_types
        ];
      }
      
      // Validate response structure
      if (!isset($result['type']) || !isset($result['confidence'])) {
        throw new \Exception('Missing required fields in JSON response');
      }
      
      // Validate type (must be one of 4 categories)
      $validTypes = ['analytics', 'semantic', 'hybrid', 'web_search'];
      if (!in_array($result['type'], $validTypes, true)) {
        throw new \Exception('Invalid type: ' . $result['type']);
      }
      
      // Validate confidence (must be between 0.0 and 1.0)
      $confidence = (float)$result['confidence'];
      if ($confidence < 0.0 || $confidence > 1.0) {
        throw new \Exception('Invalid confidence: ' . $confidence);
      }
      
      // Ensure sub_types exists (empty array if not hybrid)
      if (!isset($result['sub_types'])) {
        $result['sub_types'] = [];
      }

      // Ensure action fields exist for tool selection
      if (!isset($result['action'])) {
        $result['action'] = null;
      }
      if (!isset($result['action_params']) || !is_array($result['action_params'])) {
        $result['action_params'] = [];
      }
      
      // Log successful classification
      if (self::$logger && defined('CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER') && CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER === 'True') {
        self::$logger->logStructured(
          'info',
          'ClassificationEngine',
          'checkSemantics_success',
          [
            'query' => $text,
            'type' => $result['type'],
            'confidence' => $confidence,
            'reasoning' => $result['reasoning'] ?? 'N/A',
            'sub_types' => $result['sub_types']
          ]
        );
      }
      
      return [
        'type' => $result['type'],
        'confidence' => $confidence,
        'reasoning' => $result['reasoning'] ?? '',
        'sub_types' => $result['sub_types'],
        'action' => $result['action'],
        'action_params' => $result['action_params']
      ];
      
    } catch (\Exception $e) {
      // Log error
      if (self::$logger) {
        self::$logger->logStructured(
          'warning',
          'ClassificationEngine',
          'checkSemantics_fallback',
          [
            'query' => $text,
            'error' => $e->getMessage(),
            'fallback' => 'using old prompt'
          ]
        );
      }
      
      // Fallback to old prompt (simple text response)
      // Load fallback prompt from language file
      $CLICSHOPPING_Language = Registry::get('Language');
      DomainConfig::loadLanguageFile('rag_classification');
      $prompt = $CLICSHOPPING_Language->getDef('text_rag_classification_fallback', ['QUERY' => $text]);

      $response = Gpt::getGptResponse($prompt, 20);
      $type = trim(strtolower($response));
      
      // Validate old prompt response - default to 'semantic' if invalid
      if (!in_array($type, ['analytics', 'semantic'], true)) {
        $type = 'semantic'; // Default fallback
      }
      
      // Return in new format with default values
      return [
        'type' => $type,
        'confidence' => 0.5, // Default medium confidence
        'reasoning' => 'Fallback classification (old prompt)',
        'sub_types' => []
      ];
    }
  }
}
