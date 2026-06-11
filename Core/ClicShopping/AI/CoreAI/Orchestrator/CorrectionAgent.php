<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\CoreAI\Orchestrator;

use ClicShopping\OM\Registry;
use ClicShopping\AI\Security\SecurityLogger;
use ClicShopping\AI\Infrastructure\Storage\MariaDBVectorStore;
use ClicShopping\AI\DomainsAI\Shared\Embedding\NewVector;
use ClicShopping\AI\Infrastructure\Cache\Cache;
use ClicShopping\AI\CoreAI\Orchestrator\SubCorrectionAgent\LearningStatistics;
use ClicShopping\AI\CoreAI\Orchestrator\SubCorrectionAgent\CorrectionValidator;
use ClicShopping\AI\CoreAI\Orchestrator\SubCorrectionAgent\ErrorAnalyzer;
use ClicShopping\AI\CoreAI\Orchestrator\SubCorrectionAgent\PatternLearner;
use ClicShopping\AI\CoreAI\Orchestrator\SubCorrectionAgent\CorrectionStrategyManager;
use ClicShopping\AI\CoreAI\Orchestrator\SubCorrectionAgent\CorrectionMemory;
use ClicShopping\AI\CoreAI\Orchestrator\SubCorrectionAgent\FeedbackLearner;
use LLPhant\Embeddings\Document;
use LLPhant\Embeddings\EmbeddingGenerator\EmbeddingGeneratorInterface;

/**
 * CorrectionAgent Class
 * 
 * Orchestrator for SQL error correction using specialized components.
 * Coordinates error analysis, pattern learning, strategy selection, and correction validation.
 * Maintains backward compatibility with existing API while delegating to focused components.
 */
class CorrectionAgent
{
  private SecurityLogger $securityLogger;
  private LearningStatistics $statistics;
  private CorrectionValidator $validator;
  private ErrorAnalyzer $errorAnalyzer;
  private PatternLearner $patternLearner;
  private CorrectionStrategyManager $strategyManager;
  private CorrectionMemory $memory;
  private FeedbackLearner $feedbackLearner;
  private bool $debug;
  private string $userId;
  private float $confidenceThreshold = 0.7;

  /**
   * Constructor
   *
   * Initializes all sub-components with dependency injection.
   * Maintains backward compatibility with existing constructor signature.
   *
   * @param string $userId User identifier
   * @param int|null $languageId Language ID (defaults to current language)
   * @param string $tableName Table name for storing corrections
   */
  public function __construct(string $userId = 'system', ?int $languageId = null, string $tableName = 'rag_correction_patterns_embedding')
  {
    $this->userId = $userId;
    $this->securityLogger = new SecurityLogger();
    $this->debug = \defined('CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER') && CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER === 'True';
    
    // Get language ID
    $language = Registry::get('Language');
    $languageId ??= $language->getId();

    // Initialize embedding generator and vector store
    $embeddingGenerator = $this->createEmbeddingGenerator();
    $correctionStore = new MariaDBVectorStore($embeddingGenerator, $tableName);
    $cache = new Cache(true);

    // Initialize all sub-components with dependency injection
    $this->statistics = new LearningStatistics($cache, $this->securityLogger, $this->userId, $this->debug);
    $this->validator = new CorrectionValidator($this->confidenceThreshold, $this->securityLogger, $this->debug);
    $this->errorAnalyzer = new ErrorAnalyzer($this->securityLogger, $this->debug);
    $this->patternLearner = new PatternLearner($correctionStore, $this->securityLogger, $this->debug, 5);
    $this->strategyManager = new CorrectionStrategyManager($this->securityLogger, $this->debug);
    $this->memory = new CorrectionMemory($correctionStore, $this->securityLogger, $this->userId, $languageId, $this->debug);
    $this->feedbackLearner = new FeedbackLearner($correctionStore, $this->securityLogger, $this->userId, $languageId, $this->debug);

    if ($this->debug) {
      $this->securityLogger->logSecurityEvent(
        "CorrectionAgent initialized for user: {$this->userId}",
        'info'
      );
    }
  }

