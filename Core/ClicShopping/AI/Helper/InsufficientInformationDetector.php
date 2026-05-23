<?php
/**
 * InsufficientInformationDetector
 * 
 * Helper class to detect generic "insufficient information" responses
 * Uses language definitions for multilingual pattern matching
 * Dynamically loads patterns for all available languages
 *
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\Helper;

use ClicShopping\OM\Registry;
use ClicShopping\AI\Config\DomainConfig;

/**
 * InsufficientInformationDetector Class
 *
 * Detects generic "insufficient information" responses to trigger fallback chain
 * Supports multiple languages dynamically
 */
class InsufficientInformationDetector
{
  private $language;
  private array $patterns = [];
  private int $minResponseLength = 20;
  private bool $initialized = false;

  /**
   * Constructor
   */
  public function __construct()
  {
    $this->language = Registry::get('Language');
    DomainConfig::loadLanguageFile('rag_semantic_search_orchestrator');
    $this->loadPatterns();
  }

  /**
   * Load insufficient information patterns from language definitions
   * All patterns (multilingual) are stored in text_insufficient_info_patterns
   * 
   * @return void
   */
  private function loadPatterns(): void
  {
    if ($this->initialized) {
      return;
    }

    // Load all patterns (includes English, French, Spanish, Chinese, etc.)
    $allPatterns = $this->language->getDef('text_insufficient_info_patterns');
    if (!empty($allPatterns) && $allPatterns !== 'text_insufficient_info_patterns') {
      $this->patterns = explode('|', $allPatterns);
      // Remove duplicates and trim patterns
      $this->patterns = array_unique(array_map('trim', $this->patterns));
    }
    
    // Load minimum response length
    $minLength = $this->language->getDef('text_min_response_length');
    if (!empty($minLength) && is_numeric($minLength)) {
      $this->minResponseLength = (int)$minLength;
    }

    $this->initialized = true;
  }

  /**
   * Check if response contains insufficient information patterns
   * 
   * @param string $response Response text to check
   * @return bool True if response is generic "insufficient information"
   */
  public function isInsufficientInformation(string $response): bool
  {
    // Ensure patterns are loaded
    if (!$this->initialized) {
      $this->loadPatterns();
    }

    // Check if response is empty or too short
    if (empty($response) || strlen(trim($response)) < $this->minResponseLength) {
      return true;
    }
    
    // Check against all patterns (case-insensitive)
    foreach ($this->patterns as $pattern) {
      if (!empty($pattern) && stripos($response, $pattern) !== false) {
        return true;
      }
    }
    
    return false;
  }

  /**
   * Get loaded patterns (for debugging)
   * 
   * @return array Array of patterns
   */
  public function getPatterns(): array
  {
    if (!$this->initialized) {
      $this->loadPatterns();
    }
    
    return $this->patterns;
  }

  /**
   * Get minimum response length
   * 
   * @return int Minimum length
   */
  public function getMinResponseLength(): int
  {
    return $this->minResponseLength;
  }

  /**
   * Get pattern count
   * 
   * @return int Number of loaded patterns
   */
  public function getPatternCount(): int
  {
    if (!$this->initialized) {
      $this->loadPatterns();
    }
    
    return count($this->patterns);
  }
}
