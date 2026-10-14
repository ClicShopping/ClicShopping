<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\DomainsAI\Shared\Entity;

/**
 * DocumentEntityResolver Class
 *
 * Decides the entity type of a retrieved document from its metadata. The store it came from is
 * schema-derived, so it outranks the 'entity_type' label — written by whichever subsystem
 * produced the document, in whichever form it favours.
 *
 * @package ClicShopping\AI\DomainsAI\Shared\Entity
 */
class DocumentEntityResolver
{
  /**
   * Resolve the entity type a document belongs to
   *
   * The source table only wins when it derives a KNOWN entity type: a conversation-memory or
   * correction-pattern store types nothing, and its raw name is no entity nature. There, the
   * entity the document is ABOUT survives only in the label its producer wrote.
   *
   * @param array $metadata Document metadata
   * @param callable(string): string $typeFromTable Entity type derived from a table name
   * @param callable(string): bool $isKnownType Whether a type belongs to the canonical vocabulary
   * @return string|null Entity type, or null when the metadata carries none
   */
  public static function resolveEntityType(array $metadata, callable $typeFromTable, callable $isKnownType): ?string
  {
    $sourceTable = (string)($metadata['source_table'] ?? '');

    if ($sourceTable !== '') {
      $derived = $typeFromTable($sourceTable);

      if ($derived !== '' && $isKnownType($derived)) {
        return $derived;
      }
    }

    foreach (['entity_type', 'type'] as $key) {
      $label = (string)($metadata[$key] ?? '');

      if ($label !== '') {
        return $label;
      }
    }

    return null;
  }
}