  /**
   * Attempt to correct an error
   *
   * Orchestrates the complete correction workflow by delegating to specialized components:
   * 1. ErrorAnalyzer - Analyzes and classifies the error
   * 2. PatternLearner - Finds similar historical cases
   * 3. CorrectionStrategyManager - Selects and applies correction strategy
   * 4. CorrectionValidator - Validates the proposed correction
   * 5. CorrectionMemory - Stores successful corrections for future learning
   * 6. LearningStatistics - Tracks correction metrics
   *
   * @param array $errorContext Error context containing error_message, failed_query, etc.
   * @return array Correction result with success status, corrected query, and metadata
   */
  public function attemptCorrection(array $errorContext): array
  {
    $startTime = microtime(true);
    $this->statistics->incrementTotalErrors();

    try {
      // Step 1: Analyze error using ErrorAnalyzer component
      $errorAnalysis = $this->errorAnalyzer->analyzeError($errorContext);

      if ($this->debug) {
        $this->securityLogger->logSecurityEvent(
          "CorrectionAgent: Error analyzed - Type={$errorAnalysis['type']}, Confidence={$errorAnalysis['confidence']}",
          'info'
        );
      }

      // Step 2: Find similar cases using PatternLearner component
      $similarCases = $this->patternLearner->findSimilarCases($errorContext, $errorAnalysis);

      // Step 3: Apply correction strategy
      $correction = $this->applyCorrectionStrategy(
        $errorContext,
        $errorAnalysis,
        $similarCases
      );

      // Step 4: Validate correction using CorrectionValidator component
      $validation = $this->validator->validateCorrection($correction);

      if ($validation['is_valid']) {
        // Step 5: Store successful correction using CorrectionMemory component
        $this->memory->memorizeSuccessfulCorrection(
          $errorContext,
          $correction,
          $errorAnalysis
        );

        // Step 6: Update statistics
        $this->statistics->incrementSuccessfulCorrections();
        $this->statistics->incrementLearnedPatterns();

        $result = [
          'success' => true,
          'corrected_query' => $correction['query'],
          'correction_method' => $correction['method'],
          'confidence' => $correction['confidence'],
          'learned_from_history' => !empty($similarCases),
          'similar_cases_found' => \count($similarCases),
          'execution_time' => microtime(true) - $startTime,
          'suggestions' => $correction['suggestions'] ?? [],
        ];
      } else {
        $this->statistics->incrementFailedCorrections();

        $result = [
          'success' => false,
          'error' => 'Correction validation failed',
          'validation_issues' => $validation['issues'],
          'attempted_correction' => $correction['query'] ?? null,
          'suggestions' => $this->generateFallbackSuggestions($errorContext),
        ];
      }

      $this->statistics->updateAccuracy();

      return $result;

    } catch (\Exception $e) {
      $this->securityLogger->logSecurityEvent(
        "CorrectionAgent: Correction attempt failed - " . $e->getMessage(),
        'error'
      );

      return [
        'success' => false,
        'error' => $e->getMessage(),
        'suggestions' => $this->generateFallbackSuggestions($errorContext),
      ];
    }
  }

  /**
   * Apply appropriate correction strategy
   *
   * Delegates to PatternLearner for high-confidence similar cases,
   * otherwise delegates to CorrectionStrategyManager for strategy selection.
   *
   * @param array $errorContext Error context
   * @param array $errorAnalysis Error analysis
   * @param array $similarCases Similar historical cases
   * @return array Proposed correction
   */
  private function applyCorrectionStrategy(
    array $errorContext,
    array $errorAnalysis,
    array $similarCases
  ): array {
    // Delegate to PatternLearner if we have a high-confidence similar case
    if (!empty($similarCases) && $similarCases[0]['similarity_score'] > 0.85) {
      return $this->patternLearner->applyLearnedCorrection($errorContext, $similarCases[0]);
    }

    // Delegate to CorrectionStrategyManager for strategy selection and execution
    return $this->strategyManager->applyStrategy($errorContext, $errorAnalysis, $similarCases);
  }

