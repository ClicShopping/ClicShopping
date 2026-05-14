<?php
/**
 *
 * @copyright 2008 - https://www.clicshopping.org
 * @Brand : ClicShoppingAI(TM) at Inpi all right Reserved
 * @Licence GPL 2 & MIT
 * @Info : https://www.clicshopping.org/forum/trademark/
 *
 */

namespace ClicShopping\AI\CoreAI\Orchestrator\SubCorrectionAgent;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\AI\Security\SecurityLogger;
use ClicShopping\AI\Infrastructure\Storage\MariaDBVectorStore;
use ClicShopping\AI\Infrastructure\Orm\DoctrineOrm;
use LLPhant\Embeddings\Document;

/**
 * FeedbackLearner Class
 * Processes user feedback to improve correction strategies
 * Identifies error patterns and stores correction patterns from user feedback
 */
class FeedbackLearner
{
  private MariaDBVectorStore $correctionStore;
  private SecurityLogger $logger;
  private string $userId;
  private int $languageId;
  private bool $debug;
  private CorrectionMemory $memory;

  /**
   * Constructor
   *
   * @param MariaDBVectorStore $correctionStore Vector store for corrections
   * @param SecurityLogger $logger Security logger instance
   * @param string $userId User identifier
   * @param int $languageId Language ID
   * @param bool $debug Debug mode flag
   */
  public function __construct(
    MariaDBVectorStore $correctionStore,
    SecurityLogger $logger,
    string $userId,
    int $languageId,
    bool $debug
  ) {
    $this->correctionStore = $correctionStore;
    $this->logger = $logger;
    $this->userId = $userId;
    $this->languageId = $languageId;
    $this->debug = $debug;
    
    // Initialize CorrectionMemory for entity extraction
    $this->memory = new CorrectionMemory(
      $correctionStore,
      $logger,
      $userId,
      $languageId,
      $debug
    );
  }

  /**
   * Learn from user feedback to improve corrections
   * 
   * @param int $limit Maximum number of feedbacks to analyze
   * @return array Learning results
   */
  public function learnFromFeedback(int $limit = 100): array
  {
    try {
      $prefix = CLICSHOPPING::getConfig('db_table_prefix');
      
      $sql = "SELECT f.id,
                f.interaction_id,
                f.feedback_type,
                f.feedback_data,
                f.timestamp,
                f.user_id
              FROM {$prefix}rag_feedback f
              WHERE f.feedback_type IN ('negative', 'correction')
              ORDER BY f.timestamp DESC
              LIMIT :limit
            ";
      
      $feedbacks = DoctrineOrm::select($sql, ['limit' => $limit]);
      
      if (empty($feedbacks)) {
        return [
          'success' => true,
          'feedbacks_analyzed' => 0,
          'patterns_identified' => 0,
          'corrections_learned' => 0,
          'message' => 'No feedbacks to analyze'
        ];
      }
      
      $patternsIdentified = [];
      $correctionsLearned = 0;
      
      foreach ($feedbacks as $feedback) {
        $feedbackData = json_decode($feedback['feedback_data'], true);
        
        if ($feedback['feedback_type'] === 'negative') {
          $pattern = $this->identifyErrorPattern($feedback, $feedbackData);
          if ($pattern) {
            $patternsIdentified[] = $pattern;
          }
        } elseif ($feedback['feedback_type'] === 'correction') {
          $success = $this->storeCorrectionPattern($feedback, $feedbackData);
          if ($success) {
            $correctionsLearned++;
          }
        }
      }
      
      if (!empty($patternsIdentified)) {
        $this->updateCorrectionStrategies($patternsIdentified);
      }
      
      $improvementRate = $this->calculateImprovementRate($feedbacks);
      
      if ($this->debug) {
        $this->logger->logSecurityEvent(
          "FeedbackLearner: Learned from " . \count($feedbacks) . " feedbacks: " . 
          $correctionsLearned . " corrections, " . 
          \count($patternsIdentified) . " patterns",
          'info'
        );
      }
      
      return [
        'success' => true,
        'feedbacks_analyzed' => \count($feedbacks),
        'patterns_identified' => \count($patternsIdentified),
        'corrections_learned' => $correctionsLearned,
        'improvement_rate' => round($improvementRate, 2),
        'patterns' => \array_slice($patternsIdentified, 0, 10),
        'message' => "Successfully learned from {$correctionsLearned} corrections"
      ];
      
    } catch (\Exception $e) {
      $this->logger->logSecurityEvent(
        "FeedbackLearner: Error learning from feedback: " . $e->getMessage(),
        'error'
      );
      
      return [
        'success' => false,
        'error' => $e->getMessage(),
        'feedbacks_analyzed' => 0,
        'patterns_identified' => 0,
        'corrections_learned' => 0
      ];
    }
  }

