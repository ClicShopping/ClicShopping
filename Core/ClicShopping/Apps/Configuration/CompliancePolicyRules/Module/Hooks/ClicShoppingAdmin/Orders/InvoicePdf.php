<?php
  /**
   * Copyright (c) 2008–2026 Loic Richard
   *
   * Licensed under AGPLv3 or commercial license.
   * See LICENSE file.
   */

  namespace ClicShopping\Apps\Configuration\CompliancePolicyRules\Module\Hooks\ClicShoppingAdmin\Orders;

  use ClicShopping\OM\Interfaces\HooksInterface;
  use ClicShopping\Apps\Configuration\CompliancePolicyRules\Classes\Shared\InvoiceFooterRenderer;
  use ClicShopping\Apps\Orders\Orders\Classes\Pdf\AbstractOrderPdf;

  /**
   * Supplies the CompliancePolicyRules company block to the invoice footer.
   * Self-guarded on the CPR status so a shop without it prints no block.
   */
  class InvoicePdf implements HooksInterface
  {
    /**
     * Renders the footer company block. Called by AbstractOrderPdf::Footer().
     *
     * @param array $params Expects 'pdf' => the AbstractOrderPdf instance.
     */
    public function renderFooter(array $params): void
    {
      if (!\defined('CLICSHOPPING_APP_COMPLIANCE_POLICY_RULES_STATUS')
        || trim(CLICSHOPPING_APP_COMPLIANCE_POLICY_RULES_STATUS) === '') {
        return;
      }

      $pdf = $params['pdf'] ?? null;

      if ($pdf instanceof AbstractOrderPdf) {
        InvoiceFooterRenderer::render($pdf);
      }
    }
  }
