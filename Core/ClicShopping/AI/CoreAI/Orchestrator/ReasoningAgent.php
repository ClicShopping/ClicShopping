<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\CoreAI\Orchestrator;

use ClicShopping\AI\Config\AutonomousConfig;
use ClicShopping\AI\Config\DomainConfig;
use ClicShopping\AI\CoreAI\Orchestrator\SubAbstention\AgentAbstentionManager;
use ClicShopping\AI\Infrastructure\Metrics\ReasoningAgentStats;
use ClicShopping\AI\Security\SecurityLogger;
use ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\Gpt;
use ClicShopping\OM\Registry;

/**
 * ReasoningAgent Class
 * Specialized agent for multi-step reasoning with Chain-of-Thought, Tree-of-Thought, and Self-consistency methods
 */

class ReasoningAgent
{
  private SecurityLogger $securityLogger;
  private mixed $chat;
  private bool $debug;
  private ?ReasoningAgentStats $persistentStats = null;
  private ?AutonomousConfig $autonomousConfig = null;
  private ?AgentAbstentionManager $abstentionManager = null;
  private ReasoningStrategies $strategies;

  // Configuration
  private string $reasoningMode = 'chain_of_thought'; // chain_of_thought, tree_of_thought, self_consistency
  private int $maxReasoningSteps = 10;
  private int $selfConsistencyPaths = 3;
  private int $treeOfThoughtPaths = 3;

  // Statistiques (session locale)
  private array $stats = [
    'total_reasonings' => 0,
    'avg_steps' => 0,
    'successful_reasonings' => 0,
    'failed_reasonings' => 0,
    // Statistics by mode
    'by_mode' => [
      'chain_of_thought' => [
        'count' => 0,
        'successful' => 0,
        'failed' => 0,
        'avg_steps' => 0,
        'total_steps' => 0,
        'avg_confidence' => 0,
        'total_confidence' => 0,
      ],
      'tree_of_thought' => [
        'count' => 0,
        'successful' => 0,
        'failed' => 0,
        'avg_paths' => 0,
        'total_paths' => 0,
        'avg_confidence' => 0,
        'total_confidence' => 0,
      ],
      'self_consistency' => [
        'count' => 0,
        'successful' => 0,
        'failed' => 0,
        'avg_attempts' => 0,
        'total_attempts' => 0,
        'avg_confidence' => 0,
        'total_confidence' => 0,
        'avg_agreement' => 0,
        'total_agreement' => 0,
      ],
    ],
  ];

  /**
   * Constructor
   * 
   * @param array|null $config Optional configuration array to override defaults
   */
  public function __construct(?array $config = null)
  {
    $this->securityLogger = new SecurityLogger();
    $this->debug = defined('CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER') && CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER === 'True';
    $this->autonomousConfig = new AutonomousConfig($this->debug);
    $this->abstentionManager = new AgentAbstentionManager();

    // Initialize persistent stats
    try {
      $this->persistentStats = new ReasoningAgentStats();
    } catch (\Exception $e) {
      // Silently fail if database is not available
      error_log('ReasoningAgent: Failed to initialize persistent stats - ' . $e->getMessage());
    }

    // Load configuration from various sources (priority order):
    // 1. Constructor parameter (highest priority)
    // 2. Admin-configured constants (CLICSHOPPING_APP_CHATGPT_RA_*)
    // 3. Default values (already set in property declarations)

    // 2. Apply admin constants. Required because OrchestratorAgent builds
    //    `new ReasoningAgent()` WITHOUT a $config array — defining the constant
    //    is not enough, the value must be applied to the property here.
    if (defined('CLICSHOPPING_APP_CHATGPT_RA_REASONING_MODE')) {
      try {
        $this->setReasoningMode((string) CLICSHOPPING_APP_CHATGPT_RA_REASONING_MODE);
      } catch (\InvalidArgumentException $e) {
        // Empty or unknown mode → keep the default 'chain_of_thought'.
        error_log('ReasoningAgent: invalid CLICSHOPPING_APP_CHATGPT_RA_REASONING_MODE - ' . $e->getMessage());
      }
    }
    if (defined('CLICSHOPPING_APP_CHATGPT_RA_MAX_REASONING_STEPS')) {
      $this->setMaxReasoningSteps((int) CLICSHOPPING_APP_CHATGPT_RA_MAX_REASONING_STEPS);
    }
    if (defined('CLICSHOPPING_APP_CHATGPT_RA_CONSISTENCY_PATHS')) {
      $this->setSelfConsistencyPaths((int) CLICSHOPPING_APP_CHATGPT_RA_CONSISTENCY_PATHS);
    }
    if (defined('CLICSHOPPING_APP_CHATGPT_RA_TREE_PATHS')) {
      $this->setTreeOfThoughtPaths((int) CLICSHOPPING_APP_CHATGPT_RA_TREE_PATHS);
    }

    // 1. Constructor parameter overrides the constants when provided.
    if ($config !== null) {
      $this->loadConfigurationFromArray($config);
    }

    // Reasoning strategies collaborator — built after config is finalized
    // (setters have no post-construction callers, so a snapshot is safe).
    $this->strategies = new ReasoningStrategies(
      $this->securityLogger,
      $this->debug,
      $this->maxReasoningSteps,
      $this->selfConsistencyPaths,
      $this->treeOfThoughtPaths
    );

    if ($this->debug) {
      $this->logConfiguration();
    }
  }

