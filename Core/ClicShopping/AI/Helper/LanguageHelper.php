<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\Helper;

/**
 * LanguageHelper Class
 * 
 * Provides language-related utility functions for text processing.
 * 
 * @package ClicShopping\AI\Helper
 */
class LanguageHelper
{
  /**
   * Returns an array of common English stop words.
   * 
   * Stop words are common words that are typically filtered out
   * during text processing and keyword extraction because they
   * don't carry significant meaning.
   *
   * @return array List of stop words
   */
  public static function stopWord(): array
  {
    return [
      'the', 'a', 'an', 'and', 'or', 'with', 'without', 
      'for', 'by', 'on', 'in', 'at', 'to', 'of', 'from', 
      'me', 'you', 'he', 'she', 'it', 'we', 'they', 
      'this', 'that', 'these', 'those', 
      'can', 'give', 'have', 'be', 'do', 'go'
    ];
  }
}
