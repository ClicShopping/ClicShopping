<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\SubGpt;

/**
 * ResponseValidator
 *
 * Validates response structure before sending to frontend.
 * Extracted from chatGpt.php AJAX handler as part of code refactoring (Task 12).
 *
 * Responsibilities:
 * - Validate response structure
 * - Check required fields
 * - Validate data types
 * - Generate validation report
 */
class ResponseValidator
{
  /**
   * Validate response structure before sending to frontend
   *
   * @param array $response Response data to validate
   * @return array Validation result with 'valid', 'errors', 'warnings' keys
   */
  public static function validate(array $response): array
  {
    $errors = [];
    $warnings = [];
    
    // Required fields validation
    $requiredFields = [
      'success' => 'boolean',
      'interaction_id' => 'string',
      'text_response' => 'string',
      'type' => 'string',
      'confidence' => 'numeric',
      'agent_used' => 'string',
      'execution_time' => 'numeric',
      'entity_id' => 'numeric',
      'entity_type' => 'string',
      'language_id' => 'numeric'
    ];
    
    foreach ($requiredFields as $field => $expectedType) {
      if (!isset($response[$field])) {
        $errors[] = "Missing required field: {$field}";
        continue;
      }
      
      $value = $response[$field];
      $actualType = gettype($value);
      
      // Type validation
      switch ($expectedType) {
        case 'boolean':
          if (!is_bool($value)) {
            $errors[] = "Field '{$field}' must be boolean, got {$actualType}";
          }
          break;
          
        case 'string':
          if (!is_string($value)) {
            $errors[] = "Field '{$field}' must be string, got {$actualType}";
          } elseif ($field === 'text_response' && empty(trim($value))) {
            $warnings[] = "Field 'text_response' is empty";
          }
          break;
          
        case 'numeric':
          if (!is_numeric($value) && !is_int($value) && !is_float($value)) {
            $errors[] = "Field '{$field}' must be numeric, got {$actualType}";
          }
          break;
      }
    }
    
    // Validate nested structures
    if (isset($response['metrics'])) {
      $metricsValidation = self::validateMetrics($response['metrics']);
      $errors = array_merge($errors, $metricsValidation['errors']);
      $warnings = array_merge($warnings, $metricsValidation['warnings']);
    } else {
      $warnings[] = "Missing 'metrics' object";
    }
    
    if (isset($response['metadata'])) {
      if (!is_array($response['metadata'])) {
        $errors[] = "Field 'metadata' must be an array";
      }
    } else {
      $warnings[] = "Missing 'metadata' object";
    }
    
    // Validate confidence range (0-1)
    if (isset($response['confidence'])) {
      $conf = (float)$response['confidence'];
      if ($conf < 0 || $conf > 1) {
        $warnings[] = "Confidence score out of range [0,1]: {$conf}";
      }
    }
    
    // Validate type is one of expected values
    if (isset($response['type'])) {
      $validTypes = ['analytics', 'semantic', 'web_search', 'hybrid', 'error', 'clarification'];
      if (!in_array($response['type'], $validTypes)) {
        $warnings[] = "Unexpected type value: {$response['type']}";
      }
    }
    
    return [
      'valid' => empty($errors),
      'errors' => $errors,
      'warnings' => $warnings
    ];
  }
  
  /**
   * Validate metrics object
   *
   * @param array $metrics Metrics data
   * @return array Validation result with 'errors' and 'warnings' keys
   */
  public static function validateMetrics(array $metrics): array
  {
    $errors = [];
    $warnings = [];
    
    if (!is_array($metrics)) {
      $errors[] = "Field 'metrics' must be an array";
      return ['errors' => $errors, 'warnings' => $warnings];
    }
    
    $requiredMetrics = [
      'confidence_score',
      'security_score',
      'hallucination_score',
      'response_quality',
      'relevance_score'
    ];
    
    foreach ($requiredMetrics as $metric) {
      if (!isset($metrics[$metric])) {
        $warnings[] = "Missing metric: {$metric}";
      } elseif (!is_numeric($metrics[$metric])) {
        $errors[] = "Metric '{$metric}' must be numeric";
      }
    }
    
    return ['errors' => $errors, 'warnings' => $warnings];
  }
  
  /**
   * Check required fields in response
   *
   * @param array $response Response data
   * @param array $requiredFields List of required field names
   * @return array Missing fields
   */
  public static function checkRequiredFields(array $response, array $requiredFields): array
  {
    $missing = [];
    
    foreach ($requiredFields as $field) {
      if (!isset($response[$field])) {
        $missing[] = $field;
      }
    }
    
    return $missing;
  }
}
