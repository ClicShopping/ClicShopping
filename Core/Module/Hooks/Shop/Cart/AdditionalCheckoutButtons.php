<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\OM\Module\Hooks\Shop\Cart;

use ClicShopping\OM\Registry;
use ClicShopping\Sites\Shop\Payment;

class AdditionalCheckoutButtons
{

  /**
   * Executes the initialization of the payment method during the checkout process.
   *
   * @return string Returns a concatenated string representation of the payment initialization methods.
   */
  public function execute()
  {

    if (!Registry::exists('Payment')) {
      Registry::set('Payment', new Payment());
    }

    $CLICSHOPPING_Payment = Registry::get('Payment');

    if (isset($CLICSHOPPING_Payment)) {
      return implode('', $CLICSHOPPING_Payment->checkout_initialization_method());
    }
  }
}
