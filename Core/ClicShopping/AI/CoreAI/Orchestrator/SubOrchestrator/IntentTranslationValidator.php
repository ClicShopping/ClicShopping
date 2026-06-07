<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

/**
 * IntentTranslationValidator Class
 *
 * Anti-hallucination guard on an intent's translated query: validates the translation against the
 * original query and falls back to the resolved query when a hallucination is detected. Promoted
 * from the OrchestratorAgent private helper validateIntentTranslation() without behaviour change.
 */

namespace ClicShopping\AI\CoreAI\Orchestrator\SubOrchestrator;

use ClicShopping\AI\Security\SecurityLogger;
use ClicShopping\AI\Security\Validation\HallucinationDetector;

class IntentTranslationValidator
{
  private HallucinationDetector $hallucinationDetector;
  private SecurityLogger $securityLogger;

  public function __construct(HallucinationDetector $hallucinationDetector, SecurityLogger $securityLogger)
  {
    $this->hallucinationDetector = $hallucinationDetector;
    $this->securityLogger = $securityLogger;
  }

  /**
   * Validate the intent's translated query against the original; fall back on hallucination.
   *
   * Validates intent['translated_query'] against the original query with the HallucinationDetector.
   * If a hallucination is detected, falls back to the resolved query (overwriting the intent's
   * translated query) and logs the fallback action. Returns the (possibly corrected) intent and
   * the validation result, which the downstream intent-decision debug log consumes.
   *
   * @param string $query Original user query
   * @param string $queryToProcess Resolved query used as the fallback translation
   * @param array $intent Intent produced by analyzeIntent()
   * @return array{intent: array, validation: array} Corrected intent and the validation result
   */
  public function validate(string $query, string $queryToProcess, array $intent): array
  {
    $translatedQuery = $intent['translated_query'] ?? $queryToProcess;

    // Use HallucinationDetector for validation (extracted from inline code)
    $validationResult = $this->hallucinationDetector->validateTranslation($query, $translatedQuery);

    if ($validationResult['hallucination_detected']) {
      // Fallback: Use original query as translated query
      $intent['translated_query'] = $queryToProcess;
      $translatedQuery = $queryToProcess;

      // Log the ACTION taken (fallback decision) - detection is already logged by HallucinationDetector
      $this->securityLogger->logStructured(
        'info',
        'OrchestratorAgent',
        'hallucination_fallback',
        [
          'action' => 'using_original_query_as_fallback',
          'original_query' => $validationResult['original_query'],
          'rejected_translation' => $validationResult['translated_query'],
          'hallucination_keywords' => $validationResult['hallucination_keywords']
        ]
      );

      error_log("   → Fallback to original: '$queryToProcess'");
    }

    return ['intent' => $intent, 'validation' => $validationResult];
  }
}
