<?php
/**
 *
 * @copyright 2008 - https://www.clicshopping.org
 * @Brand : ClicShoppingAI(TM) at Inpi all right Reserved
 * @Licence GPL 2 & MIT
 * @Info : https://www.clicshopping.org/forum/trademark/
 *
 */

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTTP;
use ClicShopping\OM\Registry;
/**
 * Class pdfInvoice
 *
 * This class extends the functionality of FPDF to facilitate the creation of customized PDFs,
 * particularly invoices. It includes methods for rendering rounded rectangles, headers, footers, and other graphical elements.
 */
class pdfInvoice extends FPDF
{
  /**
   * Helper to set text color from a comma-separated RGB string (e.g. '158,11,14').
   * FPDF::SetTextColor() expects three separate numeric arguments.
   *
   * @param string $rgb Comma-separated RGB string
   * @return void
   */
  protected function SetTextColorRGB(string $rgb): void
  {
    $parts = array_map('intval', explode(',', $rgb));
    $this->SetTextColor($parts[0] ?? 0, $parts[1] ?? 0, $parts[2] ?? 0);
  }

  /**
   * Draws a rounded rectangle on the page.
   *
   * @param float $x The x-coordinate of the top-left corner of the rectangle.
   * @param float $y The y-coordinate of the top-left corner of the rectangle.
   * @param float $w The width of the rectangle.
   * @param float $h The height of the rectangle.
   * @param float $r The radius of the rounded corners.
   * @param string $style The drawing style ('D' for draw, 'F' for fill, 'DF' or 'FD' for both draw and fill). Default is an empty string.
   * @return void
   */
  public function roundedRect($x, $y, $w, $h, $r, $style = '')
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

  /**
   * Draws a cubic Bézier curve between three control points.
   *
   * @param float $x1 The x-coordinate of the first control point.
   * @param float $y1 The y-coordinate of the first control point.
   * @param float $x2 The x-coordinate of the second control point.
   * @param float $y2 The y-coordinate of the second control point.
   * @param float $x3 The x-coordinate of the third control point.
   * @param float $y3 The y-coordinate of the third control point.
   *
   * @return void
   */
  public function _Arc($x1, $y1, $x2, $y2, $x3, $y3)
  {
    $h = $this->h;
    $this->_out(sprintf('%.2f %.2f %.2f %.2f %.2f %.2f c ', $x1 * $this->k, ($h - $y1) * $this->k,
      $x2 * $this->k, ($h - $y2) * $this->k, $x3 * $this->k, ($h - $y3) * $this->k));
  }

  /**
   * Generates the header section for an invoice, including the company logo, name, address, email, and website URL.
   *
   * @return void
   */
  public function Header(): void
  {
    $CLICSHOPPING_Template = Registry::get('Template');

// Logo
    if (is_file(CLICSHOPPING::getConfig('dir_root', 'Shop') . $CLICSHOPPING_Template->getDirectoryTemplateImages() . 'logos/invoice/' . INVOICE_LOGO)) {
      $this->Image(HTTP::getShopUrlDomain() . $CLICSHOPPING_Template->getDirectoryTemplateImages() . 'logos/invoice/' . INVOICE_LOGO, 5, 10, 50);
    }

// Nom de la compagnie
    $this->SetX(0);
    $this->SetY(10);
    $this->SetFont('Arial', 'B', 10);
    $this->SetTextColorRGB(INVOICE_RGB);
    $this->Ln(0);
    $this->Cell(125);
    $this->MultiCell(100, 3.5, mb_convert_encoding(STORE_NAME, 'ISO-8859-1', 'UTF-8'), 0, 'L');

// Adresse de la compagnie
    $this->SetX(0);
    $this->SetY(15);
    $this->SetFont('Arial', '', 8);
    $this->SetTextColorRGB(INVOICE_RGB);
    $this->Ln(0);
    $this->Cell(125);
    $this->MultiCell(100, 3.5, mb_convert_encoding(STORE_NAME_ADDRESS, 'ISO-8859-1', 'UTF-8'), 0, 'L');

// Email
    $this->SetX(0);
    $this->SetY(30);
    $this->SetFont('Arial', '', 8);
    $this->SetTextColorRGB(INVOICE_RGB);
    $this->Ln(0);
    $this->Cell(-3);
    $this->MultiCell(100, 3.5, mb_convert_encoding(CLICSHOPPING::getDef('entry_email'), 'ISO-8859-1', 'UTF-8') . ' ' . STORE_OWNER_EMAIL_ADDRESS, 0, 'L');

// Website
    $this->SetX(0);
    $this->SetY(34);
    $this->SetFont('Arial', '', 8);
    $this->SetTextColorRGB(INVOICE_RGB);
    $this->Ln(0);
    $this->Cell(-3);
    $this->MultiCell(100, 3.5, CLICSHOPPING::getDef('entry_http_site') . ' ' . HTTP::typeUrlDomain(), 0, 'L');
  }

