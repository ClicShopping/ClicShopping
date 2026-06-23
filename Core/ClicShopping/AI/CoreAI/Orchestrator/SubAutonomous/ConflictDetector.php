<?php
/**
 * ConflictDetector Class
 *
 * Identifies conflicts between agent objectives using similarity analysis,
 * resource conflict detection, and goal conflict detection. Provides
 * suggestions for merging complementary objectives.
 *
 * @package ClicShopping\AI\CoreAI\Orchestrator\SubAutonomous
 * @since 1.0.0
 */

namespace ClicShopping\AI\CoreAI\Orchestrator\SubAutonomous;

use ClicShopping\AI\DomainsAI\Shared\Embedding\NewVector;
use ClicShopping\AI\Rag\RagEmbeddingGenerator;
use ClicShopping\AI\Security\SecurityLogger;

class ConflictDetector
{
  private ObjectiveRegistry $registry;
  private SecurityLogger $logger;
  private bool $debug;
  private float $similarityThreshold = 0.75; // Threshold for considering objectives similar
  private float $mergeThreshold = 0.60; // Threshold for suggesting merge

  /**
   * Subject-overlap gate for goal-conflict detection.
   *
   * Two objectives only count as a goal conflict when they address the SAME subject.
   * Calibrated to the raw embedding-cosine distribution of {@see calculateTextSimilarity}:
   * unrelated pairs score ~0.17, same-subject pairs ~0.69. 0.50 sits between them with
   * margin. (The previous Levenshtein/word-overlap engine floored near 0, so this gate
   * used to be 0.30.)
   *
   * @var float
   */
  private float $goalConflictSubjectThreshold = 0.50;

  /**
   * In-memory embedding memo, keyed by md5(text). detectConflicts() compares the new
   * objective against every existing one and calls calculateSimilarity up to four times
   * per pair, so the same text would be embedded repeatedly without this cache.
   *
   * @var array<string, array>
   */
  private array $embeddingCache = [];

  /**
   * Constructor
   *
   * @param ObjectiveRegistry $registry The objective registry for querying existing objectives
   * @param bool $debug Enable debug logging when the embedding path falls back to lexical similarity
   */
  public function __construct(ObjectiveRegistry $registry, bool $debug = false)
  {
    $this->registry = $registry;
    $this->debug = $debug;
    $this->logger = new SecurityLogger();
  }

  /**
   * Detect conflicts for a new objective
   *
   * Analyzes a new objective against all active and pending objectives
   * to identify potential conflicts. Returns an array of conflicting
   * objectives with conflict details.
   *
   * @param LocalObjective $newObjective The objective to check for conflicts
   * @return array Array of conflicts, each containing:
   *               - objective: The conflicting LocalObjective
   *               - type: 'resource', 'goal', or 'duplicate'
   *               - similarity: Similarity score (0.0 - 1.0)
   *               - reason: Human-readable explanation
   */
  public function detectConflicts(LocalObjective $newObjective): array
  {
    $conflicts = [];

    // Get all active and pending objectives
    $activeObjectives = $this->registry->getObjectivesByStatus('active');
    $pendingObjectives = $this->registry->getObjectivesByStatus('pending');
    $approvedObjectives = $this->registry->getObjectivesByStatus('approved');

    $existingObjectives = array_merge($activeObjectives, $pendingObjectives, $approvedObjectives);

    foreach ($existingObjectives as $existing) {
      // Skip if same agent (agents can have multiple objectives)
      // But check for duplicates from same agent
      if ($existing->getAgentId() === $newObjective->getAgentId()) {
        $similarity = $this->calculateSimilarity($newObjective, $existing);
        if ($similarity >= $this->similarityThreshold) {
          $conflicts[] = [
            'objective' => $existing,
            'type' => 'duplicate',
            'similarity' => $similarity,
            'reason' => sprintf(
              'Highly similar objective already exists from same agent (%.1f%% similar)',
              $similarity * 100
            )
          ];
        }
        continue;
      }

      // Check for resource conflicts
      if ($this->checkResourceConflict($newObjective, $existing)) {
        $conflicts[] = [
          'objective' => $existing,
          'type' => 'resource',
          'similarity' => $this->calculateSimilarity($newObjective, $existing),
          'reason' => 'Objectives compete for the same resources or data'
        ];
        continue;
      }

      // Check for goal conflicts
      if ($this->checkGoalConflict($newObjective, $existing)) {
        $conflicts[] = [
          'objective' => $existing,
          'type' => 'goal',
          'similarity' => $this->calculateSimilarity($newObjective, $existing),
          'reason' => 'Objectives have conflicting or contradictory goals'
        ];
        continue;
      }

      // Check for high similarity (potential duplicate from different agent)
      $similarity = $this->calculateSimilarity($newObjective, $existing);
      if ($similarity >= $this->similarityThreshold) {
        $conflicts[] = [
          'objective' => $existing,
          'type' => 'duplicate',
          'similarity' => $similarity,
          'reason' => sprintf(
            'Highly similar objective already exists from different agent (%.1f%% similar)',
            $similarity * 100
          )
        ];
      }
    }

    return $conflicts;
  }

