<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Orders\Orders\Classes\Pdf;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTTP;
use ClicShopping\OM\Registry;
use Closure;
use FPDF;

/**
 * Shared FPDF base for ClicShopping order documents (invoice, packing slip,
 * and their batch variants). Centralises the page Header(), Footer(),
 * table headings and vector-graphic helpers that were previously duplicated
 * across the front-office invoice class and the
 * admin templates.
 *
 * The class is context-agnostic: the caller injects a definition resolver
 * (shop uses CLICSHOPPING::getDef, admin uses $app->getDef) and a logo URL.
 */
abstract class AbstractOrderPdf extends FPDF
{
  protected Closure $defResolver;
  protected string|false $logoUrl = false;
  protected bool $renderHeader = true;
  protected bool $renderFooter = true;

  public function setDefResolver(Closure $resolver): void
  {
    $this->defResolver = $resolver;
  }

  public function setLogoUrl(string|false $url): void
  {
    $this->logoUrl = $url;
  }

  public function setRenderHeader(bool $on): void
  {
    $this->renderHeader = $on;
  }

  public function setRenderFooter(bool $on): void
  {
    $this->renderFooter = $on;
  }

  protected function def(string $key, ?array $values = null): string
  {
    if (!isset($this->defResolver)) {
      return CLICSHOPPING::getDef($key, $values);
    }

    return ($this->defResolver)($key, $values);
  }

  protected function rgb(): array
  {
    return explode(',', INVOICE_RGB);
  }

  /**
   * Draws a rectangle with rounded corners.
   */
  public function roundedRect(float $x, float $y, float $w, float $h, float $r, string $style = ''): void
  {
    $k = $this->k;
    $hp = $this->h;

    if ($style == 'F') {
      $op = 'f';
    } elseif ($style == 'FD' || $style == 'DF') {
      $op = 'B';
    } else {
      $op = 'S';
    }

    $MyArc = 4 / 3 * (sqrt(2) - 1);
    $this->_out(sprintf('%.2f %.2f m', ($x + $r) * $k, ($hp - $y) * $k));
    $xc = $x + $w - $r;
    $yc = $y + $r;
    $this->_out(sprintf('%.2f %.2f l', $xc * $k, ($hp - $y) * $k));
    $this->_Arc($xc + $r * $MyArc, $yc - $r, $xc + $r, $yc - $r * $MyArc, $xc + $r, $yc);
    $xc = $x + $w - $r;
    $yc = $y + $h - $r;
    $this->_out(sprintf('%.2f %.2f l', ($x + $w) * $k, ($hp - $yc) * $k));
    $this->_Arc($xc + $r, $yc + $r * $MyArc, $xc + $r * $MyArc, $yc + $r, $xc, $yc + $r);
    $xc = $x + $r;
    $yc = $y + $h - $r;
    $this->_out(sprintf('%.2f %.2f l', $xc * $k, ($hp - ($y + $h)) * $k));
    $this->_Arc($xc - $r * $MyArc, $yc + $r, $xc - $r, $yc + $r * $MyArc, $xc - $r, $yc);
    $xc = $x + $r;
    $yc = $y + $r;
    $this->_out(sprintf('%.2f %.2f l', ($x) * $k, ($hp - $yc) * $k));
    $this->_Arc($xc - $r, $yc - $r * $MyArc, $xc - $r * $MyArc, $yc - $r, $xc, $yc - $r);
    $this->_out($op);
  }

  public function _Arc(float $x1, float $y1, float $x2, float $y2, float $x3, float $y3): void
  {
    $h = $this->h;
    $this->_out(sprintf('%.2f %.2f %.2f %.2f %.2f %.2f c ', $x1 * $this->k, ($h - $y1) * $this->k,
      $x2 * $this->k, ($h - $y2) * $this->k, $x3 * $this->k, ($h - $y3) * $this->k));
  }

