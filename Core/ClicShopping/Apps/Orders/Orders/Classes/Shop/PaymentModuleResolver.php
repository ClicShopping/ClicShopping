<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Orders\Orders\Classes\Shop;

use ClicShopping\OM\Registry;

use function is_numeric;
use function str_contains;
use function str_replace;

/**
 * Resolves the active payment module from the session payment code and applies its
 * public title / forced order-status onto an order info array.
 *
 * This behaviour was historically inlined — byte-identically — inside {@see Order::cart()}
 * and {@see Order::Insert()}. It is extracted here as a single, side-effect-explicit seam
 * (session value in, info array out) so the two callers share one implementation and a
 * future agentic adapter can drive it without going through $_SESSION. It introduces NO
 * AI/agent dependency: it is a plain, stateless resolver.
 */
class PaymentModuleResolver
{
  private mixed $hooks;

  public function __construct()
  {
    $this->hooks = Registry::get('Hooks');
  }

  /**
   * Resolves the active payment module instance from the session payment code,
   * mirroring the historical `Payment_<Vendor>_<Module>` Registry lookup.
   *
   * Returns null in exactly the cases where the original inline block left
   * $CLICSHOPPING_PM unset: no payment selected, a code carrying no namespace
   * separator, or a module that is not registered.
   *
   * @param string|null $session_payment The raw `$_SESSION['payment']` value (or null).
   * @return object|null The payment module instance, or null when unresolved.
   */
  public function resolve(?string $session_payment): ?object
  {
    if ($session_payment === null || !str_contains($session_payment, '\\')) {
      return null;
    }

    $code = 'Payment_' . str_replace('\\', '_', $session_payment);

    return Registry::exists($code) ? Registry::get($code) : null;
  }

  /**
   * Applies the resolved module's public title and (optional) forced order status
   * onto the order info array, returning the updated array. A null module leaves
   * the info untouched — identical to the original `if (isset($CLICSHOPPING_PM))` guard.
   *
   * @param object|null $module The module returned by {@see resolve()}.
   * @param array<string, mixed> $info The order info array to enrich.
   * @return array<string, mixed> The (possibly) updated info array.
   */
  public function applyToInfo(?object $module, array $info): array
  {
    if ($module === null) {
      return $info;
    }

    // public_title when present, otherwise the internal title (isset semantics).
    $info['payment_method'] = $module->public_title ?? $module->title;

    if (isset($module->order_status) && is_numeric($module->order_status) && ($module->order_status > 0)) {
      $info['order_status'] = $module->order_status;
    }

    // Extension seam: let observers react to / augment the resolved payment info.
    $this->hooks->call('PaymentModuleResolver', 'ApplyToInfo', ['module' => $module, 'info' => $info]);

    return $info;
  }
}
