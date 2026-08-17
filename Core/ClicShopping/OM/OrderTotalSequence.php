<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\OM;

/**
 * Fiscal sequencing of the order total chain.
 *
 * A module declares the FAMILY it belongs to. For the two families that can sit on either side of
 * the tax — reduction and charge — the module also ships a default fiscal position, and the
 * administrator may override it through the module's own `tax_position` parameter. The platform
 * derives from that the rank at which the module is computed, instead of appending it to the end of
 * MODULE_ORDER_TOTAL_INSTALLED.
 *
 * The family declaration is mandatory. A module that declares nothing usable is REFUSED at install:
 * an unplaced module ends up computing after the grand total, prints its line, is not counted, and
 * nothing anywhere says so.
 *
 * This is not sort_order. sort_order governs the PRINTED line and stays the merchant's to edit;
 * the ranks below govern the order of CALCULATION only.
 */
final class OrderTotalSequence
{
  public const ROLE_BASE = 'base';
  public const ROLE_REDUCTION = 'reduction';
  public const ROLE_CHARGE = 'charge';
  public const ROLE_TAX = 'tax';
  public const ROLE_TOTAL = 'total';

  public const POSITION_BEFORE_TAX = 'before_tax';
  public const POSITION_AFTER_TAX = 'after_tax';

  // Lower rank computes first. The grand total is terminal by construction, which is what makes
  // "a line that shows without being counted" unreachable.
  private const RANKS = [
    self::ROLE_BASE => 100,
    self::ROLE_REDUCTION . '|' . self::POSITION_BEFORE_TAX => 200,
    self::ROLE_CHARGE . '|' . self::POSITION_BEFORE_TAX => 300,
    self::ROLE_TAX => 400,
    self::ROLE_REDUCTION . '|' . self::POSITION_AFTER_TAX => 500,
    self::ROLE_CHARGE . '|' . self::POSITION_AFTER_TAX => 510,
    self::ROLE_TOTAL => 900,
  ];

  // Only these two families carry a fiscal position: the base opens the sequence, the tax IS the
  // pivot, the total closes it. None of the three can be moved.
  private const POSITIONED_ROLES = [self::ROLE_REDUCTION, self::ROLE_CHARGE];

  public static function positions(): array
  {
    return [self::POSITION_BEFORE_TAX, self::POSITION_AFTER_TAX];
  }

  /**
   * Resolve the rank key a module class declares, or null when the declaration is absent, not a
   * string, names an unknown family, or resolves to a position the platform does not know.
   *
   * Position precedence: the explicit override (a value being saved this request) beats the
   * administrator's stored choice, which beats the default the module ships.
   */
  public static function declarationOfClass(string $class, ?string $positionOverride = null): ?string
  {
    if (!class_exists($class)) {
      return null;
    }

    $defaults = (new \ReflectionClass($class))->getDefaultProperties();

    $role = $defaults['moduletype'] ?? null;

    if (!\is_string($role)) {
      return null;
    }

    if (!\in_array($role, self::POSITIONED_ROLES, true)) {
      return isset(self::RANKS[$role]) ? $role : null;
    }

    $position = self::validPosition($positionOverride)
      ?? self::chosenPosition($defaults)
      ?? self::validPosition($defaults['moduletype_position'] ?? null);

    $key = $role . '|' . ($position ?? '');

    return isset(self::RANKS[$key]) ? $key : null;
  }

  public static function rankOfClass(string $class, ?string $positionOverride = null): ?int
  {
    $declaration = self::declarationOfClass($class, $positionOverride);

    return $declaration === null ? null : self::RANKS[$declaration];
  }

  /**
   * Rank of a chain entry, given as 'Vendor\App\Code'.
   */
  public static function rank(string $module, ?string $positionOverride = null): ?int
  {
    $class = Apps::getModuleClass($module, 'OrderTotal');

    return \is_string($class) ? self::rankOfClass($class, $positionOverride) : null;
  }

