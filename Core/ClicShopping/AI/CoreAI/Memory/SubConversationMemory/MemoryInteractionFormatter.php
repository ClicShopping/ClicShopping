<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\CoreAI\Memory\SubConversationMemory;

use ClicShopping\AI\Config\TechnicalDefaults;

/**
 * MemoryInteractionFormatter Class
 *
 * Owns the interaction-text preparation concern extracted verbatim from
 * ConversationMemory (2026-06-28, memory decomposition step 3): turning a raw
 * user/system exchange into the plain semantic text the embedding model stores.
 * Stateless and pure.
 *
 * Responsibilities:
 * - Format a user/system interaction into the stored "User: …\n\nAssistant: …" shape
 * - Reduce an assistant response to plain embedding-ready text (strip HTML,
 *   decode entities, collapse whitespace, cap length)
 */
class MemoryInteractionFormatter
{
  /**
   * Formats an interaction for vector store storage.
   *
   * @param string $userMessage User message
   * @param string $systemResponse System response
   * @return string Formatted content
   */
  public function formatInteractionForStorage(
    string $userMessage,    string $systemResponse): string {
    $cleanedResponse = self::cleanResponseForEmbedding($systemResponse);

    return "User: {$userMessage}\n\nAssistant: {$cleanedResponse}";
  }

  /**
   * Inverse of formatInteractionForStorage(): splits a stored interaction back into its
   * user and assistant halves. Kept here so the storage shape stays known to one class only.
   *
   * @param string $content Stored content
   * @return array{user: string, assistant: string} Empty strings when the shape does not match
   */
  public function parseStoredInteraction(string $content): array
  {
    $empty = ['user' => '', 'assistant' => ''];

    if (!str_starts_with($content, 'User: ')) {
      return $empty;
    }

    $split = mb_strpos($content, "\n\nAssistant: ");
    if ($split === false) {
      return $empty;
    }

    return [
      'user' => trim(mb_substr($content, 6, $split - 6)),
      'assistant' => trim(mb_substr($content, $split + 13)),
    ];
  }

  /**
   * Reduces an assistant response to the plain semantic text the embedding
   * model needs. Strips HTML, decodes entities, collapses whitespace and
   * caps the result so a runaway answer never poisons the embedding cost.
   *
   * Cap: CLICSHOPPING_APP_CHATGPT_RA_EMBED_RESPONSE_MAX_CHARS (32 000 chars by default).
   */
  private static function cleanResponseForEmbedding(string $response): string
  {
    if ($response === '') {
      return $response;
    }

    // If there are no HTML tags at all, skip the work
    $hasTags = str_contains($response, '<');

    if ($hasTags) {
      $response = \strip_tags($response);
      $response = \html_entity_decode($response, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    $response = \preg_replace('/\s+/u', ' ', $response);
    $response = \trim($response);

    $maxChars = TechnicalDefaults::int('CLICSHOPPING_APP_CHATGPT_RA_EMBED_RESPONSE_MAX_CHARS');

    if ($maxChars <= 0) {
      $maxChars = 32000;
    }

    if (\mb_strlen($response) > $maxChars) {
      $response = \mb_substr($response, 0, $maxChars);
    }

    return $response;
  }
}
