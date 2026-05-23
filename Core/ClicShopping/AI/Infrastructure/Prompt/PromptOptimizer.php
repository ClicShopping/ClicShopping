<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\Infrastructure\Prompt;

use Yethee\Tiktoken\EncoderProvider;
use ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\Gpt;

/**
 * PromptOptimizer
 *
 * Optimizes prompts for model-specific context length limits.
 * Handles token estimation and intelligent truncation to ensure prompts fit within model constraints.
 *
 * SIMPLIFIED APPROACH (following Task 1.1 pattern):
 * - Uses tiktoken (from LLPhant) for accurate token counting
 * - Reads model context limits dynamically from Gpt::getGptModel()
 * - No hardcoded model limits - extracts from model text descriptions
 * - Easy to maintain - just update Gpt.php when adding new models
 *
 * Truncation Priority:
 * 1. Keep system instructions (highest priority)
 * 2. Keep recent conversation (last 3-5 messages)
 * 3. Keep current query
 * 4. Truncate old conversation history (oldest first)
 * 5. Truncate examples (if present)
 * 6. Compress system instructions (last resort)
 */
class PromptOptimizer
{
  /**
   * Tiktoken encoder for accurate token counting
   */
  private $encoder;

  /**
   * Tiktoken encoding to use (cl100k_base for GPT-4/GPT-4o/GPT-5)
   */
  private string $encoding = 'cl100k_base';

  /**
   * Safety margin (use 75% of actual limit)
   */
  private const SAFETY_MARGIN = 0.75;

  /**
   * Average characters per token for fallback estimation
   * Based on empirical data: ~4 chars/token in English, ~2.5 in French
   */
  private const AVG_CHARS_PER_TOKEN = 3.5;

  /**
   * Constructor
   */
  public function __construct()
  {
    // Initialize tiktoken encoder
    try {
      $provider = new EncoderProvider();
      $this->encoder = $provider->get($this->encoding);
    } catch (\Exception $e) {
      // Fallback to null if tiktoken not available
      $this->encoder = null;
      error_log("PromptOptimizer: Tiktoken not available, using character-based estimation");
    }
  }

  /**
   * Optimize prompt for specific model
   *
   * @param string $prompt Original prompt
   * @param string $model Target model name
   * @return string Optimized prompt (truncated if needed)
   */
  public function optimizeForModel(string $prompt, string $model): string
  {
    // Get model's token limit
    $maxTokens = $this->getModelLimit($model);
    
    // Estimate current token count
    $currentTokens = $this->estimateTokenCount($prompt);
    
    // Check if truncation is needed
    if ($currentTokens <= $maxTokens) {
      return $prompt; // No optimization needed
    }
    
    // Truncate prompt to fit model limit
    return $this->truncatePrompt($prompt, $maxTokens);
  }

  /**
   * Estimate token count for prompt
   *
   * Uses tiktoken for accurate token counting when available.
   * Falls back to character-based estimation if tiktoken is not available.
   *
   * Character-based estimation:
   * - ~4 characters per token in English
   * - ~2.5 characters per token in French
   * - Average: 3.5 characters per token
   *
   * @param string $prompt Prompt text
   * @return int Estimated token count
   */
  public function estimateTokenCount(string $prompt): int
  {
    // Use tiktoken if available (accurate)
    if ($this->encoder !== null) {
      try {
        $tokens = $this->encoder->encode($prompt);
        return count($tokens);
      } catch (\Exception $e) {
        error_log("PromptOptimizer: Tiktoken encoding failed: " . $e->getMessage());
        // Fall through to character-based estimation
      }
    }
    
    // Fallback: character-based estimation
    return (int)ceil(strlen($prompt) / self::AVG_CHARS_PER_TOKEN);
  }

