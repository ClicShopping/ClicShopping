<?php
  /**
   * Copyright (c) 2008–2026 Loic Richard
   *
   * Licensed under AGPLv3 or commercial license.
   * See LICENSE file.
   */

  namespace ClicShopping\Apps\Configuration\CompliancePolicyRules\Classes\Shared;

  class CompliancePolicyRules
  {
    public function construct() {}

    /**
     * Allow to display or not the double taxes
     *
     * @return bool
     */
    public function displayDoubleTaxes(): bool
    {
      $result = true;

      if (
        defined('CLICSHOPPING_APP_COMPLIANCE_POLICY_RULES_DISPLAY_DOUBLE_TAXES') &&
        CLICSHOPPING_APP_COMPLIANCE_POLICY_RULES_DISPLAY_DOUBLE_TAXES === 'False'
      ) {
        $result = false;
      }

      return $result;
    }

    /**
     * Allow to display or not the checkout conditions
     *
     * @return bool
     */
    public function displayCheckoutConditions(): bool
    {
      $result = true;

      if (
        defined('CLICSHOPPING_APP_COMPLIANCE_POLICY_RULES_DISPLAY_CONDITIONS_CHECKOUT') &&
        CLICSHOPPING_APP_COMPLIANCE_POLICY_RULES_DISPLAY_CONDITIONS_CHECKOUT === 'False'
      ) {
        $result = false;
      }

      return $result;
    }

    /**
     * Allow to display or not the privacy checkout conditions
     *
     * @return bool
     */
    public function displayPrivacyConditions(): bool
    {
      $result = true;

      if (
        defined('CLICSHOPPING_APP_COMPLIANCE_POLICY_RULES_DISPLAY_PRIVACY_CONDITIONS') &&
        CLICSHOPPING_APP_COMPLIANCE_POLICY_RULES_DISPLAY_PRIVACY_CONDITIONS === 'False'
      ) {
        $result = false;
      }

      return $result;
    }

    /**
     * Allow to display or not the shipping delay condition
     *
     * @return bool
     */
    public function displayShippingDelayCondition(): string
    {
      $result = '';

      if (
        defined('CLICSHOPPING_APP_COMPLIANCE_POLICY_RULES_DISPLAY_SHIPPING_DELAY') &&
        !empty(CLICSHOPPING_APP_COMPLIANCE_POLICY_RULES_DISPLAY_SHIPPING_DELAY)
      ) {
        $result = CLICSHOPPING_APP_COMPLIANCE_POLICY_RULES_DISPLAY_SHIPPING_DELAY;
      }

      return $result;
    }

    /**
     * Allow to display or not the order acceptance conditions
     *
     * @return bool
     */
    public function displayOrderAcceptance(): bool
    {
      $result = true;

      if (
        defined('CLICSHOPPING_APP_COMPLIANCE_POLICY_RULES_DISPLAY_ORDER_ACCEPTANCE') &&
        CLICSHOPPING_APP_COMPLIANCE_POLICY_RULES_DISPLAY_ORDER_ACCEPTANCE === 'False'
      ) {
        $result = false;
      }

      return $result;
    }

    /**
     * Return the sales conditions URL.
     *
     * @return string
     */
    public function displayUrlSalesCondition(): string
    {
      $result = '';

      if (
        defined('CLICSHOPPING_APP_COMPLIANCE_POLICY_RULES_URL_SALES_CONDITIONS') &&
        !empty(CLICSHOPPING_APP_COMPLIANCE_POLICY_RULES_URL_SALES_CONDITIONS)
      ) {
        $result = CLICSHOPPING_APP_COMPLIANCE_POLICY_RULES_URL_SALES_CONDITIONS;
      }

      return $result;
    }

    /**
     * Return the privacy conditions URL.
     *
     * @return string
     */
    public function displayUrlPrivacyCondition(): string
    {
      $result = '';

      if (
        defined('CLICSHOPPING_APP_COMPLIANCE_POLICY_RULES_URL_PRIVACY') &&
        !empty(CLICSHOPPING_APP_COMPLIANCE_POLICY_RULES_URL_PRIVACY)
      ) {
        $result = CLICSHOPPING_APP_COMPLIANCE_POLICY_RULES_URL_PRIVACY;
      }

      return $result;
    }

    /**
     * Return the shop capital.
     *
     * @return string
     */
    public function displayShopCapital(): string
    {
      $result = '';

      if (
        defined('CLICSHOPPING_APP_COMPLIANCE_POLICY_RULES_SHOP_CAPITAL') &&
        !empty(CLICSHOPPING_APP_COMPLIANCE_POLICY_RULES_SHOP_CAPITAL)
      ) {
        $result = CLICSHOPPING_APP_COMPLIANCE_POLICY_RULES_SHOP_CAPITAL;
      }

      return $result;
    }

    /**
     * Return the EU VAT number.
     *
     * @return string
     */
    public function displayEUVatNumber(): string
    {
      $result = '';

      if (
        defined('CLICSHOPPING_APP_COMPLIANCE_POLICY_RULES_EU_VAT_NUMBER') &&
        !empty(CLICSHOPPING_APP_COMPLIANCE_POLICY_RULES_EU_VAT_NUMBER)
      ) {
        $result = CLICSHOPPING_APP_COMPLIANCE_POLICY_RULES_EU_VAT_NUMBER;
      }

      return $result;
    }

    /**
     * Return the company registration number.
     *
     * @return string
     */
    public function displayRegistrationNumber(): string
    {
      $result = '';

      if (
        defined('CLICSHOPPING_APP_COMPLIANCE_POLICY_RULES_COMPANY_REGISTRATION_NUMBER') &&
        !empty(CLICSHOPPING_APP_COMPLIANCE_POLICY_RULES_COMPANY_REGISTRATION_NUMBER)
      ) {
        $result = CLICSHOPPING_APP_COMPLIANCE_POLICY_RULES_COMPANY_REGISTRATION_NUMBER;
      }

      return $result;
    }

    /**
     * Return the company registration number.
     *
     * @return string
     */
    public function displayLegalInformation(): string
    {
      $result = '';

      if (
        defined('CLICSHOPPING_APP_COMPLIANCE_POLICY_RULES_SHOP_LEGAL_INFORMATION') &&
        !empty(CLICSHOPPING_APP_COMPLIANCE_POLICY_RULES_SHOP_LEGAL_INFORMATION)
      ) {
        $result = CLICSHOPPING_APP_COMPLIANCE_POLICY_RULES_SHOP_LEGAL_INFORMATION;
      }

      return $result;
    }
  }