  /**
   * Identify error pattern from negative feedback
   * 
   * @param array $feedback Feedback data
   * @param array $feedbackData Decoded JSON data
   * @return array|null Identified pattern or null
   */
  private function identifyErrorPattern(array $feedback, array $feedbackData): ?array
  {
    try {
      $reason = $feedbackData['reason'] ?? 'unknown';
      $comment = $feedbackData['comment'] ?? '';
      $rating = $feedbackData['rating'] ?? 0;
      
      $errorType = $this->classifyErrorFromFeedback($reason, $comment);
      
      if (!$errorType) {
        return null;
      }
      
      return [
        'error_type' => $errorType,
        'frequency' => 1,
        'severity' => $this->calculateSeverity($rating),
        'user_feedback' => $comment,
        'interaction_id' => $feedback['interaction_id'],
        'timestamp' => $feedback['timestamp']
      ];
      
    } catch (\Exception $e) {
      if ($this->debug) {
        $this->logger->logSecurityEvent(
          "FeedbackLearner: Error identifying pattern: " . $e->getMessage(),
          'error'
        );
      }
      return null;
    }
  }

  /**
   * Classify error type from user feedback
   * 
   * @param string $reason Feedback reason
   * @param string $comment User comment
   * @return string|null Error type or null
   */
  private function classifyErrorFromFeedback(string $reason, string $comment): ?string
  {
    $keywords = [
      'factual_error' => ['wrong', 'incorrect', 'false', 'error', 'mistake'],
      'incomplete' => ['incomplete', 'missing', 'partial', 'not enough'],
      'unclear' => ['unclear', 'confusing', 'ambiguous', 'vague'],
      'irrelevant' => ['irrelevant', 'off-topic', 'not related', 'wrong answer'],
      'formatting' => ['format', 'structure', 'layout', 'presentation']
    ];
    
    $text = strtolower("$reason $comment");
    
    foreach ($keywords as $type => $words) {
      foreach ($words as $word) {
        if (str_contains($text, $word)) {
          return $type;
        }
      }
    }
    
    return 'general_error';
  }

  /**
   * Calculates error severity based on rating
   * 
   * @param int $rating Rating value
   * @return string Severity level
   */
  private function calculateSeverity(int $rating): string
  {
    if ($rating <= 1) {
      return 'critical';
    } elseif ($rating <= 2) {
      return 'high';
    } elseif ($rating <= 3) {
      return 'medium';
    } else {
      return 'low';
    }
  }

  /**
   * Store correction pattern from correction feedback
   * 
   * @param array $feedback Feedback data
   * @param array $feedbackData Decoded JSON data
   * @return bool Storage success
   */
  private function storeCorrectionPattern(array $feedback, array $feedbackData): bool
  {
    try {
      $originalResponse = $feedbackData['original_response'] ?? '';
      $correctedResponse = $feedbackData['corrected_response'] ?? '';
      $correctionType = $feedbackData['correction_type'] ?? 'general';
      
      if (empty($originalResponse) || empty($correctedResponse)) {
        return false;
      }
      
      // Extract entity_id and entity_type from feedback data if available
      $entityId = $feedbackData['entity_id'] ?? null;
      $entityType = $feedbackData['entity_type'] ?? null;
      
      // If no entity_id in feedback, try to extract from interaction
      if ($entityId === null && isset($feedback['interaction_id'])) {
        $entityInfo = $this->extractEntityInfoFromInteraction($feedback['interaction_id']);
        $entityId = $entityInfo['entity_id'];
        $entityType = $entityInfo['entity_type'];
      }
      
      // Validate entity_id - use default if null
      if ($entityId === null) {
        $this->logger->logSecurityEvent(
          "FeedbackLearner: Cannot extract entity_id for feedback correction. Interaction: " . 
          ($feedback['interaction_id'] ?? 'N/A'),
          'warning',
          [
            'correction_type' => $correctionType,
            'user_id' => $feedback['user_id'] ?? 'N/A'
          ]
        );
        
        // Use default entity_id of 0 to indicate "no specific entity"
        $entityId = 0;
        $entityType = null;
      }
      
      $content = $this->createCorrectionContent($originalResponse, $correctedResponse, $correctionType);
      
      $document = new Document();
      $document->content = $content;
      $document->sourceType = 'user_correction';
      $document->sourceName = 'feedback_system';
      
      $document->metadata = [
        'type' => 'correction_pattern',
        'correction_type' => $correctionType,
        'original_response' => substr($originalResponse, 0, 500),
        'corrected_response' => substr($correctedResponse, 0, 500),
        'interaction_id' => $feedback['interaction_id'],
        'user_id' => $feedback['user_id'],
        'timestamp' => $feedback['timestamp'],
        'language_id' => $this->languageId,
        'correction_successful' => true,
        'success_rate' => 1.0,
        'entity_id' => $entityId,
        'entity_type' => $entityType,
      ];
      
      $this->correctionStore->addDocument($document);
      
      return true;
      
    } catch (\Exception $e) {
      $this->logger->logSecurityEvent(
        "FeedbackLearner: Error storing correction pattern: " . $e->getMessage(),
        'error',
        [
          'interaction_id' => $feedback['interaction_id'] ?? 'N/A',
          'correction_type' => $correctionType ?? 'N/A',
          'stack_trace' => $e->getTraceAsString()
        ]
      );
      
      if ($this->debug) {
        error_log("FeedbackLearner: Failed to store correction pattern - " . $e->getMessage() . "\n");
      }
      
      return false;
    }
  }