  /**
   * Load configuration from an array
   * This method allows loading from JSON, database, or any array source
   * 
   * @param array $config Configuration array with keys: reasoning_mode, max_reasoning_steps, self_consistency_paths, tree_of_thought_paths
   */
  public function loadConfigurationFromArray(array $config): void
  {
    if (isset($config['reasoning_mode'])) {
      try {
        $this->setReasoningMode($config['reasoning_mode']);
      } catch (\InvalidArgumentException $e) {
        $this->securityLogger->logSecurityEvent(
          "Invalid reasoning_mode in config: " . $e->getMessage(),
          'warning'
        );
      }
    }

    if (isset($config['max_reasoning_steps'])) {
      try {
        $this->setMaxReasoningSteps((int)$config['max_reasoning_steps']);
      } catch (\InvalidArgumentException $e) {
        $this->securityLogger->logSecurityEvent(
          "Invalid max_reasoning_steps in config: " . $e->getMessage(),
          'warning'
        );
      }
    }

    if (isset($config['self_consistency_paths'])) {
      try {
        $this->setSelfConsistencyPaths((int)$config['self_consistency_paths']);
      } catch (\InvalidArgumentException $e) {
        $this->securityLogger->logSecurityEvent(
          "Invalid self_consistency_paths in config: " . $e->getMessage(),
          'warning'
        );
      }
    }

    if (isset($config['tree_of_thought_paths'])) {
      try {
        $this->setTreeOfThoughtPaths((int)$config['tree_of_thought_paths']);
      } catch (\InvalidArgumentException $e) {
        $this->securityLogger->logSecurityEvent(
          "Invalid tree_of_thought_paths in config: " . $e->getMessage(),
          'warning'
        );
      }
    }
  }

  /**
   * Set reasoning mode
   *
   * @param string $mode Reasoning mode (default: 'chain_of_thought')
   * @throws \InvalidArgumentException if mode is invalid
   */
  public function setReasoningMode(string $mode = 'chain_of_thought'): void
  {
    $validModes = ['chain_of_thought', 'tree_of_thought', 'self_consistency'];

    if (!in_array($mode, $validModes, true)) {
      throw new \InvalidArgumentException(
        "Invalid reasoning mode '{$mode}'. Valid modes: " . implode(', ', $validModes)
      );
    }

    $this->reasoningMode = $mode;

    if ($this->debug) {
      $this->securityLogger->logSecurityEvent(
        "Reasoning mode changed to: {$mode}",
        'info'
      );
    }
  }

  /**
   * Set maximum reasoning steps
   *
   * @param int $max Maximum steps (default: 10, range: 1-50)
   * @throws \InvalidArgumentException if value is out of range
   */
  public function setMaxReasoningSteps(int $max = 10): void
  {
    if ($max < 1 || $max > 50) {
      throw new \InvalidArgumentException(
        "Max reasoning steps must be between 1 and 50, got: {$max}"
      );
    }

    $this->maxReasoningSteps = $max;

    if ($this->debug) {
      $this->securityLogger->logSecurityEvent(
        "Max reasoning steps set to: {$max}",
        'info'
      );
    }
  }

