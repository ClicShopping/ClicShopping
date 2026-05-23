<?php
  /**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

  namespace ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\CockpitAI\SubRules;

  /**
   * ActionPriority
   *
   * Formal priority levels for recommended actions in the CockpitAI rules engine.
   * Numeric backing values allow direct comparison: higher int = higher urgency.
   *
   * Used by ConflictResolver to order actions when multiple rules fire simultaneously.
   * Resolution order: Critical (4) > High (3) > Medium (2) > Low (1)
   */
  enum ActionPriority: int
  {
    case Critical = 4;
    case High     = 3;
    case Medium   = 2;
    case Low      = 1;

    /**
     * Instantiate from string label (case-insensitive).
     *
     * @param string $label One of: 'critical', 'high', 'medium', 'low'
     * @throws \ValueError For unknown labels
     */
    public static function fromLabel(string $label): self
    {
      return match (strtolower(trim($label))) {
        'critical' => self::Critical,
        'high'     => self::High,
        'medium'   => self::Medium,
        'low'      => self::Low,
        default    => throw new \ValueError("Unknown ActionPriority label: '{$label}'"),
      };
    }

    /**
     * Return the canonical lowercase string label for this priority.
     */
    public function label(): string
    {
      return match ($this) {
        self::Critical => 'critical',
        self::High     => 'high',
        self::Medium   => 'medium',
        self::Low      => 'low',
      };
    }

    /**
     * Return true if this priority is strictly higher than $other.
     */
    public function isHigherThan(self $other): bool
    {
      return $this->value > $other->value;
    }
  }