  /**
   * Check for resource conflicts between objectives
   *
   * Determines if two objectives compete for the same resources.
   * Resources can include database tables, API endpoints, cache keys,
   * or any system resources mentioned in success criteria.
   *
   * @param LocalObjective $obj1 First objective
   * @param LocalObjective $obj2 Second objective
   * @return bool True if there is a resource conflict
   */
  public function checkResourceConflict(LocalObjective $obj1, LocalObjective $obj2): bool
  {
    // Extract resource keywords from goal statements and success criteria
    $resources1 = $this->extractResources($obj1);
    $resources2 = $this->extractResources($obj2);

    // Check for overlapping resources
    $commonResources = array_intersect($resources1, $resources2);

    if (empty($commonResources)) {
      return false;
    }

    // Check if objectives have conflicting operations on common resources
    $operations1 = $this->extractOperations($obj1);
    $operations2 = $this->extractOperations($obj2);

    // Conflicting operations: write/write, delete/read, delete/write
    $conflictingOps = [
      ['write', 'write'],
      ['delete', 'read'],
      ['delete', 'write'],
      ['modify', 'modify'],
      ['update', 'update']
    ];

    foreach ($conflictingOps as $pair) {
      if ((in_array($pair[0], $operations1, true) && in_array($pair[1], $operations2, true)) ||
          (in_array($pair[1], $operations1, true) && in_array($pair[0], $operations2, true))) {
        return true;
      }
    }

    return false;
  }

  /**
   * Check for goal conflicts between objectives
   *
   * Determines if two objectives have contradictory or mutually exclusive goals.
   * For example, one objective optimizing for speed while another optimizes
   * for accuracy on the same component.
   *
   * @param LocalObjective $obj1 First objective
   * @param LocalObjective $obj2 Second objective
   * @return bool True if there is a goal conflict
   */
  public function checkGoalConflict(LocalObjective $obj1, LocalObjective $obj2): bool
  {
    $goal1 = strtolower($obj1->getGoalStatement());
    $goal2 = strtolower($obj2->getGoalStatement());

    // Define contradictory goal patterns
    $contradictions = [
      ['increase', 'decrease'],
      ['maximize', 'minimize'],
      ['optimize for speed', 'optimize for accuracy'],
      ['optimize for performance', 'optimize for memory'],
      ['enable', 'disable'],
      ['add', 'remove'],
      ['expand', 'reduce'],
      ['grow', 'shrink']
    ];

    // Check if goals contain contradictory terms
    foreach ($contradictions as $pair) {
      $has1in1 = str_contains($goal1, $pair[0]);
      $has2in1 = str_contains($goal1, $pair[1]);
      $has1in2 = str_contains($goal2, $pair[0]);
      $has2in2 = str_contains($goal2, $pair[1]);

      // If obj1 has first term and obj2 has second term (or vice versa)
      if (($has1in1 && $has2in2) || ($has2in1 && $has1in2)) {
        // Check if they're operating on similar subjects
        $similarity = $this->calculateSimilarity($obj1, $obj2);
        if ($similarity > $this->goalConflictSubjectThreshold) { // Same subject matter
          return true;
        }
      }
    }

    return false;
  }