  /**
   * Set self-consistency paths
   *
   * @param int $paths Number of paths (default: 3, range: 2-10)
   * @throws \InvalidArgumentException if value is out of range
   */
  public function setSelfConsistencyPaths(int $paths = 3): void
  {
    if ($paths < 2 || $paths > 10) {
      throw new \InvalidArgumentException(
        "Self-consistency paths must be between 2 and 10, got: {$paths}"
      );
    }

    $this->selfConsistencyPaths = $paths;

    if ($this->debug) {
      $this->securityLogger->logSecurityEvent(
        "Self-consistency paths set to: {$paths}",
        'info'
      );
    }
  }

  /**
   * Set tree-of-thought paths
   *
   * @param int $paths Number of paths (default: 3, range: 2-10)
   * @throws \InvalidArgumentException if value is out of range
   */
  public function setTreeOfThoughtPaths(int $paths = 3): void
  {
    if ($paths < 2 || $paths > 10) {
      throw new \InvalidArgumentException(
        "Tree-of-thought paths must be between 2 and 10, got: {$paths}"
      );
    }

    $this->treeOfThoughtPaths = $paths;

    if ($this->debug) {
      $this->securityLogger->logSecurityEvent(
        "Tree-of-thought paths set to: {$paths}",
        'info'
      );
    }
  }

  /**
   * Log current configuration
   */
  private function logConfiguration(): void
  {
    $config = $this->getConfiguration();
    $this->securityLogger->logSecurityEvent(
      "ReasoningAgent configuration: " . json_encode($config),
      'info'
    );
  }

  /**
   * Get current configuration
   *
   * @return array Configuration settings
   */
  public function getConfiguration(): array
  {
    return [
      'reasoning_mode' => $this->reasoningMode,
      'max_reasoning_steps' => $this->maxReasoningSteps,
      'self_consistency_paths' => $this->selfConsistencyPaths,
      'tree_of_thought_paths' => $this->treeOfThoughtPaths,
    ];
  }

  /**
   * Reason about a problem using configured reasoning mode
   *
   * @param string $problem Problem to solve
   * @param array $context Additional context
   * @return array Reasoning result
   */
  public function reason(string $problem, array $context = []): array
  {
    $this->stats['total_reasonings']++;
    $startTime = microtime(true);

    try {

      $complexity = $this->estimateReasoningComplexity($problem);

      $confidence = $this->abstentionManager->evaluateConfidence(
        'ReasoningAgent',
        $problem,
        array_merge($context, [
          'task_type' => 'reasoning_' . $this->reasoningMode,
          'description' => $problem,
          'complexity' => $complexity
        ])
      );

      $decision = $this->abstentionManager->getAbstentionDecision(
        'ReasoningAgent',
        $confidence,
        'reasoning_' . $this->reasoningMode
      );

      if ($this->debug) {
        $this->securityLogger->logSecurityEvent(
          "ReasoningAgent confidence: {$confidence}, decision: {$decision['action']}",
          'info'
        );
      }

      if ($decision['action'] === 'abstain') {
        // Log abstention to database
        $this->abstentionManager->logAbstention(
          'ReasoningAgent',
          md5($problem),
          'reasoning_' . $this->reasoningMode,
          $confidence,
          $decision['reason'],
          'escalate_human'
        );

        $this->stats['failed_reasonings']++;

        return [
          'success' => false,
          'error' => 'Confidence too low for autonomous reasoning. Human review required.',
          'reason' => $decision['reason'],
          'confidence' => $confidence,
          'requires_human' => true,
          'problem' => $problem
        ];
      }

      if ($decision['action'] === 'delegate') {
        // Log delegation intent
        $this->abstentionManager->logAbstention(
          'ReasoningAgent',
          md5($problem),
          'reasoning_' . $this->reasoningMode,
          $confidence,
          $decision['reason'],
          'delegate_peer',
          $decision['suggested_delegate']
        );

        // Proceed with execution but note delegation was considered
        if ($this->debug) {
          $this->securityLogger->logSecurityEvent(
            "ReasoningAgent delegating to: " . ($decision['suggested_delegate'] ?? 'none'),
            'info'
          );
        }
      }

      $result = match($this->reasoningMode) {
        'chain_of_thought' => $this->strategies->chainOfThought($problem, $context),
        'tree_of_thought' => $this->strategies->treeOfThought($problem, $context),
        'self_consistency' => $this->strategies->selfConsistency($problem, $context),
        default => $this->strategies->chainOfThought($problem, $context),
      };

      $this->stats['successful_reasonings']++;
      $this->updateAverageSteps($result['steps_count'] ?? 0);

      // Calculate response time
      $responseTime = (int)round((microtime(true) - $startTime) * 1000);

      // Update mode-specific statistics
      $this->updateModeStats($this->reasoningMode, $result, true);

      // Save statistics to database via ReasoningAgentStats
      if ($this->persistentStats !== null) {
        $this->persistentStats->saveStatistics($this->reasoningMode, $result, $responseTime, true);
      }

      return $result;

    } catch (\Exception $e) {
      $this->stats['failed_reasonings']++;

      // Calculate response time
      $responseTime = (int)round((microtime(true) - $startTime) * 1000);

      // Update mode-specific statistics for failure
      $this->updateModeStats($this->reasoningMode, [], false);

      // Save failure statistics to database via ReasoningAgentStats
      if ($this->persistentStats !== null) {
        $this->persistentStats->saveStatistics($this->reasoningMode, ['error' => $e->getMessage()], $responseTime, false);
      }

      $this->securityLogger->logSecurityEvent(
        "Reasoning failed: " . $e->getMessage(),
        'error'
      );

      return [
        'success' => false,
        'error' => $e->getMessage(),
        'problem' => $problem,
      ];
    }
  }

