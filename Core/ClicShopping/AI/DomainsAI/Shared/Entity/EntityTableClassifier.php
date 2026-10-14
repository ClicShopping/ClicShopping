<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\DomainsAI\Shared\Entity;

/**
 * EntityTableClassifier Class
 *
 * Sorts embedding tables into three natures, from the schema alone (no domain vocabulary):
 * - ENTITY: the store of a business table that owns its id column ('products' → products_id)
 * - SATELLITE: a store whose entity_id points at ANOTHER entity ('products_seo' → products);
 *   it inherits its target's type instead of publishing one of its own
 * - SYSTEM: a store related to no entity (conversation memory, retention log)
 *
 * @package ClicShopping\AI\DomainsAI\Shared\Entity
 */
class EntityTableClassifier
{
  public const ENTITY = 'entity';
  public const SATELLITE = 'satellite';
  public const SYSTEM = 'system';

  /**
   * Classify embedding tables by nature
   *
   * @param array<int, string> $tables Full table names (with prefix)
   * @param string $prefix Database table prefix
   * @param callable(string): array<int, string> $columnsOf Columns of a table ([] when absent)
   * @param callable(string): ?string $idColumnFor Expected id column of an entity name
   * @return array<string, array{kind: string, entity_type: string|null}> Keyed by table name
   */
  public static function classify(array $tables, string $prefix, callable $columnsOf, callable $idColumnFor): array
  {
    $derived = [];

    foreach ($tables as $table) {
      $derived[$table] = self::deriveName($table, $prefix);
    }

    $classification = [];
    $entityTypes = [];

    // Pass 1 — a table owning its id column is an entity, and seeds the vocabulary the
    // satellites resolve against. A bare 'id' identifies nothing, so it never qualifies.
    foreach ($derived as $table => $name) {
      $idColumn = $idColumnFor($name);

      if ($name !== '' && $idColumn !== null && $idColumn !== 'id'
        && in_array($idColumn, $columnsOf($prefix . $name), true)) {
        $classification[$table] = ['kind' => self::ENTITY, 'entity_type' => $name];
        $entityTypes[$name] = $idColumn;
      }
    }

    // Pass 2 — the rest inherits from an entity, by name then by foreign key.
    foreach ($derived as $table => $name) {
      if (isset($classification[$table])) {
        continue;
      }

      $parent = self::parentFromName($name, array_keys($entityTypes))
        ?? self::parentFromForeignKey($columnsOf($prefix . $name), $entityTypes);

      $classification[$table] = $parent === null
        ? ['kind' => self::SYSTEM, 'entity_type' => null]
        : ['kind' => self::SATELLITE, 'entity_type' => $parent];
    }

    return $classification;
  }

  /**
   * Strip prefix and '_embedding' suffix to get the name a table declares
   *
   * @param string $table Full table name
   * @param string $prefix Database table prefix
   * @return string
   */
  private static function deriveName(string $table, string $prefix): string
  {
    if ($prefix !== '' && str_starts_with($table, $prefix)) {
      $table = substr($table, strlen($prefix));
    }

    if (str_ends_with($table, '_embedding')) {
      $table = substr($table, 0, -strlen('_embedding'));
    }

    return $table;
  }

  /**
   * Longest entity type the name extends ('products_seo' → 'products')
   *
   * @param string $name Derived table name
   * @param array<int, string> $entityTypes Known entity types
   * @return string|null
   */
  private static function parentFromName(string $name, array $entityTypes): ?string
  {
    $parent = null;

    foreach ($entityTypes as $entityType) {
      if (str_starts_with($name, $entityType . '_')
        && ($parent === null || strlen($entityType) > strlen($parent))) {
        $parent = $entityType;
      }
    }

    return $parent;
  }

  /**
   * First entity id column the table carries — the only link left when the name says nothing
   *
   * @param array<int, string> $columns Columns of the business table
   * @param array<string, string> $entityTypes Entity type => its id column
   * @return string|null
   */
  private static function parentFromForeignKey(array $columns, array $entityTypes): ?string
  {
    $idColumnToType = array_flip($entityTypes);

    foreach ($columns as $column) {
      if (isset($idColumnToType[$column])) {
        return $idColumnToType[$column];
      }
    }

    return null;
  }
}
