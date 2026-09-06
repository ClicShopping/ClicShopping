<?php
  /**
   * Copyright (c) 2008–2026 Loic Richard
   *
   * Licensed under AGPLv3 or commercial license.
   * See LICENSE file.
   */

  namespace ClicShopping\Apps\Configuration\CompliancePolicyRules\Classes\Shared;

  use ClicShopping\OM\Registry;
  use ClicShopping\Sites\Common\PDF;
  use ClicShopping\Apps\Orders\Orders\Classes\Pdf\AbstractOrderPdf;

  /**
   * Renders the jurisdiction-specific company block of the invoice footer.
   *
   * Moved out of AbstractOrderPdf::Footer() so a country can supply its own footer
   * block through a hook without touching Core. Selects on displayDoubleTaxes()
   * (single-taxe vs federal+provincial); Lot B will replace that flag by the
   * merchant jurisdiction.
   */
  class InvoiceFooterRenderer
  {
    /**
     * Draws the company-info block at the fixed footer positions (Y -25 / -20).
     *
     * @param AbstractOrderPdf $pdf The invoice PDF being rendered.
     */
    public static function render(AbstractOrderPdf $pdf): void
    {
      $rules = Registry::get('CompliancePolicyRules');
      $rgb = $pdf->rgb();

      $shopCapital = $rules->displayShopCapital();
      $info_societe = $shopCapital === '' ? '' : $shopCapital . ' - ';

      if ($rules->displayDoubleTaxes() === false) {
        $pdf->SetY(-25);
        $pdf->SetFont('Arial', '', 8);
        $pdf->SetTextColor(...$rgb);
        $pdf->Cell(0, 10, PDF::enc($pdf->def('entry_info_societe', ['shop_code_capital' => $info_societe, 'shop_code_rcs' => $rules->displayRegistrationNumber(), 'shop_code_ape' => $rules->displayApeCode()])), 0, 0, 'C');

        $pdf->SetY(-20);
        $pdf->SetFont('Arial', '', 8);
        $pdf->SetTextColor(...$rgb);
        $pdf->Cell(0, 10, PDF::enc($pdf->def('entry_info_societe_next', ['tva_shop_intracom' => $rules->displayEUVatNumber()])), 0, 0, 'C');
      } else {
        $pdf->SetY(-25);
        $pdf->SetFont('Arial', '', 8);
        $pdf->SetTextColor(...$rgb);
        $pdf->Cell(0, 10, PDF::enc($pdf->def('entry_info_societe1', ['shop_code_capital' => $info_societe, 'shop_code_rcs' => $rules->displayRegistrationNumber(), 'shop_code_ape' => $rules->displayApeCode()])), 0, 0, 'C');

        $pdf->SetY(-20);
        $pdf->SetFont('Arial', '', 8);
        $pdf->SetTextColor(...$rgb);
        $pdf->Cell(0, 10, PDF::enc($pdf->def('entry_info_societe_next1', ['tva_shop_provincial' => $rules->displayRegionalTaxesNumber(), 'tva_shop_federal' => $rules->displayFederalTaxesNumber()])), 0, 0, 'C');
      }
    }
  }