  /**
   * Estimate reasoning complexity for confidence evaluation
   *

   *
   * @param string $problem The reasoning problem
   * @return float Complexity score (0.0-1.0)
   */
  private function estimateReasoningComplexity(string $problem): float
  {
    $complexity = 0.3; // Base complexity

    // Increase for word count (longer problems are more complex)
    $wordCount = str_word_count($problem);
    if ($wordCount > 50) {
      $complexity += 0.15;
    }
    if ($wordCount > 100) {
      $complexity += 0.1;
    }

    // Increase for multi-step indicators
    if (preg_match('/\b(first|then|next|finally|step|stage|phase)\b/i', $problem)) {
      $complexity += 0.15;
    }

    // Increase for conditional logic
    if (preg_match('/\b(if|when|unless|provided|assuming|given)\b/i', $problem)) {
      $complexity += 0.1;
    }

    // Increase for comparison/analysis requirements
    if (preg_match('/\b(compare|analyze|evaluate|assess|determine|calculate)\b/i', $problem)) {
      $complexity += 0.1;
    }

    // Increase for multiple questions
    $questionCount = preg_match_all('/\?/', $problem);
    if ($questionCount > 1) {
      $complexity += 0.1;
    }

    return min(1.0, $complexity);
  }

  /**
   * Update average steps statistic
   * 
   * @param int $steps Number of steps in current reasoning
   */
  private function updateAverageSteps(int $steps): void
  {
    $total = $this->stats['total_reasonings'];
    $current = $this->stats['avg_steps'];

    $this->stats['avg_steps'] = (($current * ($total - 1)) + $steps) / $total;
  }

