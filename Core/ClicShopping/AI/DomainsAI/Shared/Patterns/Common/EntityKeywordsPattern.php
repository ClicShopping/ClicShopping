<?php
/**
 * EntityKeywordsPattern.php
 * 
 * Centralized entity keywords shared across multiple domains.
 * Extracted from SuperlativePatterns, WebSearchPatterns, and EntityDetectionPattern
 * to avoid duplication and ensure consistency.
 * 
 * @package ClicShopping\AI\DomainsAI\Shared\Patterns\Common
 * @since 2026-01-09
 * 
 * REFACTORING: Centralized from multiple pattern files
 * - SuperlativePatterns::$entityKeywords
 * - WebSearchPatterns::$entityKeywords
 * - EntityDetectionPattern::getPatterns()
  *
 * @deprecated Pattern-based logic superseded by Pure LLM Mode
 *             Scheduled for removal in Q3 2026
 *             Use UnifiedQueryAnalyzer for intent classification instead
 *             See Domain/Patterns/DEPRECATED.md for migration guide
 **/

namespace ClicShopping\AI\DomainsAI\Shared\Patterns\Common;

use ClicShopping\AI\DomainsAI\DomainRegistry;

// Agnostic reader: the entity vocabulary is owned by the active domain (§Q-quater).

class EntityKeywordsPattern
{
  /**
   * Resolve the active domain's entity-vocabulary class, or null when no domain
   * exposes one (agnostic default = pure LLM, no hardcoded vocabulary in Core).
   */
  private static function resolveVocabClass(): ?string
  {
    $app = DomainRegistry::getInstance()->getActiveApp();

    if ($app !== null && method_exists($app, 'getEntityKeywordsClass')) {
      $class = $app->getEntityKeywordsClass();

      if (is_string($class) && $class !== '' && class_exists($class)) {
        return $class;
      }
    }

    return null;
  }

  /** @return array<string> */
  public static function getKeywords(): array
  {
    $class = self::resolveVocabClass();
    return $class !== null ? $class::getKeywords() : [];
  }

  /** @return array<string, array<string>> */
  public static function getPatterns(): array
  {
    $class = self::resolveVocabClass();
    return $class !== null ? $class::getPatterns() : [];
  }

  /** @return array<string> */
  public static function getFinancialMetricKeywords(): array
  {
    $class = self::resolveVocabClass();

    if ($class !== null && method_exists($class, 'getFinancialMetricKeywords')) {
      return $class::getFinancialMetricKeywords();
    }

    return [];
  }

  /** @return array<string> */
  public static function getKeywordsForEntity(string $entityType): array
  {
    return self::getPatterns()[$entityType] ?? [];
  }

  public static function isEntityKeyword(string $keyword): bool
  {
    return in_array(mb_strtolower($keyword), self::getKeywords(), true);
  }

  public static function getEntityTypeForKeyword(string $keyword): ?string
  {
    $keyword = mb_strtolower($keyword);

    foreach (self::getPatterns() as $entityType => $keywords) {
      if (in_array($keyword, $keywords, true)) {
        return $entityType;
      }
    }

    return null;
  }

  /** @return array<string> */
  public static function getEntityTypes(): array
  {
    return array_keys(self::getPatterns());
  }

  /** @return array<string, mixed> */
  public static function getMetadata(): array
  {
    return [
      'name' => 'Entity Keywords Pattern',
      'description' => 'Agnostic reader; entity vocabulary is owned by the active domain',
      'entity_types' => self::getEntityTypes(),
      'total_keywords' => count(self::getKeywords()),
      'source' => 'active domain via DomainRegistry::getActiveApp()->getEntityKeywordsClass()',
    ];
  }
}
