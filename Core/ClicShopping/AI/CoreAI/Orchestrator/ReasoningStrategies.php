<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\CoreAI\Orchestrator;

use ClicShopping\AI\Security\SecurityLogger;
use ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\Gpt;
use ClicShopping\AI\Config\DomainConfig;
use ClicShopping\OM\Registry;

/**
 * ReasoningStrategies
 *
 * The three LLM reasoning strategies (Chain-of-Thought, Tree-of-Thought,
 * Self-Consistency) extracted verbatim from ReasoningAgent (2026-06-20).
 * ReasoningAgent::reason() dispatches to chainOfThought/treeOfThought/
 * selfConsistency by mode. Config (max steps, path counts) is snapshot at
 * construction — ReasoningAgent's setters have no post-construction callers.
 */
class ReasoningStrategies
{
  private SecurityLogger $securityLogger;
  private bool $debug;
  private int $maxReasoningSteps;
  private int $selfConsistencyPaths;
  private int $treeOfThoughtPaths;

  public function __construct(SecurityLogger $securityLogger, bool $debug, int $maxReasoningSteps, int $selfConsistencyPaths, int $treeOfThoughtPaths)
  {
    $this->securityLogger = $securityLogger;
    $this->debug = $debug;
    $this->maxReasoningSteps = $maxReasoningSteps;
    $this->selfConsistencyPaths = $selfConsistencyPaths;
    $this->treeOfThoughtPaths = $treeOfThoughtPaths;
  }

  /**
   * Chain-of-Thought reasoning with step-by-step analysis
   *
   * @param string $problem Problem to solve
   * @param array $context Additional context
   * @return array Reasoning result with steps
   */
  public function chainOfThought(string $problem, array $context): array
  {
    $prompt = $this->buildCoTPrompt($problem, $context);

    if ($this->debug) {
      $this->securityLogger->logSecurityEvent(
        "Starting Chain-of-Thought reasoning (max steps: {$this->maxReasoningSteps})",
        'info'
      );
    }

    $response = Gpt::getGptResponse($prompt, 1000);

    // Parser la réponse
    $parsed = $this->parseCoTResponse($response);

    // Enforce max reasoning steps
    $truncated = false;
    if (count($parsed['steps']) > $this->maxReasoningSteps) {
      $parsed['steps'] = array_slice($parsed['steps'], 0, $this->maxReasoningSteps);
      $truncated = true;

      if ($this->debug) {
        $this->securityLogger->logSecurityEvent(
          "Reasoning steps truncated to {$this->maxReasoningSteps}",
          'warning'
        );
      }
    }

    return [
      'success' => true,
      'method' => 'chain_of_thought',
      'problem' => $problem,
      'reasoning_steps' => $parsed['steps'],
      'final_answer' => $parsed['answer'],
      'confidence' => $parsed['confidence'] ?? 0.8,
      'steps_count' => count($parsed['steps']),
      'truncated' => $truncated,
    ];
  }

  /**
   * Build Chain-of-Thought prompt
   *
   * @param string $problem Problem to solve
   * @param array $context Additional context
   * @return string Formatted prompt
   */
  private function buildCoTPrompt(string $problem, array $context): string
  {
    $CLICSHOPPING_Language = Registry::get('Language');

    // Load language file in English for internal processing
    DomainConfig::loadAgnosticLanguageFile('rag_reasoning_agent');

    // Get the prompt template
    $prompt = $CLICSHOPPING_Language->getDef('text_reasoning_cot_prompt');

    // Build context string
    $contextStr = '';
    if (!empty($context)) {
      $contextParts = ["", "Context:"];
      foreach ($context as $key => $value) {
        if (is_string($value)) {
          $contextParts[] = "- {$key}: {$value}";
        }
      }
      $contextStr = implode("\n", $contextParts);
    }

    // Replace variables
    $prompt = str_replace('{{problem}}', $problem, $prompt);
    $prompt = str_replace('{{context}}', $contextStr, $prompt);

    return $prompt;
  }