  /**
   * Update mode-specific statistics
   *
   * @param string $mode The reasoning mode used
   * @param array $result The result from reasoning
   * @param bool $success Whether the reasoning was successful
   */
  private function updateModeStats(string $mode, array $result, bool $success): void
  {
    if (!isset($this->stats['by_mode'][$mode])) {
      return;
    }

    $this->stats['by_mode'][$mode]['count']++;

    if ($success) {
      $this->stats['by_mode'][$mode]['successful']++;

      // Update mode-specific metrics
      switch ($mode) {
        case 'chain_of_thought':
          if (isset($result['steps_count'])) {
            $this->stats['by_mode'][$mode]['total_steps'] += $result['steps_count'];
            $this->stats['by_mode'][$mode]['avg_steps'] =
              $this->stats['by_mode'][$mode]['total_steps'] / $this->stats['by_mode'][$mode]['successful'];
          }
          if (isset($result['confidence'])) {
            $this->stats['by_mode'][$mode]['total_confidence'] += $result['confidence'];
            $this->stats['by_mode'][$mode]['avg_confidence'] =
              $this->stats['by_mode'][$mode]['total_confidence'] / $this->stats['by_mode'][$mode]['successful'];
          }
          break;

        case 'tree_of_thought':
          if (isset($result['explored_paths'])) {
            $this->stats['by_mode'][$mode]['total_paths'] += $result['explored_paths'];
            $this->stats['by_mode'][$mode]['avg_paths'] =
              $this->stats['by_mode'][$mode]['total_paths'] / $this->stats['by_mode'][$mode]['successful'];
          }
          if (isset($result['confidence'])) {
            $this->stats['by_mode'][$mode]['total_confidence'] += $result['confidence'];
            $this->stats['by_mode'][$mode]['avg_confidence'] =
              $this->stats['by_mode'][$mode]['total_confidence'] / $this->stats['by_mode'][$mode]['successful'];
          }
          break;

        case 'self_consistency':
          if (isset($result['attempts'])) {
            $this->stats['by_mode'][$mode]['total_attempts'] += $result['attempts'];
            $this->stats['by_mode'][$mode]['avg_attempts'] =
              $this->stats['by_mode'][$mode]['total_attempts'] / $this->stats['by_mode'][$mode]['successful'];
          }
          if (isset($result['confidence'])) {
            $this->stats['by_mode'][$mode]['total_confidence'] += $result['confidence'];
            $this->stats['by_mode'][$mode]['avg_confidence'] =
              $this->stats['by_mode'][$mode]['total_confidence'] / $this->stats['by_mode'][$mode]['successful'];
          }
          if (isset($result['agreement_rate'])) {
            $this->stats['by_mode'][$mode]['total_agreement'] += $result['agreement_rate'];
            $this->stats['by_mode'][$mode]['avg_agreement'] =
              $this->stats['by_mode'][$mode]['total_agreement'] / $this->stats['by_mode'][$mode]['successful'];
          }
          break;
      }
    } else {
      $this->stats['by_mode'][$mode]['failed']++;
    }
  }

  /**
   * Decompose complex problem recursively
   *
   * @param string $problem Complex problem
   * @param int $depth Current depth
   * @return array Hierarchical decomposition
   */
  public function decompose(string $problem, int $depth = 0): array
  {
    if ($depth >= 3) {
      // Depth limit reached
      return [
        'problem' => $problem,
        'is_atomic' => true,
        'subproblems' => [],
      ];
    }

    $prompt = $this->buildDecomposePrompt($problem);
    $response = Gpt::getGptResponse($prompt, 500);

    $parsed = $this->parseDecomposition($response);

    // If problem is atomic, stop
    if ($parsed['is_atomic']) {
      return [
        'problem' => $problem,
        'is_atomic' => true,
        'subproblems' => [],
        'reasoning' => $parsed['reasoning'] ?? '',
      ];
    }

    // Otherwise, recursively decompose subproblems
    $subproblems = [];
    foreach ($parsed['subproblems'] as $subproblem) {
      $subproblems[] = $this->decompose($subproblem, $depth + 1);
    }

    return [
      'problem' => $problem,
      'is_atomic' => false,
      'subproblems' => $subproblems,
      'reasoning' => $parsed['reasoning'] ?? '',
      'depth' => $depth,
    ];
  }

  /**
   * Build decomposition prompt
   *
   * @param string $problem Problem to decompose
   * @return string Formatted prompt
   */
  private function buildDecomposePrompt(string $problem): string
  {
    $CLICSHOPPING_Language = Registry::get('Language');

    // Load language file in English for internal processing
    DomainConfig::loadAgnosticLanguageFile('rag_reasoning_agent');

    // Get the prompt template
    $prompt = $CLICSHOPPING_Language->getDef('text_reasoning_decompose_prompt');

    // Replace variables
    $prompt = str_replace('{{problem}}', $problem, $prompt);

    return $prompt;
  }