  /**
   * Calculate similarity between two objectives
   *
   * Uses embedding-based semantic similarity to determine how similar two objectives
   * are. Combines similarity of goal statements and success criteria.
   *
   * @param LocalObjective $obj1 First objective
   * @param LocalObjective $obj2 Second objective
   * @return float Similarity score between 0.0 (completely different) and 1.0 (identical)
   */
  public function calculateSimilarity(LocalObjective $obj1, LocalObjective $obj2): float
  {
    // Calculate goal statement similarity
    $goalSimilarity = $this->calculateTextSimilarity(
      $obj1->getGoalStatement(),
      $obj2->getGoalStatement()
    );

    // Calculate success criteria similarity
    $criteria1 = implode(' ', $obj1->getSuccessCriteria());
    $criteria2 = implode(' ', $obj2->getSuccessCriteria());
    $criteriaSimilarity = $this->calculateTextSimilarity($criteria1, $criteria2);

    // Weighted average: goal statement is more important
    $similarity = ($goalSimilarity * 0.6) + ($criteriaSimilarity * 0.4);

    return $similarity;
  }

  /**
   * Suggest merge for complementary objectives
   *
   * Analyzes two objectives to determine if they are complementary and
   * could be merged into a single collaborative objective. Returns merge
   * suggestion with combined goal and criteria, or null if not suitable.
   *
   * @param LocalObjective $obj1 First objective
   * @param LocalObjective $obj2 Second objective
   * @return array|null Merge suggestion with:
   *                    - combined_goal: Suggested combined goal statement
   *                    - combined_criteria: Merged success criteria
   *                    - agents: Array of agent IDs
   *                    - priority: Suggested priority
   *                    - estimated_time: Suggested completion time
   *                    Or null if merge not recommended
   */
  public function suggestMerge(LocalObjective $obj1, LocalObjective $obj2): ?array
  {
    $similarity = $this->calculateSimilarity($obj1, $obj2);

    // Only suggest merge if objectives are related but not too similar
    if ($similarity < 0.3 || $similarity >= $this->similarityThreshold) {
      return null;
    }

    // Check if objectives are complementary (not conflicting)
    if ($this->checkGoalConflict($obj1, $obj2)) {
      return null;
    }

    // Check if they share resources (good for collaboration)
    $resources1 = $this->extractResources($obj1);
    $resources2 = $this->extractResources($obj2);
    $commonResources = array_intersect($resources1, $resources2);

    if (empty($commonResources) && $similarity < $this->mergeThreshold) {
      return null; // Not enough overlap to justify merge
    }

    // Build merge suggestion
    $combinedGoal = sprintf(
      "Collaborative objective: %s AND %s",
      $obj1->getGoalStatement(),
      $obj2->getGoalStatement()
    );

    $combinedCriteria = array_merge(
      array_map(fn($c) => "[{$obj1->getAgentId()}] {$c}", $obj1->getSuccessCriteria()),
      array_map(fn($c) => "[{$obj2->getAgentId()}] {$c}", $obj2->getSuccessCriteria())
    );

    // Use higher priority
    $priorities = ['low' => 1, 'medium' => 2, 'high' => 3, 'critical' => 4];
    $priority1 = $priorities[$obj1->getPriority()];
    $priority2 = $priorities[$obj2->getPriority()];
    $combinedPriority = array_search(max($priority1, $priority2), $priorities);

    // Estimate combined time (not just sum, as there may be synergies)
    $estimatedTime = (int)(($obj1->getEstimatedCompletionTime() + $obj2->getEstimatedCompletionTime()) * 0.8);

    return [
      'combined_goal' => $combinedGoal,
      'combined_criteria' => $combinedCriteria,
      'agents' => [$obj1->getAgentId(), $obj2->getAgentId()],
      'priority' => $combinedPriority,
      'estimated_time' => $estimatedTime,
      'similarity' => $similarity,
      'common_resources' => $commonResources,
      'reason' => sprintf(
        'Objectives are %.1f%% similar and share %d common resource(s)',
        $similarity * 100,
        count($commonResources)
      )
    ];
  }

