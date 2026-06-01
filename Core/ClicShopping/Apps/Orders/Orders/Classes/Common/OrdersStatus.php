<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Orders\Orders\Classes\Common;

/**
 * Canonical order-status identifiers (table :table_orders_status).
 *
 * These IDs are invariants of the platform: administrators may rename the
 * status labels but cannot delete or renumber them. Any code that needs to
 * compare an order status to a business meaning must reference these
 * constants instead of hard-coding the integer.
 *
 * Companion to {@see EInvoiceService} which exposes the equivalent
 * invoice-status constants (table :table_orders_status_invoice).
 */
final class OrdersStatus
{
  public const PENDING    = 1; // En instance
  public const CANCELLED  = 2; // Annulé
  public const DELIVERED  = 3; // Livré
  public const PROCESSING = 4; // Traitement en cours

  /**
   * Statuses considered "final" — orders in these states cannot be cancelled
   * or further modified by a customer-facing endpoint.
   *
   * @return int[]
   */
  public static function finalStatuses(): array
  {
    return [self::CANCELLED, self::DELIVERED];
  }
}