  /**
   * Parse decomposition response
   *
   * @param string $response LLM response
   * @return array Parsed decomposition with atomic flag and subproblems
   */
  private function parseDecomposition(string $response): array
  {
    $result = [
      'is_atomic' => true,
      'reasoning' => '',
      'subproblems' => [],
    ];

    // Extract IS_ATOMIC
    if (preg_match('/IS_ATOMIC:\s*(yes|no)/i', $response, $match)) {
      $result['is_atomic'] = strtolower($match[1]) === 'yes';
    }

    // Extract REASONING
    if (preg_match('/REASONING:\s*(.+?)(?=SUBPROBLEM|$)/is', $response, $match)) {
      $result['reasoning'] = trim($match[1]);
    }

    // Extract SUBPROBLEMS
    if (!$result['is_atomic']) {
      preg_match_all('/SUBPROBLEM\s+\d+:\s*(.+?)(?=SUBPROBLEM|\n\n|$)/is', $response, $matches);
      $result['subproblems'] = array_map('trim', $matches[1]);
    }

    return $result;
  }

  /**
   * Verify solution consistency
   *
   * @param string $problem Original problem
   * @param string $solution Proposed solution
   * @return array Verification result
   */
  public function verifySolution(string $problem, string $solution): array
  {
    $prompt = $this->buildVerificationPrompt($problem, $solution);
    $response = Gpt::getGptResponse($prompt, 400);

    $parsed = $this->parseVerification($response);

    return [
      'is_correct' => $parsed['is_correct'],
      'confidence' => $parsed['confidence'],
      'explanation' => $parsed['explanation'],
      'issues' => $parsed['issues'] ?? [],
    ];
  }

  /**
   * Build verification prompt
   *
   * @param string $problem Original problem
   * @param string $solution Proposed solution
   * @return string Formatted prompt
   */
  private function buildVerificationPrompt(string $problem, string $solution): string
  {
    $CLICSHOPPING_Language = Registry::get('Language');

    // Load language file in English for internal processing
    DomainConfig::loadAgnosticLanguageFile('rag_reasoning_agent');

    // Get the prompt template
    $prompt = $CLICSHOPPING_Language->getDef('text_reasoning_verification_prompt');

    // Replace variables
    $prompt = str_replace('{{problem}}', $problem, $prompt);
    $prompt = str_replace('{{solution}}', $solution, $prompt);

    return $prompt;
  }

  /**
   * Parse verification response
   *
   * @param string $response LLM response
   * @return array Parsed verification with correctness flag and issues
   */
  private function parseVerification(string $response): array
  {
    $result = [
      'is_correct' => false,
      'confidence' => 0.5,
      'explanation' => '',
      'issues' => [],
    ];

    if (preg_match('/IS_CORRECT:\s*(yes|no)/i', $response, $match)) {
      $result['is_correct'] = strtolower($match[1]) === 'yes';
    }

    if (preg_match('/CONFIDENCE:\s*([\d\.]+)/i', $response, $match)) {
      $result['confidence'] = (float)$match[1];
    }

    if (preg_match('/EXPLANATION:\s*(.+?)(?=ISSUES:|$)/is', $response, $match)) {
      $result['explanation'] = trim($match[1]);
    }

    if (preg_match('/ISSUES:\s*(.+?)$/is', $response, $match)) {
      $issuesText = trim($match[1]);
      $result['issues'] = array_filter(explode("\n", $issuesText));
    }

    return $result;
  }

  /**
   * Get statistics from database (persistent across sessions)
   * 
   * @param int $days Number of days to look back
   * @return array Statistics from database
   */
  public function getPersistentStats(int $days = 30): array
  {
    if ($this->persistentStats === null) {
      return [
        'total_reasonings' => 0,
        'successful_reasonings' => 0,
        'failed_reasonings' => 0,
        'success_rate' => '0%',
        'by_mode' => [],
        'period_days' => $days,
      ];
    }

    try {
      return $this->persistentStats->getStats($days);
    } catch (\Exception $e) {
      error_log('ReasoningAgent: Failed to get persistent stats - ' . $e->getMessage());
      return [
        'total_reasonings' => 0,
        'successful_reasonings' => 0,
        'failed_reasonings' => 0,
        'success_rate' => '0%',
        'by_mode' => [],
        'period_days' => $days,
      ];
    }
  }

