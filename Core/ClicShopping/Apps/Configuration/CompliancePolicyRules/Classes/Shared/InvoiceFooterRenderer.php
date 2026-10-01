<?php
  /**
   * Copyright (c) 2008–2026 Loic Richard
   *
   * Licensed under AGPLv3 or commercial license.
   * See LICENSE file.
   */

  namespace ClicShopping\Apps\Configuration\CompliancePolicyRules\Classes\Shared;

  use ClicShopping\OM\CLICSHOPPING;
  use ClicShopping\OM\Registry;
  use ClicShopping\Apps\Orders\Orders\Classes\Pdf\AbstractOrderPdf;

  /**
   * Picks the country block of the invoice footer.
   *
   * A country is a config module of this App (every one but CPR) whose `_STATUS` is True; its block
   * is the class InvoiceFooter\{CODE}. An invoice belongs to ONE fiscal jurisdiction: several active
   * countries is a configuration error, alerted in the admin (see conflictingCountries()).
   * See README.md of this App to add a country.
   */
  class InvoiceFooterRenderer
  {
    private const string COMMON_MODULE = 'CPR';

    /**
     * Draws the block of the active country, if any.
     *
     * @param AbstractOrderPdf $pdf The invoice PDF being rendered.
     */
    public static function render(AbstractOrderPdf $pdf): void
    {
      $country = self::countryToRender();
      $class = __NAMESPACE__ . '\\InvoiceFooter\\' . $country;

      if ($country !== null && class_exists($class)) {
        $class::render($pdf);
      }
    }

    /**
     * Country modules whose status is True, in directory order.
     *
     * @return list<string> Module codes (e.g. FRE, CAD)
     */
    public static function activeCountries(): array
    {
      $active = [];
      $dirs = glob(CLICSHOPPING::BASE_DIR . 'Apps/Configuration/CompliancePolicyRules/Module/ClicShoppingAdmin/Config/*', GLOB_ONLYDIR) ?: [];

      foreach ($dirs as $dir) {
        $code = basename($dir);
        $status = 'CLICSHOPPING_APP_COMPLIANCE_POLICY_RULES_' . $code . '_STATUS';

        if ($code !== self::COMMON_MODULE && \defined($status) && constant($status) === 'True') {
          $active[] = $code;
        }
      }

      return $active;
    }

    /**
     * @return list<string> The active countries when more than one is active, else empty
     */
    public static function conflictingCountries(): array
    {
      $active = self::activeCountries();

      return \count($active) > 1 ? $active : [];
    }

    /**
     * The single active country; on a conflict, the pre-module choice (double-taxes flag) so a
     * live invoice never changes silently while the admin is alerted.
     *
     * @return string|null Module code, or null when no country block applies
     */
    private static function countryToRender(): ?string
    {
      $active = self::activeCountries();

      if (\count($active) <= 1) {
        return $active[0] ?? null;
      }

      $legacy = Registry::get('CompliancePolicyRules')->displayDoubleTaxes() ? 'CAD' : 'FRE';

      return \in_array($legacy, $active, true) ? $legacy : null;
    }
  }