  /**
   * Extract resources mentioned in an objective
   *
   * Identifies database tables, API endpoints, cache keys, and other
   * system resources mentioned in the objective.
   *
   * @param LocalObjective $objective The objective to analyze
   * @return array Array of resource identifiers
   */
  private function extractResources(LocalObjective $objective): array
  {
    $text = strtolower($objective->getGoalStatement() . ' ' . implode(' ', $objective->getSuccessCriteria()));
    $resources = [];

    // Database tables (rag_*, clicshopping_*)
    if (preg_match_all('/\b(rag_\w+|clicshopping_\w+)\b/', $text, $matches)) {
      $resources = array_merge($resources, $matches[1]);
    }

    // API endpoints
    if (preg_match_all('/\b(api\/\w+|endpoint\s+\w+)\b/', $text, $matches)) {
      $resources = array_merge($resources, $matches[0]);
    }

    // Cache keys
    if (preg_match_all('/\b(cache\s+\w+|cache_\w+)\b/', $text, $matches)) {
      $resources = array_merge($resources, $matches[0]);
    }

    // Generic resource patterns
    $resourceKeywords = [
      'database', 'table', 'index', 'query', 'cache', 'memory',
      'file', 'log', 'configuration', 'setting', 'registry'
    ];

    foreach ($resourceKeywords as $keyword) {
      if (str_contains($text, $keyword)) {
        $resources[] = $keyword;
      }
    }

    return array_unique($resources);
  }

  /**
   * Extract operations mentioned in an objective
   *
   * Identifies the types of operations (read, write, delete, modify, etc.)
   * that the objective intends to perform.
   *
   * @param LocalObjective $objective The objective to analyze
   * @return array Array of operation types
   */
  private function extractOperations(LocalObjective $objective): array
  {
    $text = strtolower($objective->getGoalStatement() . ' ' . implode(' ', $objective->getSuccessCriteria()));
    $operations = [];

    $operationPatterns = [
      'write' => ['write', 'insert', 'create', 'add', 'store', 'save'],
      'read' => ['read', 'query', 'fetch', 'retrieve', 'get', 'load'],
      'delete' => ['delete', 'remove', 'drop', 'clear', 'purge'],
      'modify' => ['modify', 'update', 'change', 'alter', 'edit'],
      'optimize' => ['optimize', 'improve', 'enhance', 'tune']
    ];

    foreach ($operationPatterns as $operation => $keywords) {
      foreach ($keywords as $keyword) {
        if (str_contains($text, $keyword)) {
          $operations[] = $operation;
          break;
        }
      }
    }

    return array_unique($operations);
  }

  /**
   * Calculate semantic text similarity using embeddings
   *
   * Embeds both strings and returns their cosine similarity, so paraphrases with
   * little literal overlap are recognised as similar (the whole point over the former
   * Levenshtein/word-overlap engine, which could not tell a paraphrase from an
   * unrelated sentence). Degrades gracefully to {@see fallbackTextSimilarity} when no
   * embedding model is configured or an embedding call fails.
   *
   * @param string $text1 First text
   * @param string $text2 Second text
   * @return float Similarity score between 0.0 and 1.0
   */
  private function calculateTextSimilarity(string $text1, string $text2): float
  {
    $text1 = strtolower(trim($text1));
    $text2 = strtolower(trim($text2));

    // Handle empty strings
    if (empty($text1) || empty($text2)) {
      return 0.0;
    }

    // Exact match — short-circuit before any embedding call
    if ($text1 === $text2) {
      return 1.0;
    }

    try {
      // RagEmbeddingGenerator is the project's embedding seam (adapter over NewVector);
      // it throws when no model is configured, which the catch routes to the fallback.
      $generator = new RagEmbeddingGenerator();

      $embedding1 = $this->embedText($generator, $text1);
      $embedding2 = $this->embedText($generator, $text2);

      // Raw cosine in [-1,1] from the canonical agnostic helper; for text embeddings
      // it lands in ~0.15 (unrelated) .. 1.0 (identical), which the class thresholds
      // (similarityThreshold/mergeThreshold/goalConflictSubjectThreshold) assume.
      return NewVector::cosineSimilarity($embedding1, $embedding2);
    } catch (\Throwable $e) {
      $this->logFallback('embedding similarity unavailable: ' . $e->getMessage());
      return $this->fallbackTextSimilarity($text1, $text2);
    }
  }