  /**
   * The configuration constant through which the administrator chooses this module's fiscal
   * position, or null when the module has no such choice to offer.
   */
  public static function positionKeyOf(string $module): ?string
  {
    $class = Apps::getModuleClass($module, 'OrderTotal');

    if (!\is_string($class) || !class_exists($class)) {
      return null;
    }

    $defaults = (new \ReflectionClass($class))->getDefaultProperties();

    if (!\in_array($defaults['moduletype'] ?? null, self::POSITIONED_ROLES, true)) {
      return null;
    }

    $key = $defaults['moduletype_position_key'] ?? null;

    return \is_string($key) && $key !== '' ? $key : null;
  }

  /**
   * Insert a module in the chain at the rank its declaration commands.
   *
   * Returns null when the module declares nothing usable — the caller MUST then refuse the whole
   * installation and say why, rather than write a chain it cannot vouch for.
   *
   * @param array<int, string> $chain
   * @return array<int, string>|null
   */
  public static function place(array $chain, string $module, ?string $positionOverride = null): ?array
  {
    $rank = self::rank($module, $positionOverride);

    if ($rank === null) {
      return null;
    }

    $chain = self::normalise($chain, $module);

    foreach ($chain as $position => $installed) {
      $installedRank = self::rank($installed);

      // An undeclared neighbour keeps its place: only declared entries position the newcomer.
      if ($installedRank !== null && $installedRank > $rank) {
        array_splice($chain, $position, 0, [$module]);

        return $chain;
      }
    }

    $chain[] = $module;

    return $chain;
  }

  /**
   * Move an ALREADY INSTALLED module to the rank a newly chosen position commands. This is what
   * makes the administrator's choice real: saving a position that does not move the module in the
   * chain would be a screen offering a choice it never honours.
   *
   * Returns null when the module is not installed, or declares nothing usable — the caller then
   * leaves the chain untouched.
   *
   * @param array<int, string> $chain
   * @return array<int, string>|null
   */
  public static function reposition(array $chain, string $module, ?string $position): ?array
  {
    if (!\in_array($module, self::normalise($chain), true)) {
      return null;
    }

    return self::place($chain, $module, $position);
  }

  /**
   * Read-only diagnostic: the chain entries whose stored order contradicts their declared rank.
   * Existing shops are never reordered behind the merchant's back, so this is how a sequence
   * inherited from an earlier version becomes visible instead of staying silently wrong.
   *
   * @param array<int, string> $chain
   * @return array<int, array{module: string, rank: int|null, after: string}>
   */
  public static function misplaced(array $chain): array
  {
    $entries = self::normalise($chain);
    $result = [];
    $highest = null;
    $highestModule = '';

    foreach ($entries as $module) {
      $rank = self::rank($module);

      if ($rank === null) {
        continue;
      }

      if ($highest !== null && $rank < $highest) {
        $result[] = ['module' => $module, 'rank' => $rank, 'after' => $highestModule];

        continue;
      }

      $highest = $rank;
      $highestModule = $module;
    }

    return $result;
  }

  /**
   * @param array<int|string, string> $chain
   * @return array<int, string>
   */
  private static function normalise(array $chain, ?string $exclude = null): array
  {
    return array_values(array_filter(
      array_map('trim', $chain),
      static fn(string $entry): bool => $entry !== '' && $entry !== $exclude
    ));
  }

  /**
   * The administrator's stored fiscal position, or null when they have not chosen one — which is
   * also the case at install time, since the row is written in the request that reads it and the
   * constants come from the bootstrap. The module's declared default then applies.
   *
   * @param array<string, mixed> $defaults
   */
  private static function chosenPosition(array $defaults): ?string
  {
    $key = $defaults['moduletype_position_key'] ?? null;

    if (!\is_string($key) || $key === '' || !\defined($key)) {
      return null;
    }

    return self::validPosition(\constant($key));
  }

  private static function validPosition(mixed $position): ?string
  {
    return \in_array($position, self::positions(), true) ? $position : null;
  }
}