  // ========================================
  // AUTONOMOUS AGENT INTERFACE IMPLEMENTATION
  // ========================================

  /**
   * Get statistics
   *
   * @return array Statistics with success rate and configuration
   */
  public function getStats(): array
  {
    $total = $this->stats['successful_reasonings'] + $this->stats['failed_reasonings'];
    $successRate = $total > 0
      ? round(($this->stats['successful_reasonings'] / $total) * 100, 2)
      : 0;

    return array_merge($this->stats, [
      'success_rate' => $successRate . '%',
      'avg_steps' => round($this->stats['avg_steps'], 2),
      'configuration' => $this->getConfiguration(),
    ]);
  }

  /**
   * Create a local objective for reasoning improvements
   *
   * @param string $goalStatement Clear description of the goal
   * @param array $successCriteria Measurable success criteria
   * @param string $priority Priority level
   * @return \ClicShopping\AI\CoreAI\Orchestrator\SubAutonomous\LocalObjective
   */
  public function createLocalObjective(
    string $goalStatement,
    array $successCriteria,
    string $priority
  ): \ClicShopping\AI\CoreAI\Orchestrator\SubAutonomous\LocalObjective {

    if (!$this->autonomousConfig->canAgentCreateObjectives('ReasoningAgent')) {
      throw new \RuntimeException('ReasoningAgent is not authorized to create objectives (disabled in configuration)');
    }

    $estimatedTime = match ($priority) {
      'critical' => 300,
      'high' => 900,
      'medium' => 1800,
      'low' => 3600,
      default => 1800
    };

    $objective = new \ClicShopping\AI\CoreAI\Orchestrator\SubAutonomous\LocalObjective(
      'ReasoningAgent',
      $goalStatement,
      $successCriteria,
      $priority,
      $estimatedTime
    );

    $db = Registry::get('Db');
    $objectiveRegistry = new \ClicShopping\AI\CoreAI\Orchestrator\SubAutonomous\ObjectiveRegistry($db, $this->debug);
    $objectiveRegistry->registerObjective($objective);

    if ($this->debug) {
      $this->securityLogger->logSecurityEvent(
        "ReasoningAgent created objective: {$goalStatement}",
        'info'
      );
    }

    return $objective;
  }

  /**
   * Execute a reasoning improvement objective
   *
   * @param \ClicShopping\AI\CoreAI\Orchestrator\SubAutonomous\LocalObjective $objective
   * @return mixed Execution results
   */
  public function executeObjective(\ClicShopping\AI\CoreAI\Orchestrator\SubAutonomous\LocalObjective $objective): mixed
  {
    $goalStatement = $objective->getGoalStatement();

    if ($this->debug) {
      $this->securityLogger->logSecurityEvent(
        "ReasoningAgent executing objective: {$goalStatement}",
        'info'
      );
    }

    $objective->setStatus('active');

    try {
      $result = ['message' => 'Reasoning objective execution placeholder'];

      $objective->markCompleted([
        'execution_time' => time() - strtotime($objective->getCreatedAt()->format('Y-m-d H:i:s')),
        'result' => $result
      ]);

      return $result;

    } catch (\Exception $e) {
      $objective->markFailed($e->getMessage());
      throw $e;
    }
  }

  /**
   * Evaluate peer agent output (reasoning chains, logic)
   *
   * @param string $outputType Type of output
   * @param mixed $output The output to evaluate
   * @param array $criteria Evaluation criteria
   * @return \ClicShopping\AI\CoreAI\Orchestrator\SubAutonomous\AgentEvaluation
   */
  public function evaluatePeerOutput(
    string $outputType,
    mixed $output,
    array $criteria
  ): \ClicShopping\AI\CoreAI\Orchestrator\SubAutonomous\AgentEvaluation {

    if (!$this->autonomousConfig->canAgentEvaluatePeers('ReasoningAgent')) {
      throw new \RuntimeException('ReasoningAgent is not authorized to evaluate peers (disabled in configuration)');
    }

    $capabilities = $this->getEvaluationCapabilities();
    if (!isset($capabilities[$outputType])) {
      throw new \InvalidArgumentException(
        "ReasoningAgent cannot evaluate {$outputType}"
      );
    }

    $scores = match ($outputType) {
      'reasoning_chain' => $this->evaluateReasoningChain($output, $criteria),
      default => $this->getDefaultScores()
    };

    return new \ClicShopping\AI\CoreAI\Orchestrator\SubAutonomous\AgentEvaluation(
      'ReasoningAgent',
      $output['output_id'] ?? uniqid('output_'),
      $scores,
      $scores['feedback'] ?? 'Evaluation completed',
      $scores['strengths'] ?? [],
      $scores['improvements'] ?? []
    );
  }

