<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\CoreAI\Query;

use ClicShopping\OM\Registry;
use ClicShopping\AI\Config\DomainConfig;
use ClicShopping\AI\Security\SecurityLogger;
use ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\Gpt;

/**
 * ContextRelationResolver
 *
 * Pure LLM Mode replacement for the keyword relation/enrichment in {@see QueryAnalyzer}:
 * given the recent conversation and the current query, it decides whether the query continues
 * the conversation and, when it does, rewrites it as ONE self-contained English request that
 * carries the earlier turn's constraints plus the new change. Language-agnostic and domain-agnostic
 * (it names no table, entity or brand — the reasoning is purely conversational).
 *
 * Returns null when the LLM is unavailable or its output cannot be parsed, so the caller falls
 * back to the keyword analyzer (honest degradation, never a hard failure).
 */
class ContextRelationResolver
{
  private const RESPONSE_MAX_TOKENS = 400;
  private const RECENT_TURNS = 6;

  private mixed $language;
  private bool $loaded = false;

  public function __construct(
    private SecurityLogger $logger,
    private bool $debug = false
  ) {
    $this->language = Registry::get('Language');
  }

  /**
   * Resolve the query against the recent conversation turns.
   *
   * @param string $query       The current, possibly elliptical, user query
   * @param array  $recentTurns Cross-request turns [['user'=>, 'assistant'=>], ...], oldest first
   * @return array|null ['is_related_to_context'=>bool, 'relation_type'=>string, 'enriched_query'=>string]
   *                    or null when the LLM resolution could not be made (caller falls back)
   */
  public function resolve(string $query, array $recentTurns): ?array
  {
    $conversation = $this->formatConversation($recentTurns);
    if ($conversation === '') {
      return ['is_related_to_context' => false, 'relation_type' => 'new_query', 'enriched_query' => $query];
    }

    if (!$this->loaded) {
      DomainConfig::loadAgnosticLanguageFile('rag_context_relation');
      $this->loaded = true;
    }

    $prompt = $this->language->getDef('context_relation_prompt', [
      'conversation' => $conversation,
      'query' => $query,
    ]);

    try {
      $response = Gpt::getGptResponse($prompt, self::RESPONSE_MAX_TOKENS, 0.0);
    } catch (\Throwable $e) {
      $this->logger->logStructured('warning', 'ContextRelationResolver', 'llm_call_failed', [
        'error' => $e->getMessage(),
      ]);
      return null;
    }

    $parsed = json_decode($this->cleanJson($response), true);
    if (!is_array($parsed) || !array_key_exists('is_related_to_context', $parsed)) {
      $this->logger->logStructured('warning', 'ContextRelationResolver', 'unparseable_response', [
        'response' => is_string($response) ? substr($response, 0, 300) : gettype($response),
      ]);
      return null;
    }

    $isRelated = (bool)$parsed['is_related_to_context'];
    $enriched = (isset($parsed['enriched_query']) && is_string($parsed['enriched_query']) && trim($parsed['enriched_query']) !== '')
      ? trim($parsed['enriched_query'])
      : $query;

    if ($this->debug) {
      $this->logger->logStructured('info', 'ContextRelationResolver', 'resolved', [
        'is_related' => $isRelated,
        'relation_type' => $parsed['relation_type'] ?? null,
        'enriched_query' => $isRelated ? $enriched : $query,
      ]);
    }

    return [
      'is_related_to_context' => $isRelated,
      'relation_type' => is_string($parsed['relation_type'] ?? null) ? $parsed['relation_type'] : ($isRelated ? 'continuation' : 'new_query'),
      'enriched_query' => $isRelated ? $enriched : $query,
    ];
  }

  /** Render the recent turns as "User: …\nAssistant: …" lines; empty string when there is no history. */
  private function formatConversation(array $recentTurns): string
  {
    if ($recentTurns === []) {
      return '';
    }

    $lines = [];
    foreach (array_slice($recentTurns, -self::RECENT_TURNS) as $turn) {
      $user = trim((string)($turn['user'] ?? ''));
      $assistant = trim((string)($turn['assistant'] ?? ''));
      if ($user !== '') {
        $lines[] = 'User: ' . $user;
      }
      if ($assistant !== '') {
        $lines[] = 'Assistant: ' . $assistant;
      }
    }

    return implode("\n", $lines);
  }

  /** Strip a leading/trailing markdown code fence so json_decode sees raw JSON. */
  private function cleanJson(string $response): string
  {
    $t = trim($response);
    if (str_starts_with($t, '```')) {
      $t = preg_replace('/^```[a-zA-Z]*\s*/', '', $t);
      $t = preg_replace('/\s*```$/', '', (string)$t);
    }
    return trim((string)$t);
  }
}
