<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\DomainsAI\Semantic\Helper\Formatter;



use ClicShopping\OM\Hash;
use ClicShopping\OM\Registry;
use ClicShopping\AI\Security\LlmGuardrails;
use ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\Gpt;
use ClicShopping\AI\Config\DomainConfig;
use ClicShopping\AI\DomainsAI\Hybrid\Helper\Formatter\SubResultFormatters\AbstractFormatter;

/**
 * SemanticFormatter - Formats semantic search results
 */

class SemanticFormatter extends AbstractFormatter
{
  /**
   * @var \ClicShopping\OM\Language Language instance for translations
   */
  private $language;
  
  /**
   * @var string Current language code
   */
  private string $languageCode;
  
  /**
   * Constructor
   * 
   * @param bool $debug Enable debug mode
   * @param bool $displaySql Display SQL queries
   */
  public function __construct(bool $debug = false, bool $displaySql = false)
  {
    parent::__construct($debug, $displaySql);
    
    // Initialize language
    $this->language = Registry::get('Language');
    $this->languageCode = $this->language->get('code');
    
    // Load language definitions once (null = use current user language)
    DomainConfig::loadAgnosticLanguageFile('rag_formatters', null);
  }
  
  public function canHandle(array $results): bool
  {
    $type = $results['type'] ?? '';
    // Accept both 'semantic' (from AgentResponseHelper) and 'semantic_results' (legacy)
    return $type === 'semantic' || $type === 'semantic_results';
  }

  public function format(array $results): array
  {
    $question = $results['query'] ?? $results['question'] ?? 'Unknown request';

    $output = "<div class='semantic-results'>";
    $output .= "<h4>" . $this->language->getDef('text_rag_semantic_results_for') . " " . htmlspecialchars($question) . "</h4>";

    // Display source attribution
    if (isset($results['source_attribution'])) {
      $output .= $this->formatSourceAttribution($results['source_attribution']);
    }

    // Guardrails
    $output .= "<div class='mt-2'></div>";
    $responseContent = $results['response'] ?? $results['interpretation'] ?? '';
    
    $groundingMetadata = [
      'grounding_score' => $results['grounding_score'] ?? null,
      'grounding_decision' => $results['grounding_decision'] ?? null,
      'hallucination_detected' => $results['hallucination_detected'] ?? false,
      'grounding_metadata' => $results['grounding_metadata'] ?? null,
    ];
    
    $lmGuardrails = LlmGuardrails::checkGuardrails(
      $question, 
      Hash::displayDecryptedDataText($responseContent),
      $groundingMetadata
    );

    if (is_array($lmGuardrails)) {
      $output .= $this->formatGuardrailsMetrics($lmGuardrails, $groundingMetadata);
    } else {
      $output .= "<div class='alert alert-warning'>" . htmlspecialchars($lmGuardrails) . "</div>";
    }

    $output .= "<div class='mt-2'></div>";

    // Display the response
    if (!empty($results['response'])) {
      $output .= "<div class='response'><strong>" . $this->language->getDef('text_rag_semantic_response') . "</strong><br>" 
              . Hash::displayDecryptedDataText($results['response']) . "</div>";
    } elseif (!empty($results['interpretation'])) {
      $output .= "<div class='response'><strong>" . $this->language->getDef('text_rag_semantic_response') . "</strong><br>" 
              . Hash::displayDecryptedDataText($results['interpretation']) . "</div>";
    }

    if (isset($results['source_attribution']['document_names']) && !empty($results['source_attribution']['document_names'])) {
      $docNames = $results['source_attribution']['document_names'];
      
      $output .= "<div class='mt-3'></div>";
      $output .= "<div class='document-sources' style='font-size: 0.9em; color: #666; font-style: italic;'>";
      $output .= "<strong>" . (count($docNames) > 1 ? $this->language->getDef('text_rag_semantic_sources') : $this->language->getDef('text_rag_semantic_source')) . " :</strong> ";
      
      if (count($docNames) === 1) {
        $output .= htmlspecialchars($docNames[0]);
      } elseif (count($docNames) === 2) {
        $output .= htmlspecialchars($docNames[0]) . " " . $this->language->getDef('text_rag_semantic_and') . " " . htmlspecialchars($docNames[1]);
      } else {
        // More than 2 documents: "doc1, doc2 et doc3"
        $lastDoc = array_pop($docNames);
        $output .= htmlspecialchars(implode(', ', $docNames)) . " " . $this->language->getDef('text_rag_semantic_and') . " " . htmlspecialchars($lastDoc);
      }
      
      $output .= "</div>";
    }

    $output .= "</div>";

    // Save audit data
    $auditExtra = [
      'embeddings_context' => $results['embeddings_context'] ?? [],
      'similarity_scores'  => $results['similarity_scores'] ?? [],
      'processing_chain'   => $results['processing_chain'] ?? []
    ];
    //Gpt::saveData($question, $output, $auditExtra);

    if(!empty($results['similarity_scores'])) {
      $output .= '<div class="mt-2"></div>';
      $output .= 'Similarity_scores : ' . $results['similarity_scores'] ?? [];
    }

    return [
      'type' => 'formatted_results',
      'content' => $output
    ];
  }

