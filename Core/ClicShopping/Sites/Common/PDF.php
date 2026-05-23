<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Sites\Common;

use ClicShopping\OM\CLICSHOPPING;

/**
 * Class PDF
 *
 * Extends FPDF with vector-graphic helpers (rounded rectangles) and table
 * headings for the suppliers / customers-suppliers PDF reports.
 *
 * NOTE: invoice + packing-slip PDFs are NOT generated through this class
 * anymore — see Apps/Orders/Orders/Classes/Pdf/ (AbstractOrderPdf,
 * InvoicePdf, PackingSlipPdf, OrderPdfRenderer).
 */
class PDF extends FPDF
{
  private static function getGlobalPdf()
  {
    if (isset($_SESSION['pdf'])) {
      return $_SESSION['pdf'];
    }

    global $pdf;

    return $pdf;
  }

  /**
   * Draws a rectangle with rounded corners on the PDF document.
   *
   * @param string $style 'F' fill, 'D' draw, 'FD'/'DF' both, default draw-only.
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
   * Outputs a table header for supplier details in a PDF document.
   */
  public function outputTableSuppliers(float $Y_Fields_Name_position): void
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
   */
  public static function outputTableCustomersSuppliers(float $Y_Fields_Name_position): void
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
