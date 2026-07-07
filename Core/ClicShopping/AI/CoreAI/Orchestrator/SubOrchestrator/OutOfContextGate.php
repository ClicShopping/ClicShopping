<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

/**
 * OutOfContextGate Class
 *
 * Encapsulates the out-of-context decision inputs and the rejection-message construction used by
 * the three-tier out-of-context gates (primary / threshold / nuanced) in
 * OrchestratorAgent::processWithValidation(). Promoted from the OrchestratorAgent private helpers
 * evaluateOutOfContext() / buildOutOfContextError() without behaviour change.
 */

namespace ClicShopping\AI\CoreAI\Orchestrator\SubOrchestrator;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\AI\Config\DomainConfig;
use ClicShopping\AI\Config\DomainFields;
use ClicShopping\AI\Security\SecurityLogger;
use ClicShopping\AI\Security\Validation\HallucinationDetector;

class OutOfContextGate
{
  private HallucinationDetector $hallucinationDetector;
  private SecurityLogger $securityLogger;
  private bool $debug;

  public function __construct(HallucinationDetector $hallucinationDetector, SecurityLogger $securityLogger, bool $debug = false)
  {
    $this->hallucinationDetector = $hallucinationDetector;
    $this->securityLogger = $securityLogger;
    $this->debug = $debug;
  }

  /**
   * Compute the out-of-context decision inputs for a query (source data for the three-tier gate).
   *
   * Only very short queries (1-2 words with NO digit, i.e. bare product-name lookups) skip LLM
   * out-of-context detection and are allowed through with default values; every other query is evaluated by the HallucinationDetector.
   * Returns the raw $contextCheck array consumed by the decision gates in processWithValidation().
   *
   * @param string $query User query
   * @return array Context-check decision inputs (is_out_of_context, context_relevance,
   *               detected_category, confidence, suggested_action, [explanation])
   */
  public function evaluate(string $query): array
  {
    // Only skip detection for 1-2 word queries without a digit (bare product-name lookups).
    $wordCount = str_word_count($query);
    $skipOutOfContextCheck = ($wordCount <= 2 && preg_match('/\d/', $query) !== 1);

    if ($skipOutOfContextCheck) {
      if ($this->debug) {
        $this->securityLogger->logSecurityEvent(
          "Skipping out-of-context detection for short query (likely product name): '{$query}' ({$wordCount} words)",
          'info'
        );
      }
      // Set default context check (allow query to proceed)
      // Use DomainConfig to get active domain instead of hardcoding 'ecommerce'
      $activeDomain = DomainConfig::getActivities();

      // NOTE: Decision logic uses three-tier hierarchy:
      // (1) is_out_of_context (boolean gate) - authoritative rejection
      // (2) context_relevance (threshold gate) - configurable threshold-based decisions
      // (3) suggested_action (nuanced handling) - fine-grained action routing
      // All three fields are used in decision logic to ensure robust validation.
      $contextCheck = [
        'is_out_of_context' => false,
        'context_relevance' => 1.0,
        'detected_category' => $activeDomain ?: 'generic',
        'confidence' => 1.0,
        'explanation' => 'Short query - skipped out-of-context detection (likely product name)',
        'suggested_action' => 'allow'
      ];
    } else {
      // Only check out-of-context for longer queries (> 4 words)
      $contextCheck = $this->hallucinationDetector->detectOutOfContext($query);

      if ($this->debug) {
        $this->securityLogger->logStructured('info', 'OrchestratorAgent', 'out_of_context_check', [
          'query' => $query,
          'word_count' => $wordCount,
          'is_out_of_context' => $contextCheck['is_out_of_context'],
          'category' => $contextCheck['detected_category'],
          'action' => $contextCheck['suggested_action'],
          'confidence' => $contextCheck['confidence']
        ]);
      }
    }

    return $contextCheck;
  }

  /**
   * Build the rejection message shown when a query is refused by an out-of-context gate.
   *
   * Shared structure for the three decision gates (primary/threshold/nuanced): if the active
   * domain exposes an EntityConfig with entity types, the entity-aware message ($entitiesDefKey)
   * is returned; otherwise (no entity types, EntityConfig failure, or no EntityConfig class)
   * the supplied $generalMessage is returned. Each gate passes its own messages/key so the
   * per-gate wording is preserved exactly.
   *
   * @param string $activeDomain    Active business domain (from DomainConfig::getActivities()).
   * @param string $baseMessage     Initial message before entity resolution (gate-specific).
   * @param string $entitiesDefKey  getDef() key used when entity types are available; receives
   *                                an 'entity_list' placeholder.
   * @param string $generalMessage  Fallback message when no entity types can be resolved.
   * @return string The resolved rejection message.
   */
  public function buildError(string $activeDomain, string $baseMessage, string $entitiesDefKey, string $generalMessage): string
  {
    $errorMessage = $baseMessage;

    $entityConfigClass = DomainFields::resolveAppClass($activeDomain, 'EntityConfig');
    if ($entityConfigClass !== null) {
      // Use EntityConfig to get entity types dynamically
      try {
        $entityTypes = $entityConfigClass::getEntityTypes();
        if (!empty($entityTypes)) {
          $entityList = implode(', ', $entityTypes);
          $errorMessage = CLICSHOPPING::getDef($entitiesDefKey, ['entity_list' => $entityList]);
        } else {
          $errorMessage = $generalMessage;
        }
      } catch (\Exception $e) {
        // Fallback to generic message if EntityConfig fails
        $errorMessage = $generalMessage;
      }
    } else {
      // Generic message for other domains or no domain
      $errorMessage = $generalMessage;
    }

    return $errorMessage;
  }
}