  /**
   * Format guardrails metrics for semantic response
   *
   * @param array $guardrails Guardrails evaluation results
   * @param array $groundingMetadata Grounding metadata including hallucination detection
   * @return string Formatted HTML output (empty if no warnings)
   */
  private function formatGuardrailsMetrics(array $guardrails, array $groundingMetadata = []): string
  {
    $hasWarnings = false;
    
    // Check for hallucination detection
    if (!empty($groundingMetadata['hallucination_detected'])) {
      $hasWarnings = true;
    }
    
    // Check for low grounding score (< 80%)
    if (isset($groundingMetadata['grounding_score']) && $groundingMetadata['grounding_score'] < 0.8) {
      $hasWarnings = true;
    }
    
    // Check for flagged or rejected grounding decision
    if (isset($groundingMetadata['grounding_decision']) && 
        in_array($groundingMetadata['grounding_decision'], ['FLAG', 'REJECT'], true)) {
      $hasWarnings = true;
    }
    
    if (!$hasWarnings) {
      return '';
    }
    
    // If we have warnings, display the metrics section
    $output = "<div class='guardrails-metrics'>";
    $output .= '<h6>🔍 ' . $this->language->getDef('text_rag_semantic_quality_metrics') . '</h6>';
    
    if (!empty($groundingMetadata['hallucination_detected'])) {
      $output .= '<div class="alert alert-warning" style="margin-bottom: 10px; padding: 8px;">';
      $output .= '<strong>⚠️ ' . $this->language->getDef('text_rag_semantic_hallucination_detected') . '</strong> ';
      $output .= $this->language->getDef('text_rag_semantic_hallucination_message');
      $output .= '</div>';
    }
    
    if (isset($groundingMetadata['grounding_score']) && $groundingMetadata['grounding_score'] !== null) {
      $groundingScore = round($groundingMetadata['grounding_score'] * 100);
      $groundingDecision = $groundingMetadata['grounding_decision'] ?? 'UNKNOWN';
      
      // Determine CSS class based on score
      if ($groundingScore >= 80) {
        $groundingClass = 'text-success';
      } elseif ($groundingScore >= 60) {
        $groundingClass = 'text-warning';
      } else {
        $groundingClass = 'text-danger';
      }
      
      // Add decision badge
      $decisionBadge = '';
      if ($groundingDecision === 'ACCEPT') {
        $decisionBadge = '<span class="badge bg-success">' . $this->language->getDef('text_rag_semantic_accepted') . '</span>';
      } elseif ($groundingDecision === 'FLAG') {
        $decisionBadge = '<span class="badge bg-warning">' . $this->language->getDef('text_rag_semantic_flagged') . '</span>';
      } elseif ($groundingDecision === 'REJECT') {
        $decisionBadge = '<span class="badge bg-danger">' . $this->language->getDef('text_rag_semantic_rejected') . '</span>';
      }
      
      $output .= '<p class="' . $groundingClass . '"><strong>🎯 ' . $this->language->getDef('text_rag_semantic_reliability_score') . ' ' . $groundingScore . '%</strong> ' . $decisionBadge . '</p>';
    }
    
    $output .= "</div>";
    return $output;
  }
}
