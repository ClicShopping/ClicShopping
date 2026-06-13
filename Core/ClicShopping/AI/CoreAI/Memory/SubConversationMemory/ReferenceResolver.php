<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\CoreAI\Memory\SubConversationMemory;

use ClicShopping\AI\Security\SecurityLogger;
use ClicShopping\AI\Config\DomainConfig;
use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\Gpt;

/**
 * ReferenceResolver
 *
 * Agnostic, agentic resolution of contextual references for a search (sub-)query.
 * Given a query and the last discussed entity, an LLM decides whether the query references
 * that entity and, if so, rewrites it into a self-contained query. Self-contained queries
 * (documents, policies, unrelated topics) are returned untouched — no entity pollution (§R).
 *
 * Fail-safe: any uncertainty (missing name, bare id, LLM error, unparseable output) returns
 * the original query with references_entity=false. The worst case is a clean, un-enriched
 * search — never a polluted one.
 */
class ReferenceResolver
{
  private SecurityLogger $logger;
  private bool $debug;
  /** @var callable(string):string */
  private $llm;

  /**
   * @param bool $debug Enable debug logging
   * @param callable|null $llm Optional LLM callable (string $prompt): string — injected in tests.
   *                           Defaults to the Gpt façade.
   * @param SecurityLogger|null $logger Optional logger — injected in tests (e.g. a higher log
   *                                    level) so the fail-safe warnings do not pollute the
   *                                    shared security log during test runs.
   */
  public function __construct(bool $debug = false, ?callable $llm = null, ?SecurityLogger $logger = null)
  {
    $this->logger = $logger ?? new SecurityLogger();
    $this->debug = $debug;
    $this->llm = $llm ?? static fn(string $prompt): string => (string)Gpt::getGptResponse($prompt, 300, 0.0);
  }

  /**
   * Resolve contextual references in a query against the last entity.
   *
   * @param string $query Search query (in English — already translated upstream)
   * @param array $lastEntity Last entity context (keys: type, name, id)
   * @return array{resolved_query: string, references_entity: bool}
   */
  public function resolve(string $query, array $lastEntity): array
  {
    $unchanged = ['resolved_query' => $query, 'references_entity' => false];

    $name = $lastEntity['name'] ?? null;
    // §R guard: a missing name or a bare numeric id is never a useful search token.
    if ($name === null || trim((string)$name) === '' || ctype_digit(trim((string)$name))) {
      return $unchanged;
    }

    $entityType = (string)($lastEntity['type'] ?? 'entity');

    // Internal prompt -> English, from the agnostic Agents/ layer.
    DomainConfig::loadAgnosticLanguageFile('rag_reference_resolution', 'en');
    $prompt = CLICSHOPPING::getDef('text_reference_resolution_prompt', [
      'query' => $query,
      'entity_type' => $entityType,
      'entity_name' => (string)$name,
    ]);

    try {
      $raw = ($this->llm)($prompt);
    } catch (\Throwable $e) {
      $this->logger->logSecurityEvent('ReferenceResolver LLM error: ' . $e->getMessage(), 'warning');
      return $unchanged;
    }

    $parsed = $this->parseJson((string)$raw);
    if ($parsed === null) {
      $this->logger->logSecurityEvent('ReferenceResolver JSON parse failure', 'warning');
      return $unchanged;
    }

    $references = (bool)($parsed['references_entity'] ?? false);
    $resolved = trim((string)($parsed['resolved_query'] ?? ''));

    if (!$references || $resolved === '') {
      return $unchanged;
    }

    if ($this->debug) {
      $this->logger->logSecurityEvent("ReferenceResolver: '{$query}' → '{$resolved}'", 'info');
    }

    return ['resolved_query' => $resolved, 'references_entity' => true];
  }

  /**
   * Parse a JSON object out of a possibly noisy LLM response (markdown fences, prose).
   *
   * @return array<string,mixed>|null
   */
  private function parseJson(string $raw): ?array
  {
    $s = trim($raw);
    if (str_starts_with($s, '```')) {
      $s = (string)preg_replace('/^```[a-zA-Z]*\s*|\s*```$/', '', $s);
    }
    $start = strpos($s, '{');
    $end = strrpos($s, '}');
    if ($start === false || $end === false || $end < $start) {
      return null;
    }
    $data = json_decode(substr($s, $start, $end - $start + 1), true);

    return is_array($data) ? $data : null;
  }
}
