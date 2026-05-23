<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\DomainsAI\Analytics\Patterns;

/**
 * TemporalPeriodMappingPattern
 *
 * Provides patterns for mapping unrecognized temporal periods to standard periods.
 * Extracted from UnifiedQueryAnalyzer for reusability.
 *
 * Handles variations like:
 * - "biweekly" → every 2 weeks
 * - "fortnightly" → every 2 weeks
 * - "quarterly" → quarter
 * - "fiscal year" → year (fiscal)
 * - "rolling 12 months" → custom rolling period
 *
 * @package ClicShopping\AI\Domain\Patterns\Analytics
  *
 * @deprecated Pattern-based logic superseded by Pure LLM Mode
 *             Scheduled for removal in Q3 2026
 *             Use UnifiedQueryAnalyzer for intent classification instead
 *             See Domain/Patterns/DEPRECATED.md for migration guide
 **/
// DEPRECATED: Pattern-based logic superseded by Pure LLM Mode. Scheduled for removal in Q3 2026.

class TemporalPeriodMappingPattern
{
  /**
   * Get temporal period mappings
   *
   * Returns mappings from common variations to standard periods.
   *
   * @return array Associative array of period_variation => mapping_info
   */
  public static function getMappings(): array
  {
    return [
      // Week variations
      'biweekly' => ['standard_period' => 'custom', 'custom_period' => ['type' => 'weeks', 'interval' => 2], 'interpretation' => 'Every 2 weeks'],
      'bi-weekly' => ['standard_period' => 'custom', 'custom_period' => ['type' => 'weeks', 'interval' => 2], 'interpretation' => 'Every 2 weeks'],
      'fortnightly' => ['standard_period' => 'custom', 'custom_period' => ['type' => 'weeks', 'interval' => 2], 'interpretation' => 'Every 2 weeks'],
      'fortnight' => ['standard_period' => 'custom', 'custom_period' => ['type' => 'weeks', 'interval' => 2], 'interpretation' => 'Every 2 weeks'],
      'weekly' => ['standard_period' => 'week', 'custom_period' => null, 'interpretation' => 'Weekly'],
      
      // Month variations
      'bimonthly' => ['standard_period' => 'custom', 'custom_period' => ['type' => 'months', 'interval' => 2], 'interpretation' => 'Every 2 months'],
      'bi-monthly' => ['standard_period' => 'custom', 'custom_period' => ['type' => 'months', 'interval' => 2], 'interpretation' => 'Every 2 months'],
      'monthly' => ['standard_period' => 'month', 'custom_period' => null, 'interpretation' => 'Monthly'],
      
      // Quarter variations
      'quarterly' => ['standard_period' => 'quarter', 'custom_period' => null, 'interpretation' => 'Quarterly'],
      'trimester' => ['standard_period' => 'custom', 'custom_period' => ['type' => 'months', 'interval' => 3], 'interpretation' => 'Every 3 months (trimester)'],
      'tri-monthly' => ['standard_period' => 'custom', 'custom_period' => ['type' => 'months', 'interval' => 3], 'interpretation' => 'Every 3 months'],
      
      // Semester variations
      'semiannual' => ['standard_period' => 'semester', 'custom_period' => null, 'interpretation' => 'Semi-annual (every 6 months)'],
      'semi-annual' => ['standard_period' => 'semester', 'custom_period' => null, 'interpretation' => 'Semi-annual (every 6 months)'],
      'biannual' => ['standard_period' => 'semester', 'custom_period' => null, 'interpretation' => 'Bi-annual (every 6 months)'],
      'bi-annual' => ['standard_period' => 'semester', 'custom_period' => null, 'interpretation' => 'Bi-annual (every 6 months)'],
      'half-yearly' => ['standard_period' => 'semester', 'custom_period' => null, 'interpretation' => 'Half-yearly'],
      'half yearly' => ['standard_period' => 'semester', 'custom_period' => null, 'interpretation' => 'Half-yearly'],
      
      // Year variations
      'yearly' => ['standard_period' => 'year', 'custom_period' => null, 'interpretation' => 'Yearly'],
      'annual' => ['standard_period' => 'year', 'custom_period' => null, 'interpretation' => 'Annual'],
      'annually' => ['standard_period' => 'year', 'custom_period' => null, 'interpretation' => 'Annually'],
      'fiscal year' => ['standard_period' => 'year', 'custom_period' => ['type' => 'fiscal_year'], 'interpretation' => 'Fiscal year'],
      'fy' => ['standard_period' => 'year', 'custom_period' => ['type' => 'fiscal_year'], 'interpretation' => 'Fiscal year'],
      
      // Day variations
      'daily' => ['standard_period' => 'day', 'custom_period' => null, 'interpretation' => 'Daily'],
      
      // Rolling periods
      'rolling 12 months' => ['standard_period' => 'custom', 'custom_period' => ['type' => 'rolling', 'months' => 12], 'interpretation' => 'Rolling 12 months'],
      'rolling year' => ['standard_period' => 'custom', 'custom_period' => ['type' => 'rolling', 'months' => 12], 'interpretation' => 'Rolling 12 months'],
      'trailing 12 months' => ['standard_period' => 'custom', 'custom_period' => ['type' => 'rolling', 'months' => 12], 'interpretation' => 'Trailing 12 months'],
      'ttm' => ['standard_period' => 'custom', 'custom_period' => ['type' => 'rolling', 'months' => 12], 'interpretation' => 'Trailing twelve months'],
      'ytd' => ['standard_period' => 'custom', 'custom_period' => ['type' => 'ytd'], 'interpretation' => 'Year to date'],
      'year to date' => ['standard_period' => 'custom', 'custom_period' => ['type' => 'ytd'], 'interpretation' => 'Year to date'],
      'mtd' => ['standard_period' => 'custom', 'custom_period' => ['type' => 'mtd'], 'interpretation' => 'Month to date'],
      'month to date' => ['standard_period' => 'custom', 'custom_period' => ['type' => 'mtd'], 'interpretation' => 'Month to date'],
      'qtd' => ['standard_period' => 'custom', 'custom_period' => ['type' => 'qtd'], 'interpretation' => 'Quarter to date'],
      'quarter to date' => ['standard_period' => 'custom', 'custom_period' => ['type' => 'qtd'], 'interpretation' => 'Quarter to date'],
    ];
  }