  /**
   * Embed a text string, memoised for the lifetime of this detector
   *
   * detectConflicts() compares the new objective against every existing one, so the
   * same text would otherwise be re-embedded many times over.
   *
   * @param RagEmbeddingGenerator $generator The project embedding generator
   * @param string $text The text to embed
   * @return array Embedding vector
   */
  private function embedText(RagEmbeddingGenerator $generator, string $text): array
  {
    $key = md5($text);

    if (!isset($this->embeddingCache[$key])) {
      $this->embeddingCache[$key] = $generator->embedText($text);
    }

    return $this->embeddingCache[$key];
  }

  /**
   * Log a fallback to lexical similarity (debug-gated)
   *
   * @param string $reason Why the embedding path was not used
   */
  private function logFallback(string $reason): void
  {
    if ($this->debug) {
      $this->logger->logSecurityEvent('ConflictDetector embedding fallback: ' . $reason, 'warning');
    }
  }

  /**
   * Lexical text similarity fallback (Levenshtein + word overlap)
   *
   * Used only when no embedding model is available or an embedding call fails. Kept as
   * a self-contained degraded path so conflict detection still produces a usable signal
   * offline; not the primary engine — see {@see calculateTextSimilarity}.
   *
   * @param string $text1 First text
   * @param string $text2 Second text
   * @return float Similarity score between 0.0 and 1.0
   */
  private function fallbackTextSimilarity(string $text1, string $text2): float
  {
    $text1 = strtolower(trim($text1));
    $text2 = strtolower(trim($text2));

    if (empty($text1) || empty($text2)) {
      return 0.0;
    }

    if ($text1 === $text2) {
      return 1.0;
    }

    // Calculate Levenshtein similarity
    $maxLen = max(strlen($text1), strlen($text2));
    $levenshtein = levenshtein($text1, $text2);
    $levenshteinSimilarity = 1.0 - ($levenshtein / $maxLen);

    // Calculate word overlap similarity
    $words1 = $this->extractSignificantWords($text1);
    $words2 = $this->extractSignificantWords($text2);

    if (empty($words1) || empty($words2)) {
      return $levenshteinSimilarity;
    }

    $commonWords = array_intersect($words1, $words2);
    $totalWords = array_unique(array_merge($words1, $words2));
    $wordOverlapSimilarity = count($commonWords) / count($totalWords);

    // Weighted combination: word overlap is more important for semantic similarity
    $similarity = ($levenshteinSimilarity * 0.3) + ($wordOverlapSimilarity * 0.7);

    return min(1.0, max(0.0, $similarity));
  }

  /**
   * Extract significant words from text
   *
   * Removes stop words and extracts meaningful words for comparison.
   *
   * @param string $text The text to process
   * @return array Array of significant words
   */
  private function extractSignificantWords(string $text): array
  {
    // Common stop words to ignore
    $stopWords = [
      'a', 'an', 'and', 'are', 'as', 'at', 'be', 'by', 'for', 'from',
      'has', 'he', 'in', 'is', 'it', 'its', 'of', 'on', 'that', 'the',
      'to', 'was', 'will', 'with', 'this', 'these', 'those', 'their'
    ];

    // Extract words
    $words = preg_split('/\s+/', strtolower($text));
    $words = array_filter($words, function($word) use ($stopWords) {
      return strlen($word) > 2 && !in_array($word, $stopWords, true);
    });

    return array_values($words);
  }

  /**
   * Set similarity threshold
   *
   * @param float $threshold Threshold value between 0.0 and 1.0
   */
  public function setSimilarityThreshold(float $threshold): void
  {
    $this->similarityThreshold = max(0.0, min(1.0, $threshold));
  }

  /**
   * Set merge threshold
   *
   * @param float $threshold Threshold value between 0.0 and 1.0
   */
  public function setMergeThreshold(float $threshold): void
  {
    $this->mergeThreshold = max(0.0, min(1.0, $threshold));
  }

  /**
   * Get similarity threshold
   *
   * @return float
   */
  public function getSimilarityThreshold(): float
  {
    return $this->similarityThreshold;
  }

  /**
   * Get merge threshold
   *
   * @return float
   */
  public function getMergeThreshold(): float
  {
    return $this->mergeThreshold;
  }
}
