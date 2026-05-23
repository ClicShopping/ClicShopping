<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Sites\Common;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;
use function in_array;

/**
 * Determines if a given payment is disallowed for the current customer group.
 *
 * This function evaluates whether the specified payment method is restricted
 * for the current user, depending on their customer group, and whether they
 * are logged in. It checks restrictions configured for the customer group.
 *
 * @param string $pay_check The payment method to check.
 * @return bool True if the payment method is disallowed, false otherwise.
 */

class B2BCommon
{
  /**
   * Checks if a specific payment method is not allowed for the current customer group or context.
   *
   * @param string $pay_check The payment method to check against the restrictions.
   * @return bool Returns true if the payment method is not allowed, otherwise false.
   */

  public static function getPaymentUnallowed(string $pay_check): bool
  {

    $CLICSHOPPING_Customer = Registry::get('Customer');
    $CLICSHOPPING_Db = Registry::get('Db');

    if (CLICSHOPPING::getSite() != 'ClicShoppingAdmin') {

      $customer_group_id = $CLICSHOPPING_Customer->getCustomersGroupID();

      if (($CLICSHOPPING_Customer->isLoggedOn()) && ($customer_group_id != 0)) {

        $QpaymentsNotAllowed = $CLICSHOPPING_Db->prepare('select group_payment_unallowed
                                                            from :table_customers_groups
                                                            where customers_group_id = :customers_group_id
                                                          ');
        $QpaymentsNotAllowed->bindInt(':customers_group_id', (int)$customer_group_id);
        $QpaymentsNotAllowed->execute();

        $payments_not_allowed = $QpaymentsNotAllowed->fetch();

        $payments_unallowed = explode(",", $payments_not_allowed['group_payment_unallowed']);
        $clearance = (in_array($pay_check, $payments_unallowed)) ? true : false;

      } elseif ($CLICSHOPPING_Customer->isLoggedOn()) {
        $clearance = true;
      } else {
        $clearance = false;
      }

      return $clearance;
    } else {

      $Qpayments = $CLICSHOPPING_Db->prepare('select group_payment_unallowed
                                          from :table_customers_groups
                                       ');

      $Qpayments->execute();

      $payments_not_allowed = $Qpayments->fetch();
      $payments_unallowed = explode(",", $payments_not_allowed['group_payment_unallowed']);
      $clearance = (!in_array($pay_check, $payments_unallowed)) ? true : false;

      return $clearance;
    }
  }

  /**
   * Retrieves the customer group ID associated with the current customer.
   *
   * @return int The customer group ID of the currently logged-in customer.
   */
  public static function getPaymentNotDisplayPayment(): int
  {
    $CLICSHOPPING_Customer = Registry::get('Customer');
    $customer_group_id = $CLICSHOPPING_Customer->getID();

    return $customer_group_id;
  }

  /**
   * Determines whether a shipping method is not allowed for the current customer group or context.
   *
   * @param string $shipping_check The shipping method to be checked.
   * @return bool Returns true if the shipping method is not allowed; otherwise, returns false.
   */
  public static function getShippingUnallowed(string $shipping_check): bool
  {
    $CLICSHOPPING_Customer = Registry::get('Customer');
    $CLICSHOPPING_Db = Registry::get('Db');

    if (CLICSHOPPING::getSite() != 'ClicShoppingAdmin') {
      $customer_group_id = $CLICSHOPPING_Customer->getCustomersGroupID();

      if ($CLICSHOPPING_Customer->isLoggedOn() && $customer_group_id != 0) {
        $QshippingNotAllowed = $CLICSHOPPING_Db->prepare('select group_shipping_unallowed
                                                            from :table_customers_groups
                                                            where customers_group_id = :customers_group_id
                                                          ');
        $QshippingNotAllowed->bindInt(':customers_group_id', $customer_group_id);
        $QshippingNotAllowed->execute();

        $shipping_not_allowed = $QshippingNotAllowed->fetch();

        $shipping_unallowed = explode(",", $shipping_not_allowed['group_shipping_unallowed']);
        $shipping_clearance = (in_array($shipping_check, $shipping_unallowed)) ? true : false;
      } elseif ($CLICSHOPPING_Customer->isLoggedOn()) {
        $shipping_clearance = true;
      } else {
        $shipping_clearance = false;
      }

      return $shipping_clearance;

    } else {
      $Qshipping = $CLICSHOPPING_Db->prepare('select group_shipping_unallowed
                                                from :table_customers_groups
                                               ');

      $Qshipping->execute();

      $shipping_not_allowed = $Qshipping->fetch();

      if (!empty($shipping_not_allowed['group_payment_unallowed'])) {
        $shipping_unallowed = explode(',', $shipping_not_allowed['group_payment_unallowed']);

        $shipping_clearance = (!in_array($shipping_check, $shipping_unallowed)) ? true : false;
      } else {
        $shipping_clearance = false;
      }

      return $shipping_clearance;
    }
  }


  /**
   * Determines if tax is unallowed for a specific customer group.
   *
   * @return bool Returns true if tax is unallowed, otherwise false.
   */
  public static function getTaxUnallowed(): bool
  {
    $CLICSHOPPING_Customer = Registry::get('Customer');
    $CLICSHOPPING_Db = Registry::get('Db');

    if (CLICSHOPPING::getSite() != 'ClicShoppingAdmin') {
      $customer_group_id = $CLICSHOPPING_Customer->getCustomersGroupID();

      if (($CLICSHOPPING_Customer->isLoggedOn()) && ($customer_group_id != 0)) {
        $Qtaxb2b = $CLICSHOPPING_Db->prepare('select group_order_taxe
                                                from :table_customers_groups
                                                where customers_group_id = :customers_group_id
                                              ');
        $Qtaxb2b->bindInt(':customers_group_id', $customer_group_id);
        $Qtaxb2b->execute();

        if ($Qtaxb2b->valueInt('group_order_taxe') == 1) {
          $tax_clearance = false;
        } else {
          $tax_clearance = true;
        }
      } else {
        $tax_clearance = true;
      }

      return $tax_clearance;
    } else {
      $tax_clearance = true;

      return $tax_clearance;
    }
  }
}