  /**
   * Generate fallback suggestions if correction fails
   * 
   * Provides generic suggestions based on error message keywords.
   * 
   * @param array $errorContext Error context
   * @return array Suggestions
   */
  private function generateFallbackSuggestions(array $errorContext): array
  {
    $suggestions = [];
    $errorMessage = $errorContext['error_message'] ?? '';

    if (stripos($errorMessage, 'column') !== false) {
      $suggestions[] = "Check column names for typos";
      $suggestions[] = "Verify table aliases are correct";
      $suggestions[] = "Ensure all columns are in the SELECT or GROUP BY clause";
    }

    if (stripos($errorMessage, 'table') !== false) {
      $suggestions[] = "Verify table name spelling";
      $suggestions[] = "Check if table exists in the database";
      $suggestions[] = "Ensure proper table prefix if applicable";
    }

    if (stripos($errorMessage, 'syntax') !== false) {
      $suggestions[] = "Check for missing or extra commas";
      $suggestions[] = "Verify parentheses are balanced";
      $suggestions[] = "Review SQL clause order (SELECT, FROM, WHERE, GROUP BY, ORDER BY)";
    }

    $suggestions[] = "Try simplifying the query to identify the issue";
    $suggestions[] = "Review the original question to ensure SQL aligns with intent";

    return $suggestions;
  }

  /**
   * Get learning statistics
   *
   * Delegates to LearningStatistics component.
   *
   * @return array Statistics
   */
  public function getLearningStats(): array
  {
    return $this->statistics->getLearningStats();
  }

  /**
   * Reset learning statistics
   *
   * Delegates to LearningStatistics component.
   */
  public function resetLearningStats(): void
  {
    $this->statistics->resetLearningStats();
  }

  /**
   * Set confidence threshold
   *
   * Updates threshold for both orchestrator and CorrectionValidator component.
   *
   * @param float $threshold Threshold (0-1)
   */
  public function setConfidenceThreshold(float $threshold): void
  {
    $this->confidenceThreshold = max(0.0, min(1.0, $threshold));
    $this->validator->setConfidenceThreshold($this->confidenceThreshold);
  }

  /**
   * Get detailed learning report
   *
   * Delegates to LearningStatistics component.
   *
   * @return array Report
   */
  public function getLearningReport(): array
  {
    $report = $this->statistics->getLearningReport();
    $stats = $this->statistics->getLearningStats();
    
    // Provide flat structure for backward compatibility
    return [
      'total_errors' => $stats['total_errors'],
      'successful_corrections' => $stats['successful_corrections'],
      'failed_corrections' => $stats['failed_corrections'],
      'learned_patterns' => $stats['learned_patterns'],
      'correction_accuracy' => round($stats['correction_accuracy'] * 100, 2),
      'recommendations' => $report['recommendations'] ?? [],
      'overview' => $report['overview'] ?? [],
      'performance' => $report['performance'] ?? [],
    ];
  }

  /**
   * Learn from user feedback to improve corrections
   * 
   * Delegates to FeedbackLearner component and updates statistics.
   * 
   * @param int $limit Maximum number of feedbacks to analyze
   * @return array Learning results
   */
  public function learnFromFeedback(int $limit = 100): array
  {
    $result = $this->feedbackLearner->learnFromFeedback($limit);
    
    // Update statistics with learned patterns count
    if ($result['success'] && isset($result['corrections_learned'])) {
      for ($i = 0; $i < $result['corrections_learned']; $i++) {
        $this->statistics->incrementLearnedPatterns();
      }
    }
    
    return $result;
  }

  /**
   * Create embedding generator
   * 
   * Creates an anonymous class implementing EmbeddingGeneratorInterface.
   * Uses NewVector for actual embedding generation.
   * 
   * @return EmbeddingGeneratorInterface Embedding generator instance
   */
  private function createEmbeddingGenerator(): EmbeddingGeneratorInterface
  {
    return new class implements EmbeddingGeneratorInterface {
      public function embedText(string $text): array
      {
        $generator = NewVector::gptEmbeddingsModel();
        if (!$generator) {
          throw new \RuntimeException('Embedding generator not initialized');
        }
        return $generator->embedText($text);
      }

      public function embedDocument(Document $document): Document
      {
        $document->embedding = $this->embedText($document->content);
        return $document;
      }

      public function embedDocuments(array $documents): array
      {
        $results = [];
        foreach ($documents as $document) {
          $results[] = $this->embedDocument($document);
        }
        return $results;
      }

      public function getEmbeddingLength(): int
      {
        return NewVector::getEmbeddingLength();
      }
    };
  }
}