  /**
   * Truncate prompt to fit context length
   *
   * Truncation priority:
   * 1. Keep system instructions (highest priority)
   * 2. Keep recent conversation (last 3-5 messages)
   * 3. Keep current query
   * 4. Truncate old conversation history (oldest first)
   * 5. Truncate examples (if present)
   *
   * @param string $prompt Original prompt
   * @param int $maxTokens Maximum tokens allowed
   * @return string Truncated prompt
   */
  public function truncatePrompt(string $prompt, int $maxTokens): string
  {
    // Parse prompt into sections
    $sections = $this->parsePromptSections($prompt);
    
    // Calculate current tokens
    $currentTokens = $this->estimateTokenCount($prompt);
    
    if ($currentTokens <= $maxTokens) {
      return $prompt; // No truncation needed
    }
    
    // Calculate tokens to remove
    $tokensToRemove = $currentTokens - $maxTokens;
    
    // Step 1: Truncate old conversation history (keep last 5 messages)
    if (isset($sections['conversation']) && !empty($sections['conversation'])) {
      $sections['conversation'] = $this->truncateConversation(
        $sections['conversation'],
        5 // Keep last 5 messages
      );
    }
    
    // Check if we're under limit now
    $rebuiltPrompt = $this->buildPrompt($sections);
    if ($this->estimateTokenCount($rebuiltPrompt) <= $maxTokens) {
      return $rebuiltPrompt;
    }
    
    // Step 2: Remove examples if still over limit
    if (isset($sections['examples'])) {
      $sections['examples'] = '';
    }
    
    // Check again
    $rebuiltPrompt = $this->buildPrompt($sections);
    if ($this->estimateTokenCount($rebuiltPrompt) <= $maxTokens) {
      return $rebuiltPrompt;
    }
    
    // Step 3: Compress system instructions (last resort)
    if (isset($sections['system'])) {
      $sections['system'] = $this->compressSystemInstructions($sections['system'], $maxTokens);
    }
    
    return $this->buildPrompt($sections);
  }

  /**
   * Check if prompt exceeds model limit
   *
   * @param string $prompt Prompt text
   * @param string $model Target model
   * @return bool True if prompt exceeds limit
   */
  public function exceedsLimit(string $prompt, string $model): bool
  {
    $tokenCount = $this->estimateTokenCount($prompt);
    $limit = $this->getModelLimit($model);
    
    return $tokenCount > $limit;
  }

  /**
   * Get model's token limit dynamically from Gpt::getGptModel()
   *
   * Extracts context length from model text description.
   * Examples:
   * - "OpenAI GPT-4o (128K context, ...)" → 128000 tokens
   * - "Anthropic Claude Sonnet 3.5 (200K context, ...)" → 200000 tokens
   * - "LM Studio Phi-4 Reasoning (16K context, ...)" → 16000 tokens
   *
   * Applies 75% safety margin to avoid hitting limits.
   *
   * @param string $model Model name (e.g., 'gpt-4o', 'anth-sonnet')
   * @return int Token limit with safety margin (defaults to 96000 if not found)
   */
  public function getModelLimit(string $model): int
  {
    // Get model list from Gpt.php
    $models = Gpt::getGptModel();
    
    // Find the model in the list
    foreach ($models as $modelInfo) {
      if ($modelInfo['id'] === $model) {
        // Extract context length from text description
        // Pattern: "(\d+)K context"
        if (preg_match('/(\d+)K\s+context/i', $modelInfo['text'], $matches)) {
          $contextK = (int)$matches[1];
          $contextTokens = $contextK * 1000;
          
          // Apply safety margin (75%)
          return (int)($contextTokens * self::SAFETY_MARGIN);
        }
      }
    }
    
    // Default to 96K (safe for most models: 128K * 0.75)
    return 96000;
  }