  /**
   * Extracts entity_id and entity_type from an interaction by querying the interactions table
   * 
   * @param string $interactionId Interaction ID to look up
   * @return array Array with 'entity_id' and 'entity_type' keys
   */
  private function extractEntityInfoFromInteraction(string $interactionId): array
  {
    return $this->memory->extractEntityInfoFromInteraction($interactionId);
  }

  /**
   * Create textual content of correction for vector store
   * 
   * @param string $original Original response
   * @param string $corrected Corrected response
   * @param string $type Correction type
   * @return string Correction content
   */
  private function createCorrectionContent(string $original, string $corrected, string $type): string
  {
    return $this->memory->createCorrectionContent($original, $corrected, $type);
  }

  /**
   * Updates correction strategies based on identified patterns
   * 
   * @param array $patterns Identified patterns
   * @return void
   */
  private function updateCorrectionStrategies(array $patterns): void
  {
    // Group patterns by type
    $groupedPatterns = [];
    foreach ($patterns as $pattern) {
      $type = $pattern['error_type'];
      if (!isset($groupedPatterns[$type])) {
        $groupedPatterns[$type] = [];
      }
      $groupedPatterns[$type][] = $pattern;
    }
    
    // Update confidence thresholds for each type
    foreach ($groupedPatterns as $type => $typePatterns) {
      $avgSeverity = $this->calculateAverageSeverity($typePatterns);
      
      // Adjust strategies based on severity
      if ($avgSeverity === 'critical' || $avgSeverity === 'high') {
        // Increase caution for this error type
        if ($this->debug) {
          $this->logger->logSecurityEvent(
            "FeedbackLearner: Increased caution for error type: $type (severity: $avgSeverity)",
            'info'
          );
        }
      }
    }
  }

  /**
   * Calculates average severity of a pattern group
   * 
   * @param array $patterns Pattern group
   * @return string Average severity
   */
  private function calculateAverageSeverity(array $patterns): string
  {
    $severityScores = [
      'critical' => 4,
      'high' => 3,
      'medium' => 2,
      'low' => 1
    ];
    
    $totalScore = 0;
    foreach ($patterns as $pattern) {
      $totalScore += $severityScores[$pattern['severity']] ?? 1;
    }
    
    $avgScore = $totalScore / \count($patterns);
    
    if ($avgScore >= 3.5) return 'critical';
    if ($avgScore >= 2.5) return 'high';
    if ($avgScore >= 1.5) return 'medium';
    return 'low';
  }

  /**
   * Calculates improvement rate based on feedbacks
   * 
   * @param array $feedbacks Feedback data
   * @return float Improvement rate
   */
  private function calculateImprovementRate(array $feedbacks): float
  {
    if (empty($feedbacks)) {
      return 0.0;
    }
    
    // Count positive vs negative feedbacks over time
    $positiveCount = 0;
    $negativeCount = 0;
    
    foreach ($feedbacks as $feedback) {
      if ($feedback['feedback_type'] === 'positive') {
        $positiveCount++;
      } elseif ($feedback['feedback_type'] === 'negative') {
        $negativeCount++;
      }
    }
    
    $total = $positiveCount + $negativeCount;
    if ($total === 0) {
      return 0.0;
    }
    
    return ($positiveCount / $total) * 100;
  }
}
