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
use ClicShopping\Sites\Common\PDF;

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

  /**
   * Absolute SHOP url for a setting that holds either a full url or a ClicShopping route
   * (`Info&Content&pagesId=4` — the shipped default). The document is printed from the back office,
   * so the shop domain is forced: a relative link on a PDF points nowhere.
   *
   * @param string $routeOrUrl
   * @return string  empty when the setting is empty — better a missing address than a bare domain
   */
  private static function shopUrl(string $routeOrUrl): string
  {
    $routeOrUrl = trim($routeOrUrl);

    if ($routeOrUrl === '' || str_starts_with($routeOrUrl, 'http://') || str_starts_with($routeOrUrl, 'https://')) {
      return $routeOrUrl;
    }

    return HTTP::getShopUrlDomain() . 'index.php?' . ltrim($routeOrUrl, '?&');
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
   * Invoice number: date + reference + order id, e.g. 09/02/2026S12.
   *
   * The date format is a merchant setting, NOT the language definition `date_format`: that one is
   * a display setting, and an invoice number must not differ with the language it is read in.
   *
   * @param string $invoiceDate Raw invoice date
   * @param int $oID Order id
   * @return string
   */
  protected function invoiceNumber(string $invoiceDate, int $oID): string
  {
    $rules = Registry::get('CompliancePolicyRules');
    $timestamp = strtotime($invoiceDate);
    $date = $timestamp === false ? '' : date($rules->displayInvoiceNumberFormat(), $timestamp);

    return $date . $rules->displayInvoiceNumberReference() . $oID;
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
    $this->MultiCell(100, 3.5, PDF::enc(STORE_NAME), 0, 'L');

    $this->SetX(0);
    $this->SetY(15);
    $this->SetFont('Arial', '', 8);
    $this->SetTextColor(...$rgb);
    $this->Ln(0);
    $this->Cell(125);
    $this->MultiCell(100, 3.5, PDF::enc(STORE_NAME_ADDRESS), 0, 'L');

    $this->SetX(0);
    $this->SetY(30);
    $this->SetFont('Arial', '', 8);
    $this->SetTextColor(...$rgb);
    $this->Ln(0);
    $this->Cell(-3);
    $this->MultiCell(100, 3.5, PDF::enc($this->def('entry_email')) . ' ' . STORE_OWNER_EMAIL_ADDRESS, 0, 'L');

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
    $this->Cell(0, 10, PDF::enc($this->def('thank_you_customer')), 0, 0, 'C');

    $this->SetY(-45);
    $this->SetFont('Arial', '', 7);
    $this->SetTextColor(...$rgb);
    $this->Cell(0, 10, PDF::enc($this->def('reserve_propriete', ['store_name' => defined('STORE_NAME') ? STORE_NAME : ''])), 0, 0, 'C');

    $this->SetY(-40);
    $this->SetFont('Arial', '', 7);
    $this->SetTextColor(...$rgb);
    $this->Cell(0, 10, PDF::enc($this->def('reserve_propriete_next')), 0, 0, 'C');

    $this->SetY(-35);
    $this->SetFont('Arial', '', 7);
    $this->SetTextColor(...$rgb);
    $this->Cell(0, 10, PDF::enc($this->def('reserve_propriete_next1', ['sell_conditions_url' => self::shopUrl($CLICSHOPPING_CompliancePolicyRules->displayUrlSalesCondition())])), 0, 0, 'C');

    $shopCapital = $CLICSHOPPING_CompliancePolicyRules->displayShopCapital();
    $info_societe = $shopCapital === '' ? '' : $shopCapital . ' - ';

    if ($CLICSHOPPING_CompliancePolicyRules->displayDoubleTaxes() === false) {
      $this->SetY(-25);
      $this->SetFont('Arial', '', 8);
      $this->SetTextColor(...$rgb);
      $this->Cell(0, 10, PDF::enc($this->def('entry_info_societe', ['shop_code_capital' => $info_societe, 'shop_code_rcs' => $CLICSHOPPING_CompliancePolicyRules->displayRegistrationNumber(), 'shop_code_ape' => $CLICSHOPPING_CompliancePolicyRules->displayApeCode()])), 0, 0, 'C');

      $this->SetY(-20);
      $this->SetFont('Arial', '', 8);
      $this->SetTextColor(...$rgb);
      $this->Cell(0, 10, PDF::enc($this->def('entry_info_societe_next', ['tva_shop_intracom' => $CLICSHOPPING_CompliancePolicyRules->displayEUVatNumber()])), 0, 0, 'C');
    } else {
      $this->SetY(-25);
      $this->SetFont('Arial', '', 8);
      $this->SetTextColor(...$rgb);
      $this->Cell(0, 10, PDF::enc($this->def('entry_info_societe1', ['shop_code_capital' => $info_societe, 'shop_code_rcs' => $CLICSHOPPING_CompliancePolicyRules->displayRegistrationNumber(), 'shop_code_ape' => $CLICSHOPPING_CompliancePolicyRules->displayApeCode()])), 0, 0, 'C');

      $this->SetY(-20);
      $this->SetFont('Arial', '', 8);
      $this->SetTextColor(...$rgb);
      $this->Cell(0, 10, PDF::enc($this->def('entry_info_societe_next1', ['tva_shop_provincial' => $CLICSHOPPING_CompliancePolicyRules->displayRegionalTaxesNumber(), 'tva_shop_federal' => $CLICSHOPPING_CompliancePolicyRules->displayFederalTaxesNumber()])), 0, 0, 'C');
    }

    $this->SetY(-15);
    $this->SetFont('Arial', '', 8);
    $this->SetTextColor(...$rgb);
    $this->Cell(0, 10, PDF::enc($CLICSHOPPING_CompliancePolicyRules->displayLegalInformation()), 0, 0, 'C');
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
    $this->Cell(9, 6, PDF::enc($this->def('table_heading_qte')), 1, 0, 'C', 1);
    $this->SetX(15);
    $this->Cell(27, 6, PDF::enc($this->def('table_heading_products_model')), 1, 0, 'C', 1);
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
    $this->Cell(14, 6, PDF::enc($this->def('table_heading_qte')), 1, 0, 'C', 1);
    $this->SetX(20);
    $this->Cell(40, 6, PDF::enc($this->def('table_heading_products_model')), 1, 0, 'C', 1);
    $this->SetX(60);
    $this->Cell(138, 6, $this->def('table_heading_products'), 1, 0, 'C', 1);
    $this->Ln();
  }
}
