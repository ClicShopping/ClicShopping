<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\CoreAI\Memory\SubConversationMemory;

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
   * Reduces an assistant response to the plain semantic text the embedding
   * model needs. Strips HTML, decodes entities, collapses whitespace and
   * caps the result so a runaway answer never poisons the embedding cost.
   *
   * The default cap (32 000 chars) is well above the typical text content of
   * a Hybrid response (≈ 5 KB) yet keeps even large pages inside one chunk.
   * Override via CLICSHOPPING_APP_CHATGPT_RA_EMBED_RESPONSE_MAX_CHARS.
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

    $maxChars = 32000;
    if (\defined('CLICSHOPPING_APP_CHATGPT_RA_EMBED_RESPONSE_MAX_CHARS')) {
      $override = (int) \constant('CLICSHOPPING_APP_CHATGPT_RA_EMBED_RESPONSE_MAX_CHARS');
      if ($override > 0) {
        $maxChars = $override;
      }
    }

    if (\mb_strlen($response) > $maxChars) {
      $response = \mb_substr($response, 0, $maxChars);
    }

    return $response;
  }
}