  /**
   * Parse Chain-of-Thought response
   *
   * @param string $response LLM response
   * @return array Parsed steps, answer, and confidence
   */
  private function parseCoTResponse(string $response): array
  {
    $steps = [];
    $answer = '';
    $confidence = 0.8;

    // Extract steps
    preg_match_all('/STEP\s+(\d+):\s*(.+?)(?=STEP\s+\d+:|FINAL ANSWER:|$)/is', $response, $stepMatches, PREG_SET_ORDER);

    foreach ($stepMatches as $match) {
      $stepNum = (int)$match[1];
      $stepContent = trim($match[2]);

      $step = [
        'number' => $stepNum,
        'description' => '',
        'reasoning' => '',
        'result' => '',
      ];

      // Extract description
      if (preg_match('/^(.+?)(?:\n|Reasoning:)/i', $stepContent, $descMatch)) {
        $step['description'] = trim($descMatch[1]);
      }

      // Extract reasoning
      if (preg_match('/Reasoning:\s*(.+?)(?:\n|Result:|$)/is', $stepContent, $reasonMatch)) {
        $step['reasoning'] = trim($reasonMatch[1]);
      }

      // Extract result
      if (preg_match('/Result:\s*(.+?)$/is', $stepContent, $resultMatch)) {
        $step['result'] = trim($resultMatch[1]);
      }

      $steps[] = $step;
    }

    // Extract final answer
    if (preg_match('/FINAL ANSWER:\s*(.+?)(?=CONFIDENCE:|$)/is', $response, $answerMatch)) {
      $answer = trim($answerMatch[1]);
    }

    // Extract confidence
    if (preg_match('/CONFIDENCE:\s*([\d\.]+)/i', $response, $confMatch)) {
      $confidence = (float)$confMatch[1];
    }

    return [
      'steps' => $steps,
      'answer' => $answer,
      'confidence' => $confidence,
    ];
  }

  /**
   * Tree-of-Thought reasoning exploring multiple reasoning paths
   *
   * @param string $problem Problem to solve
   * @param array $context Additional context
   * @return array Best reasoning path and all explored paths
   */
  public function treeOfThought(string $problem, array $context): array
  {
    if ($this->debug) {
      $this->securityLogger->logSecurityEvent(
        "Starting Tree-of-Thought reasoning (paths: {$this->treeOfThoughtPaths})",
        'info'
      );
    }

    // Generate multiple reasoning paths
    $paths = [];

    for ($i = 0; $i < $this->treeOfThoughtPaths; $i++) {
      $prompt = $this->buildToTPrompt($problem, $context, $i);
      $response = Gpt::getGptResponse($prompt, 800);

      $paths[] = [
        'path_id' => $i + 1,
        'reasoning' => $response,
        'score' => $this->evaluatePath($response),
      ];
    }

    // Select best path
    usort($paths, fn($a, $b) => $b['score'] <=> $a['score']);
    $bestPath = $paths[0];

    return [
      'success' => true,
      'method' => 'tree_of_thought',
      'problem' => $problem,
      'explored_paths' => count($paths),
      'best_path' => $bestPath,
      'all_paths' => $paths,
      'final_answer' => $this->extractAnswer($bestPath['reasoning']),
      'confidence' => $bestPath['score'],
      'steps_count' => count($paths),
    ];
  }

  /**
   * Build Tree-of-Thought prompt
   *
   * @param string $problem Problem to solve
   * @param array $context Additional context
   * @param int $pathId Path identifier for approach variation
   * @return string Formatted prompt
   */
  private function buildToTPrompt(string $problem, array $context, int $pathId): string
  {
    $CLICSHOPPING_Language = Registry::get('Language');

    // Load language file in English for internal processing
    DomainConfig::loadAgnosticLanguageFile('rag_reasoning_agent');

    // Get the prompt template
    $prompt = $CLICSHOPPING_Language->getDef('text_reasoning_tot_prompt');

    // Get approach strings
    $approaches = [
      $CLICSHOPPING_Language->getDef('text_reasoning_tot_approach_data'),
      $CLICSHOPPING_Language->getDef('text_reasoning_tot_approach_logical'),
      $CLICSHOPPING_Language->getDef('text_reasoning_tot_approach_creative'),
    ];

    $approach = $approaches[$pathId] ?? $approaches[0];

    // Replace variables
    $prompt = str_replace('{{problem}}', $problem, $prompt);
    $prompt = str_replace('{{approach}}', $approach, $prompt);

    return $prompt;
  }

