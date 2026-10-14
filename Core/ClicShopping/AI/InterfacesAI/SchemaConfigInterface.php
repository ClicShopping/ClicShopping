<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\InterfacesAI;

/**
 * SchemaConfigInterface
 *
 * Contract for domain-specific schema rules used by the AI schema retriever.
 * Implementations should provide concise, LLM-friendly guidance for their domain.
 *
 * @package ClicShopping\AI\InterfacesAI
 */
interface SchemaConfigInterface
{
  /**
   * Get schema rules as an array of strings.
   *
   * @return array
   */
  public static function getSchemaRules(): array;

  /**
   * Get schema rules as a single formatted string.
   *
   * @return string
   */
  public static function getSchemaRulesString(): string;
}