  /**
   * Page header: logo + store name/address/email/website.
   */
  public function Header(): void
  {
    if (!$this->renderHeader) {
      return;
    }

    if ($this->logoUrl !== false && $this->logoUrl !== '') {
      $this->Image($this->logoUrl, 5, 10, 50);
    }

    $rgb = $this->rgb();

    $this->SetX(0);
    $this->SetY(10);
    $this->SetFont('Arial', 'B', 10);
    $this->SetTextColor(...$rgb);
    $this->Ln(0);
    $this->Cell(125);
    $this->MultiCell(100, 3.5, mb_convert_encoding(STORE_NAME, 'ISO-8859-1', 'UTF-8'), 0, 'L');

    $this->SetX(0);
    $this->SetY(15);
    $this->SetFont('Arial', '', 8);
    $this->SetTextColor(...$rgb);
    $this->Ln(0);
    $this->Cell(125);
    $this->MultiCell(100, 3.5, mb_convert_encoding(STORE_NAME_ADDRESS, 'ISO-8859-1', 'UTF-8'), 0, 'L');

    $this->SetX(0);
    $this->SetY(30);
    $this->SetFont('Arial', '', 8);
    $this->SetTextColor(...$rgb);
    $this->Ln(0);
    $this->Cell(-3);
    $this->MultiCell(100, 3.5, mb_convert_encoding($this->def('entry_email'), 'ISO-8859-1', 'UTF-8') . ' ' . STORE_OWNER_EMAIL_ADDRESS, 0, 'L');

    $this->SetX(0);
    $this->SetY(34);
    $this->SetFont('Arial', '', 8);
    $this->SetTextColor(...$rgb);
    $this->Ln(0);
    $this->Cell(-3);
    $this->MultiCell(100, 3.5, $this->def('entry_http_site') . ' ' . CLICSHOPPING::getConfig('http_server', 'Shop'), 0, 'L');
  }

  /**
   * Page footer: thanks + legal mentions + company info.
   */
  public function Footer(): void
  {
    $CLICSHOPPING_CompliancePolicyRules = Registry::get('CompliancePolicyRules');

    if (!$this->renderFooter) {
      return;
    }

    $rgb = $this->rgb();

    $this->SetY(-55);
    $this->SetFont('Arial', 'B', 8);
    $this->SetTextColor(...$rgb);
    $this->Cell(0, 10, mb_convert_encoding($this->def('thank_you_customer'), 'ISO-8859-1', 'UTF-8'), 0, 0, 'C');

    $this->SetY(-45);
    $this->SetFont('Arial', '', 7);
    $this->SetTextColor(...$rgb);
    $this->Cell(0, 10, mb_convert_encoding($this->def('reserve_propriete'), 'ISO-8859-1', 'UTF-8'), 0, 0, 'C');

    $this->SetY(-40);
    $this->SetFont('Arial', '', 7);
    $this->SetTextColor(...$rgb);
    $this->Cell(0, 10, mb_convert_encoding($this->def('reserve_propriete_next'), 'ISO-8859-1', 'UTF-8'), 0, 0, 'C');

    $this->SetY(-35);
    $this->SetFont('Arial', '', 7);
    $this->SetTextColor(...$rgb);
    $this->Cell(0, 10, mb_convert_encoding($this->def('reserve_propriete_next1', ['sell_conditions_url' => HTTP::getShopUrlDomain() . ' ' . CLICSHOPPING_APP_COMPLIANCE_POLICY_RULES_URL_SALES_CONDITIONS]), 'ISO-8859-1', 'UTF-8'), 0, 0, 'C');

    $info_societe = '';
    if (!empty(CLICSHOPPING_APP_COMPLIANCE_POLICY_RULES_SHOP_CAPITAL)) {
      $info_societe = CLICSHOPPING_APP_COMPLIANCE_POLICY_RULES_SHOP_CAPITAL . ' - ';
    }

    if ($CLICSHOPPING_CompliancePolicyRules->displayDoubleTaxes() === false) {
      $this->SetY(-25);
      $this->SetFont('Arial', '', 8);
      $this->SetTextColor(...$rgb);
      $this->Cell(0, 10, mb_convert_encoding($this->def('entry_info_societe', ['shop_code_capital' => $info_societe, 'shop_code_rcs' => CLICSHOPPING_APP_COMPLIANCE_POLICY_RULES_COMPANY_REGISTRATION_NUMBER, 'shop_code_ape' => CLICSHOPPING_APP_COMPLIANCE_POLICY_RULES_FRE_APE_CODE]), 'ISO-8859-1', 'UTF-8'), 0, 0, 'C');

      $this->SetY(-20);
      $this->SetFont('Arial', '', 8);
      $this->SetTextColor(...$rgb);
      $this->Cell(0, 10, mb_convert_encoding($this->def('entry_info_societe_next', ['tva_shop_intracom' => CLICSHOPPING_APP_COMPLIANCE_POLICY_RULES_EU_VAT_NUMBER]), 'ISO-8859-1', 'UTF-8'), 0, 0, 'C');
    } else {
      $this->SetY(-25);
      $this->SetFont('Arial', '', 8);
      $this->SetTextColor(...$rgb);
      $this->Cell(0, 10, mb_convert_encoding($this->def('entry_info_societe1', ['shop_code_capital' => $info_societe, 'shop_code_rcs' => CLICSHOPPING_APP_COMPLIANCE_POLICY_RULES_COMPANY_REGISTRATION_NUMBER, 'shop_code_ape' => CLICSHOPPING_APP_COMPLIANCE_POLICY_RULES_FRE_APE_CODE]), 'ISO-8859-1', 'UTF-8'), 0, 0, 'C');

      $this->SetY(-20);
      $this->SetFont('Arial', '', 8);
      $this->SetTextColor(...$rgb);
      $this->Cell(0, 10, mb_convert_encoding($this->def('entry_info_societe_next1', ['tva_shop_provincial' => CLICSHOPPING_APP_COMPLIANCE_POLICY_RULES_CAD_REGIONAL_TAXES_NUMBER, 'tva_shop_federal' => CLICSHOPPING_APP_COMPLIANCE_POLICY_RULES_CAD_FEDERAL_TAXES_NUMBER]), 'ISO-8859-1', 'UTF-8'), 0, 0, 'C');
    }

    $this->SetY(-15);
    $this->SetFont('Arial', '', 8);
    $this->SetTextColor(...$rgb);
    $this->Cell(0, 10, mb_convert_encoding(CLICSHOPPING_APP_COMPLIANCE_POLICY_RULES_SHOP_LEGAL_INFORMATION, 'ISO-8859-1', 'UTF-8'), 0, 0, 'C');
  }