  /**
   * Get evaluation capabilities for ReasoningAgent
   *
   * @return array Mapping of output types to capability levels
   */
  public function getEvaluationCapabilities(): array
  {
    return [
      'reasoning_chain' => 'expert',    // Expert in reasoning evaluation
      'validation_result' => 'competent', // Competent in validation
      'sql_query' => 'novice',          // Basic SQL understanding
      'data_analysis' => 'competent'    // Competent in analysis
    ];
  }

  /**
   * Evaluate reasoning chain quality
   *
   * @param mixed $output Reasoning chain output
   * @param array $criteria Evaluation criteria
   * @return array Evaluation scores
   */
  private function evaluateReasoningChain(mixed $output, array $criteria): array
  {
    $chain = $output['reasoning_chain'] ?? [];
    $stepCount = count($chain);

    $accuracyScore = 0.8;
    $completenessScore = $stepCount > 0 ? 0.9 : 0.5;
    $efficiencyScore = $stepCount <= 10 ? 0.9 : 0.7;
    $clarityScore = 0.8;

    $strengths = [];
    $improvements = [];

    if ($stepCount > 0) {
      $strengths[] = 'Reasoning chain has clear steps';
    } else {
      $improvements[] = 'Add explicit reasoning steps';
    }

    if ($stepCount > 10) {
      $improvements[] = 'Consider simplifying reasoning chain';
    }

    return [
      'accuracy_score' => $accuracyScore,
      'completeness_score' => $completenessScore,
      'efficiency_score' => $efficiencyScore,
      'clarity_score' => $clarityScore,
      'feedback' => 'Reasoning chain evaluation completed',
      'strengths' => $strengths,
      'improvements' => $improvements
    ];
  }

  /**
   * Get default evaluation scores
   *
   * @return array Default scores
   */
  private function getDefaultScores(): array
  {
    return [
      'accuracy_score' => 0.7,
      'completeness_score' => 0.7,
      'efficiency_score' => 0.7,
      'clarity_score' => 0.7,
      'feedback' => 'Default evaluation',
      'strengths' => [],
      'improvements' => []
    ];
  }

  /**
   * Receive and process feedback from peer agents
   *
   * @param array $feedback Feedback from peer agent
   */
  public function receiveFeedback(array $feedback): void
  {
    if ($this->debug) {
      $this->securityLogger->logSecurityEvent(
        "ReasoningAgent received feedback from {$feedback['source_agent_id']}",
        'info'
      );
    }

    $db = Registry::get('Db');
    $feedbackManager = new \ClicShopping\AI\CoreAI\Orchestrator\SubAutonomous\FeedbackManager($db, $this->debug);
    $feedbackManager->acknowledgeFeedback(
      $feedback['feedback_id'],
      'ReasoningAgent',
      null
    );
  }

  /**
   * Check if ReasoningAgent can collaborate on an objective
   *
   * @param \ClicShopping\AI\CoreAI\Orchestrator\SubAutonomous\LocalObjective $objective
   * @return bool True if can collaborate
   */
  public function canCollaborate(\ClicShopping\AI\CoreAI\Orchestrator\SubAutonomous\LocalObjective $objective): bool
  {
    $goalStatement = strtolower($objective->getGoalStatement());
    $keywords = ['reasoning', 'logic', 'analysis', 'thinking', 'problem', 'solution'];

    foreach ($keywords as $keyword) {
      if (str_contains($goalStatement, $keyword)) {
        return true;
      }
    }

    return false;
  }
}