  /**
   * Map unrecognized temporal period to standard period
   *
   * @param string $period The unrecognized period (will be lowercased and trimmed)
   * @return array Mapping result with structure:
   *   - recognized: bool
   *   - standard_period: string|null
   *   - custom_period: array|null
   *   - interpretation: string|null
   *   - confidence: float
   *   - needs_clarification: bool
   *   - clarification_message: string|null
   */
  public static function mapPeriod(string $period): array
  {
    $period = strtolower(trim($period));
    $mappings = self::getMappings();

    // Direct mapping
    if (isset($mappings[$period])) {
      $mapping = $mappings[$period];
      return [
        'recognized' => true,
        'standard_period' => $mapping['standard_period'],
        'custom_period' => $mapping['custom_period'],
        'interpretation' => $mapping['interpretation'],
        'confidence' => 0.95,
        'needs_clarification' => false,
        'clarification_message' => null,
      ];
    }

    // Check for "every X months/weeks/days" pattern
    if (preg_match('/every\s+(\d+)\s+(month|week|day|year)s?/i', $period, $matches)) {
      $interval = (int)$matches[1];
      $unit = strtolower($matches[2]) . 's';
      return [
        'recognized' => true,
        'standard_period' => 'custom',
        'custom_period' => ['type' => $unit, 'interval' => $interval],
        'interpretation' => "Every {$interval} {$unit}",
        'confidence' => 0.9,
        'needs_clarification' => false,
        'clarification_message' => null,
      ];
    }

    // Not recognized
    return [
      'recognized' => false,
      'standard_period' => null,
      'custom_period' => null,
      'interpretation' => null,
      'confidence' => 0.0,
      'needs_clarification' => true,
      'clarification_message' => null,
    ];
  }

  /**
   * Get interval pattern regex
   *
   * @return string Regex pattern for "every X units" detection
   */
  public static function getIntervalPattern(): string
  {
    return '/every\s+(\d+)\s+(month|week|day|year)s?/i';
  }

  /**
   * Get all standard periods
   *
   * @return array List of standard period types
   */
  public static function getStandardPeriods(): array
  {
    return ['day', 'week', 'month', 'quarter', 'semester', 'year', 'custom'];
  }

  /**
   * Get all custom period types
   *
   * @return array List of custom period types
   */
  public static function getCustomPeriodTypes(): array
  {
    $types = [];
    foreach (self::getMappings() as $mapping) {
      if ($mapping['standard_period'] === 'custom' && isset($mapping['custom_period']['type'])) {
        $types[] = $mapping['custom_period']['type'];
      }
    }
    return array_values(array_unique($types));
  }

  /**
   * Get metadata about temporal period mappings
   *
   * @return array Metadata about the pattern
   */
  public static function getMetadata(): array
  {
    return [
      'name' => 'TemporalPeriodMappingPattern',
      'description' => 'Maps temporal period variations to standard periods',
      'domain' => 'Analytics',
      'mappings_count' => count(self::getMappings()),
      'standard_periods' => self::getStandardPeriods(),
      'custom_period_types' => self::getCustomPeriodTypes(),
    ];
  }
}