  /**
   * Products-table heading row used by invoice variants.
   */
  public function outputTableHeadingPdf(float $y): void
  {
    $this->SetFillColor(245);
    $this->SetFont('Arial', 'B', 8);
    $this->SetY($y);
    $this->SetX(6);
    $this->Cell(9, 6, mb_convert_encoding($this->def('table_heading_qte'), 'ISO-8859-1', 'UTF-8'), 1, 0, 'C', 1);
    $this->SetX(15);
    $this->Cell(27, 6, mb_convert_encoding($this->def('table_heading_products_model'), 'ISO-8859-1', 'UTF-8'), 1, 0, 'C', 1);
    $this->SetX(40);
    $this->Cell(103, 6, $this->def('table_heading_products'), 1, 0, 'C', 1);
    $this->SetX(143);
    $this->Cell(15, 6, $this->def('table_heading_tax'), 1, 0, 'C', 1);
    $this->SetX(158);
    $this->Cell(20, 6, $this->def('table_heading_price_excluding_tax'), 1, 0, 'C', 1);
    $this->SetX(178);
    $this->Cell(20, 6, $this->def('table_heading_total_excluding_tax'), 1, 0, 'C', 1);
    $this->Ln();
  }

  /**
   * Products-table heading row used by packing-slip variants.
   */
  public function outputTableHeadingPackingslip(float $y): void
  {
    $this->SetFillColor(245);
    $this->SetFont('Arial', 'B', 8);
    $this->SetY($y);
    $this->SetX(6);
    $this->Cell(14, 6, mb_convert_encoding($this->def('table_heading_qte'), 'ISO-8859-1', 'UTF-8'), 1, 0, 'C', 1);
    $this->SetX(20);
    $this->Cell(40, 6, mb_convert_encoding($this->def('table_heading_products_model'), 'ISO-8859-1', 'UTF-8'), 1, 0, 'C', 1);
    $this->SetX(60);
    $this->Cell(138, 6, $this->def('table_heading_products'), 1, 0, 'C', 1);
    $this->Ln();
  }
}