  /**
   * Parse prompt into sections
   *
   * Sections:
   * - system: System instructions
   * - conversation: Conversation history
   * - examples: Example queries/responses
   * - query: Current query
   *
   * @param string $prompt Full prompt text
   * @return array Parsed sections
   */
  private function parsePromptSections(string $prompt): array
  {
    $sections = [
      'system' => '',
      'conversation' => [],
      'examples' => '',
      'query' => ''
    ];
    
    // Simple parsing: split by common markers
    // System instructions usually at the start
    if (preg_match('/^(.*?)(User:|Assistant:|Query:|Example:)/s', $prompt, $matches)) {
      $sections['system'] = trim($matches[1]);
      $remaining = substr($prompt, strlen($matches[1]));
    } else {
      $remaining = $prompt;
    }
    
    // Extract conversation history (User/Assistant pairs)
    if (preg_match_all('/(User|Assistant):\s*(.*?)(?=(User|Assistant):|$)/s', $remaining, $matches, PREG_SET_ORDER)) {
      foreach ($matches as $match) {
        $sections['conversation'][] = [
          'role' => strtolower($match[1]),
          'content' => trim($match[2])
        ];
      }
    }
    
    // Extract examples (if present)
    if (preg_match('/Example[s]?:(.*?)(?=Query:|User:|$)/s', $remaining, $matches)) {
      $sections['examples'] = trim($matches[1]);
    }
    
    // Current query is usually at the end
    if (preg_match('/Query:\s*(.*?)$/s', $remaining, $matches)) {
      $sections['query'] = trim($matches[1]);
    } elseif (!empty($sections['conversation'])) {
      // If no explicit query marker, last user message is the query
      $lastMessage = end($sections['conversation']);
      if ($lastMessage['role'] === 'user') {
        $sections['query'] = $lastMessage['content'];
      }
    }
    
    return $sections;
  }

  /**
   * Truncate conversation history (keep recent messages)
   *
   * @param array $conversation Conversation messages
   * @param int $keepLast Number of recent messages to keep
   * @return array Truncated conversation
   */
  private function truncateConversation(array $conversation, int $keepLast): array
  {
    if (count($conversation) <= $keepLast) {
      return $conversation;
    }
    
    // Keep last N messages
    return array_slice($conversation, -$keepLast);
  }

  /**
   * Compress system instructions
   *
   * Removes verbose explanations while keeping core instructions
   *
   * @param string $system System instructions
   * @param int $maxTokens Maximum tokens allowed
   * @return string Compressed instructions
   */
  private function compressSystemInstructions(string $system, int $maxTokens): string
  {
    // Remove common verbose patterns
    $compressed = $system;
    
    // Remove examples within system instructions
    $compressed = preg_replace('/Example[s]?:.*?(?=\n\n|\n[A-Z]|$)/s', '', $compressed);
    
    // Remove detailed explanations (lines starting with "Note:", "Important:", etc.)
    $compressed = preg_replace('/^(Note|Important|Remember|Tip|Warning):.*$/m', '', $compressed);
    
    // Remove multiple blank lines
    $compressed = preg_replace('/\n{3,}/', "\n\n", $compressed);
    
    // If still too long, truncate to fit
    $estimatedTokens = $this->estimateTokenCount($compressed);
    if ($estimatedTokens > $maxTokens) {
      $targetChars = (int)($maxTokens * self::AVG_CHARS_PER_TOKEN);
      $compressed = substr($compressed, 0, $targetChars) . "\n\n[System instructions truncated to fit context limit]";
    }
    
    return trim($compressed);
  }

  /**
   * Build prompt from sections
   *
   * @param array $sections Parsed sections
   * @return string Rebuilt prompt
   */
  private function buildPrompt(array $sections): string
  {
    $parts = [];
    
    // Add system instructions
    if (!empty($sections['system'])) {
      $parts[] = $sections['system'];
    }
    
    // Add examples
    if (!empty($sections['examples'])) {
      $parts[] = "Examples:\n" . $sections['examples'];
    }
    
    // Add conversation history
    if (!empty($sections['conversation'])) {
      foreach ($sections['conversation'] as $message) {
        $role = ucfirst($message['role']);
        $parts[] = "{$role}: {$message['content']}";
      }
    }
    
    // Add current query
    if (!empty($sections['query'])) {
      $parts[] = "Query: {$sections['query']}";
    }
    
    return implode("\n\n", $parts);
  }
}
