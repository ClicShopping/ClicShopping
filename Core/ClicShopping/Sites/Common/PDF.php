<?php
/**
 *
 * @copyright 2008 - https://www.clicshopping.org
 * @Brand : ClicShoppingAI(TM) at Inpi all right Reserved
 * @Licence GPL 2 & MIT
 * @Info : https://www.clicshopping.org/forum/trademark/
 *
 */

namespace ClicShopping\Sites\Common;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTTP;
use ClicShopping\OM\Registry;
/**
 * Class PDF
 *
 * Extends the FPDF library to add custom functionality such as rounded rectangles
 * and customization for invoice headers and footers.
 */
class PDF extends FPDF
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
   * Retrieves the global PDF object from the session if it exists; otherwise, it fetches it from the global scope.
   *
   * @return mixed The PDF object from the session or the global scope.
   */
  private static function getGlobalPdf()
  {
    if (isset($_SESSION['pdf'])) {
      return $_SESSION['pdf'];
    } else {
      global $pdf;

      return $pdf;
    }
  }

  /**
   * Draws a rectangle with rounded corners on the PDF document.
   *
   * @param float $x The x-coordinate of the upper-left corner of the rectangle.
   * @param float $y The y-coordinate of the upper-left corner of the rectangle.
   * @param float $w The width of the rectangle.
   * @param float $h The height of the rectangle.
   * @param float $r The radius of the corners.
   * @param string $style The drawing style of the rectangle. Possible values are:
   *                      'F' - Fill the rectangle.
   *                      'D' - Draw only the border.
   *                      'FD' or 'DF' - Fill and draw the border.
   *                      Default is an empty string for border-only.
   *
   * @return void
   */
  public function roundedRect(float $x, float $y, float $w, float $h, float $r, string $style = '')
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
   * Draws a Bézier curve segment defined by three control points.
   *
   * @param float $x1 The x-coordinate of the first control point.
   * @param float $y1 The y-coordinate of the first control point.
   * @param float $x2 The x-coordinate of the second control point.
   * @param float $y2 The y-coordinate of the second control point.
   * @param float $x3 The x-coordinate of the third control point, which is also the endpoint.
   * @param float $y3 The y-coordinate of the third control point, which is also the endpoint.
   *
   * @return void
   */
  public function _Arc(float $x1, float $y1, float $x2, float $y2, float $x3, float $y3)
  {
    $h = $this->h;
    $this->_out(sprintf('%.2f %.2f %.2f %.2f %.2f %.2f c ', $x1 * $this->k, ($h - $y1) * $this->k,
      $x2 * $this->k, ($h - $y2) * $this->k, $x3 * $this->k, ($h - $y3) * $this->k));
  }

  /**
   * Generates the header section of the invoice document.
   *
   * This method includes components such as the company's logo, name,
   * address, email, and website, and positions them appropriately within the document.
   *
   * @return void
   */
  public function Header()
  {
    $CLICSHOPPING_Template = Registry::get('Template');

// Logo
    if (file_exists(CLICSHOPPING::getConfig('dir_root', 'Shop') . $CLICSHOPPING_Template->getDirectoryTemplateImages() . 'logos/invoice/' . INVOICE_LOGO)) {
      $this->Image(CLICSHOPPING::getConfig('http_server', 'Shop') . $CLICSHOPPING_Template->getDirectoryShopTemplateImages() . 'logos/invoice/' . INVOICE_LOGO, 5, 10, 50);
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
   * Generates and outputs the footer section of the document. This includes:
   * - A thank you message for the customer.
   * - Legal property statements.
   * - Company information and additional optional details.
   *
   * @return void
   */
  public function Footer()
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
    $this->Cell(0, 10, mb_convert_encoding(CLICSHOPPING::getDef('reserve_propriete_next1'), 'ISO-8859-1', 'UTF-8'), 0, 0, 'C');

// Informations de la compagnie
    $this->SetY(-25);
    $this->SetFont('Arial', '', 8);
    $this->SetTextColorRGB(INVOICE_RGB);
    $this->Cell(0, 10, mb_convert_encoding(CLICSHOPPING::getDef('entry_info_societe'), 'ISO-8859-1', 'UTF-8'), 0, 0, 'C');

    $this->SetY(-20);
    $this->SetFont('Arial', '', 8);
    $this->SetTextColorRGB(INVOICE_RGB);
    $this->Cell(0, 10, mb_convert_encoding(CLICSHOPPING::getDef('entry_info_societe_next'), 'ISO-8859-1', 'UTF-8'), 0, 0, 'C');

// Autres informations (champ libre) sur la compagnie
    $this->SetY(-15);
    $this->SetFont('Arial', '', 8);
    $this->SetTextColorRGB(INVOICE_RGB);
    $this->Cell(0, 10, mb_convert_encoding(CLICSHOPPING::getDef('shop_divers'), 'ISO-8859-1', 'UTF-8'), 0, 0, 'C');
  }

  /***************************************************
   * Catalog
   ****************************************************/

  /**
   * Outputs a table heading to a PDF document with predefined fields and styling.
   *
   * @param float $Y_Fields_Name_position The Y-coordinate position where the table headings will be placed on the PDF.
   * @return void
   */
  public static function outputTableHeadingPdf(float $Y_Fields_Name_position)
  {
    $pdf = static::getGlobalPdf();

    $pdf->SetFillColor(245);
    $pdf->SetFont('Arial', 'B', 8);
    $pdf->SetY($Y_Fields_Name_position);
    $pdf->SetX(6);
    $pdf->Cell(9, 6, mb_convert_encoding(CLICSHOPPING::getDef('table_heading_qte'), 'ISO-8859-1', 'UTF-8'), 1, 0, 'C', 1);
    $pdf->SetX(15);
    $pdf->Cell(27, 6, mb_convert_encoding(CLICSHOPPING::getDef('table_heading_products_model'), 'ISO-8859-1', 'UTF-8'), 1, 0, 'C', 1);
    $pdf->SetX(40);
    $pdf->Cell(103, 6, CLICSHOPPING::getDef('table_heading_products'), 1, 0, 'C', 1);
    $pdf->SetX(143);
    $pdf->Cell(15, 6, CLICSHOPPING::getDef('table_heading_tax'), 1, 0, 'C', 1);
    $pdf->SetX(158);
    $pdf->Cell(20, 6, CLICSHOPPING::getDef('table_heading_price_excluding_tax'), 1, 0, 'C', 1);
    /*
        $pdf->SetX(138);
        $pdf->Cell(20,6,TABLE_HEADING_PRICE_INCLUDING_TAX, 1, 0, 'C', 1);
    */
    $pdf->SetX(178);
    $pdf->Cell(20, 6, CLICSHOPPING::getDef('table_heading_total_excluding_tax'), 1, 0, 'C', 1);
    /*
        $pdf->SetX(178);
        $pdf->Cell(20,6,TABLE_HEADING_TOTAL_INCLUDING_TAX, 1, 0, 'C', 1);
    */
    $pdf->Ln();
  }

// Création entête du tableau des produits pour les bons de livraison
//  output_table_heading_packingslip
  /**
   * Outputs the table heading for the packing slip.
   *
   * @param float $Y_Fields_Name_position The Y-coordinate position for the table heading.
   * @return void
   */
  public static function outputTableHeadingPackingslip($Y_Fields_Name_position)
  {
    $pdf = static::getGlobalPdf();

    $pdf->SetFillColor(245);
    $pdf->SetFont('Arial', 'B', 8);
    $pdf->SetY($Y_Fields_Name_position);
    $pdf->SetX(6);
    $pdf->Cell(14, 6, mb_convert_encoding(CLICSHOPPING::getDef('table_heading_qte'), 'ISO-8859-1', 'UTF-8'), 1, 0, 'C', 1);
    $pdf->SetX(20);
    $pdf->Cell(40, 6, mb_convert_encoding(CLICSHOPPING::getDef('table_heading_products_model'), 'ISO-8859-1', 'UTF-8'), 1, 0, 'C', 1);
    $pdf->SetX(60);
    $pdf->Cell(138, 6, CLICSHOPPING::getDef('table_heading_products'), 1, 0, 'C', 1);
    $pdf->Ln();
  }

  /**
   * Outputs a table header for supplier details in a PDF document.
   *
   * @param float $Y_Fields_Name_position The vertical position in the PDF where the table header starts.
   * @return void
   */
  public function outputTableSuppliers(float $Y_Fields_Name_position)
  {
    $pdf = static::getGlobalPdf();

    $pdf->SetFillColor(245);
    $pdf->SetFont('Arial', 'B', 8);
    $pdf->SetY($Y_Fields_Name_position);
    $pdf->SetX(6);
    $pdf->Cell(9, 6, mb_convert_encoding(CLICSHOPPING::getDef('table_heading_qte'), 'ISO-8859-1', 'UTF-8'), 1, 0, 'C', 1);
    $pdf->SetX(15);
    $pdf->Cell(27, 6, mb_convert_encoding(CLICSHOPPING::getDef('table_heading_products_model'), 'ISO-8859-1', 'UTF-8'), 1, 0, 'C', 1);
    $pdf->SetX(40);
    $pdf->Cell(78, 6, CLICSHOPPING::getDef('table_heading_products'), 1, 0, 'C', 1);
    $pdf->SetX(105);
    $pdf->Cell(45, 6, CLICSHOPPING::getDef('table_heading_options'), 1, 0, 'C', 1);
    $pdf->SetX(150);
    $pdf->Cell(45, 6, CLICSHOPPING::getDef('values'), 1, 0, 'C', 1);

    $pdf->Ln();
  }

  /**
   * Outputs a table header for Customers and Suppliers in a PDF document.
   *
   * @param float $Y_Fields_Name_position The Y-coordinate position for the table header in the PDF document.
   * @return void
   */
  public static function outputTableCustomersSuppliers(float $Y_Fields_Name_position)
  {
    $pdf = static::getGlobalPdf();

    $pdf->SetFillColor(245);
    $pdf->SetFont('Arial', 'B', 8);
    $pdf->SetY($Y_Fields_Name_position);
    $pdf->SetX(6);
    $pdf->Cell(9, 6, mb_convert_encoding(CLICSHOPPING::getDef('table_heading_qte'), 'ISO-8859-1', 'UTF-8'), 1, 0, 'C', 1);
    $pdf->SetX(15);
    $pdf->Cell(13, 6, CLICSHOPPING::getDef('table_heading_customers_id'), 1, 0, 'C', 1);
    $pdf->SetX(28);
    $pdf->Cell(25, 6, CLICSHOPPING::getDef('table_heading_customers_name'), 1, 0, 'C', 1);
    $pdf->SetX(53);
    $pdf->Cell(30, 6, mb_convert_encoding(CLICSHOPPING::getDef('table_heading_products_model'), 'ISO-8859-1', 'UTF-8'), 1, 0, 'C', 1);
    $pdf->SetX(83);
    $pdf->Cell(60, 6, CLICSHOPPING::getDef('table_heading_products'), 1, 0, 'C', 1);
    $pdf->SetX(143);
    $pdf->Cell(40, 6, CLICSHOPPING::getDef('table_heading_options'), 1, 0, 'C', 1);
    $pdf->SetX(183);
    $pdf->Cell(20, 6, CLICSHOPPING::getDef('values'), 1, 0, 'C', 1);

    $pdf->Ln();
  }
}