  /**
   * Adds footer content to the document.
   *
   * Formats and positions various elements in the footer, including customer appreciation text,
   * legal property information, company details, and additional information.
   * Displays differing information based on configuration settings (e.g., DISPLAY_DOUBLE_TAXE).
   *
   * @return void
   */
  public function Footer():void
  {
// Remerciement
    $this->SetY(-55);
    $this->SetFont('Arial', 'B', 8);
    $this->SetTextColorRGB(INVOICE_RGB);
    $this->Cell(0, 10, mb_convert_encoding(CLICSHOPPING::getDef('thank_you_customer'), 'ISO-8859-1', 'UTF-8'), 0, 0, 'C');
// Proprieties Legal
    $this->SetY(-45);
    $this->SetFont('Arial', '', 7);
    $this->SetTextColorRGB(INVOICE_RGB);

    $this->Cell(0, 10, mb_convert_encoding(CLICSHOPPING::getDef('reserve_propriete'), 'ISO-8859-1', 'UTF-8'), 0, 0, 'C');
    $this->SetY(-40);
    $this->SetFont('Arial', '', 7);
    $this->SetTextColorRGB(INVOICE_RGB);

    $this->Cell(0, 10, mb_convert_encoding(CLICSHOPPING::getDef('reserve_propriete_next'), 'ISO-8859-1', 'UTF-8'), 0, 0, 'C');
    $this->SetY(-35);
    $this->SetFont('Arial', '', 7);
    $this->SetTextColorRGB(INVOICE_RGB);
    $this->Cell(0, 10, mb_convert_encoding(CLICSHOPPING::getDef('reserve_propriete_next1', ['sell_conditions_url' => HTTP::getShopUrlDomain() . ' ' . SHOP_CODE_URL_CONDITIONS_VENTE]), 'ISO-8859-1', 'UTF-8'), 0, 0, 'C');
// Informations de la compagnie
    if (DISPLAY_DOUBLE_TAXE == 'false') {
      $this->SetY(-25);
      $this->SetFont('Arial', '', 8);
      $this->SetTextColorRGB(INVOICE_RGB);
      $this->Cell(0, 10, mb_convert_encoding(CLICSHOPPING::getDef('entry_info_societe', ['shop_code_capital' => SHOP_CODE_CAPITAL, 'shop_code_rcs' => SHOP_CODE_RCS, 'shop_code_ape' => SHOP_CODE_APE]), 'ISO-8859-1', 'UTF-8'), 0, 0, 'C');

      $this->SetY(-20);
      $this->SetFont('Arial', '', 8);
      $this->SetTextColorRGB(INVOICE_RGB);
      $this->Cell(0, 10, mb_convert_encoding(CLICSHOPPING::getDef('entry_info_societe_next', ['tva_shop_intracom' => TVA_SHOP_INTRACOM]), 'ISO-8859-1', 'UTF-8'), 0, 0, 'C');
    } else {
      $this->SetY(-25);
      $this->SetFont('Arial', '', 8);
      $this->SetTextColorRGB(INVOICE_RGB);
      $this->Cell(0, 10, mb_convert_encoding(CLICSHOPPING::getDef('entry_info_societe1', ['shop_code_capital' => SHOP_CODE_CAPITAL, 'shop_code_rcs' => SHOP_CODE_RCS, 'shop_code_ape' => SHOP_CODE_APE]), 'ISO-8859-1', 'UTF-8'), 0, 0, 'C');

      $this->SetY(-20);
      $this->SetFont('Arial', '', 8);
      $this->SetTextColorRGB(INVOICE_RGB);
      $this->Cell(0, 10, mb_convert_encoding(CLICSHOPPING::getDef('entry_info_societe_next1', ['tva_shop_provincial' => TVA_SHOP_PROVINCIAL, 'tva_shop_federal' => TVA_SHOP_FEDERAL]), 'ISO-8859-1', 'UTF-8'), 0, 0, 'C');
    }


// Autres informations (champ libre) sur la compagnie
    $this->SetY(-15);
    $this->SetFont('Arial', '', 8);
    $this->SetTextColorRGB(INVOICE_RGB);
    $this->Cell(0, 10, mb_convert_encoding(SHOP_DIVERS, 'ISO-8859-1', 'UTF-8'), 0, 0, 'C');
  }
}