  /**
   * Evaluate quality of reasoning path
   *
   * @param string $reasoning Reasoning text
   * @return float Quality score (0.0-1.0)
   */
  private function evaluatePath(string $reasoning): float
  {
    $score = 0.5;

    // Quality criteria
    $wordCount = str_word_count($reasoning);
    if ($wordCount > 50) $score += 0.1;
    if ($wordCount > 100) $score += 0.1;

    // Structure presence
    if (preg_match('/\b(first|second|third|finally)\b/i', $reasoning)) {
      $score += 0.1;
    }

    // Justification presence
    if (preg_match('/\b(because|therefore|thus|hence)\b/i', $reasoning)) {
      $score += 0.1;
    }

    // Conclusion presence
    if (preg_match('/\b(conclusion|answer|result)\b/i', $reasoning)) {
      $score += 0.1;
    }

    return min(1.0, $score);
  }

  /**
   * Extract answer from reasoning text
   *
   * @param string $reasoning Reasoning text
   * @return string Extracted answer
   */
  private function extractAnswer(string $reasoning): string
  {
    // Look for explicit conclusion
    if (preg_match('/(?:conclusion|answer|result):\s*(.+?)(?:\n|$)/i', $reasoning, $match)) {
      return trim($match[1]);
    }

    // Otherwise, take last sentences
    $sentences = preg_split('/[.!?]+/', $reasoning);
    $sentences = array_filter(array_map('trim', $sentences));

    return end($sentences) ?: $reasoning;
  }

  /**
   * Self-Consistency reasoning generating multiple answers and voting
   *
   * @param string $problem Problem to solve
   * @param array $context Additional context
   * @return array Final answer with agreement rate
   */
  public function selfConsistency(string $problem, array $context): array
  {
    if ($this->debug) {
      $this->securityLogger->logSecurityEvent(
        "Starting Self-Consistency reasoning with {$this->selfConsistencyPaths} paths",
        'info'
      );
    }

    $answers = [];

    // Generate multiple answers
    for ($i = 0; $i < $this->selfConsistencyPaths; $i++) {
      $prompt = $this->buildCoTPrompt($problem, $context);
      $response = Gpt::getGptResponse($prompt, 800);

      $parsed = $this->parseCoTResponse($response);
      $answers[] = [
        'attempt' => $i + 1,
        'answer' => $parsed['answer'],
        'confidence' => $parsed['confidence'] ?? 0.8,
        'steps' => $parsed['steps'],
      ];
    }

    // Vote for best answer
    $finalAnswer = $this->voteForBestAnswer($answers);

    return [
      'success' => true,
      'method' => 'self_consistency',
      'problem' => $problem,
      'attempts' => count($answers),
      'all_answers' => $answers,
      'final_answer' => $finalAnswer['answer'],
      'confidence' => $finalAnswer['confidence'],
      'agreement_rate' => $finalAnswer['agreement_rate'],
      'steps_count' => count($answers),
    ];
  }

  /**
   * Vote for best answer from multiple attempts
   *
   * @param array $answers Array of answer attempts
   * @return array Best answer with confidence and agreement rate
   */
  private function voteForBestAnswer(array $answers): array
  {
    // Count occurrences of each answer
    $votes = [];

    foreach ($answers as $answer) {
      $normalizedAnswer = $this->normalizeAnswer($answer['answer']);

      if (!isset($votes[$normalizedAnswer])) {
        $votes[$normalizedAnswer] = [
          'answer' => $answer['answer'],
          'count' => 0,
          'total_confidence' => 0,
        ];
      }

      $votes[$normalizedAnswer]['count']++;
      $votes[$normalizedAnswer]['total_confidence'] += $answer['confidence'];
    }

    // Find answer with most votes
    $winner = null;
    $maxVotes = 0;

    foreach ($votes as $normalizedAnswer => $voteData) {
      if ($voteData['count'] > $maxVotes) {
        $maxVotes = $voteData['count'];
        $winner = $voteData;
      }
    }

    if (!$winner) {
      $winner = $votes[array_key_first($votes)];
    }

    $agreementRate = $winner['count'] / count($answers);
    $avgConfidence = $winner['total_confidence'] / $winner['count'];

    return [
      'answer' => $winner['answer'],
      'confidence' => $avgConfidence * $agreementRate,
      'agreement_rate' => $agreementRate,
      'votes' => $winner['count'],
    ];
  }

  /**
   * Normalize answer for comparison
   *
   * @param string $answer Answer text
   * @return string Normalized answer
   */
  private function normalizeAnswer(string $answer): string
  {
    $normalized = strtolower(trim($answer));
    $normalized = preg_replace('/[^\w\s]/', '', $normalized);
    $normalized = preg_replace('/\s+/', ' ', $normalized);

    return $normalized;
  }
}
