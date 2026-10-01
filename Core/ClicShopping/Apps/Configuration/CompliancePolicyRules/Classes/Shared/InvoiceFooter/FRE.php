<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\CompliancePolicyRules\Classes\Shared\InvoiceFooter;

use ClicShopping\OM\Registry;
use ClicShopping\Sites\Common\PDF;
use ClicShopping\Apps\Orders\Orders\Classes\Pdf\AbstractOrderPdf;

/**
 * French company block: capital, RCS, APE, then the EU intracom VAT number.
 * Drawn by InvoiceFooterRenderer when FRE is the active country module.
 */
class FRE
{
  /**
   * @param AbstractOrderPdf $pdf The invoice PDF being rendered
   * @return void
   */
  public static function render(AbstractOrderPdf $pdf): void
  {
    $rules = Registry::get('CompliancePolicyRules');
    $rgb = $pdf->rgb();

    $shopCapital = $rules->displayShopCapital();
    $info_societe = $shopCapital === '' ? '' : $shopCapital . ' - ';

    $pdf->SetY(-25);
    $pdf->SetFont('Arial', '', 8);
    $pdf->SetTextColor(...$rgb);
    $pdf->Cell(0, 10, PDF::enc($pdf->def('entry_info_societe', ['shop_code_capital' => $info_societe, 'shop_code_rcs' => $rules->displayRegistrationNumber(), 'shop_code_ape' => $rules->displayApeCode()])), 0, 0, 'C');

    $pdf->SetY(-20);
    $pdf->SetFont('Arial', '', 8);
    $pdf->SetTextColor(...$rgb);
    $pdf->Cell(0, 10, PDF::enc($pdf->def('entry_info_societe_next', ['tva_shop_intracom' => $rules->displayEUVatNumber()])), 0, 0, 'C');
  }
}
