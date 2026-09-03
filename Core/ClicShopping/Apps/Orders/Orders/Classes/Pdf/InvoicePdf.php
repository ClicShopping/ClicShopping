<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Orders\Orders\Classes\Pdf;

use ClicShopping\OM\DateTime;
use ClicShopping\OM\Hash;
use ClicShopping\OM\Registry;
use ClicShopping\Sites\Shop\Tax;
use ClicShopping\Sites\Common\PDF;

/**
 * Single source of truth for the order invoice PDF page (one order).
 *
 * Reads data from a pre-loaded Order object — caller decides whether
 * that object is the front-office ClicShopping\OM\Order or the
 * back-office OrderAdmin. Addresses are passed explicitly to bridge
 * the small naming gap between the two ($order->customer vs $order->billing).
 *
 * Supports batch use: instantiate once, call addInvoicePage() per order,
 * then Output().
 */
class InvoicePdf extends AbstractOrderPdf
{
  protected bool $drawSeparatorLine = false;
  protected bool $drawTitleBox = false;

  public function setDrawSeparatorLine(bool $on): void
  {
    $this->drawSeparatorLine = $on;
  }

  public function setDrawTitleBox(bool $on): void
  {
    $this->drawTitleBox = $on;
  }

  /**
   * Append one invoice page for the given order.
   */
  public function addInvoicePage(
    int $oID,
    object $order,
    array $billingAddress,
    array $deliveryAddress,
    string $invoiceTitle,
    int $invoiceStatusId,
    string $invoiceDate
  ): void
  {
    $CLICSHOPPING_Currencies = Registry::get('Currencies');
    $CLICSHOPPING_Address = Registry::get('Address');

    $this->SetMargins(10, 2, 6);
    $this->AddPage();

    if ($this->drawSeparatorLine) {
      $this->Cell(50);
      $this->SetY(-25);
      $this->SetDrawColor(153, 153, 153);
      $this->Cell(185, .1, '', 1, 1, 'L', 1);
    }

    $this->Cell(-5);
    $this->SetY(103);
    $this->SetX(0);
    $this->SetDrawColor(220, 220, 220);
    $this->Cell(3, .1, '', 1, 1, '', 1);

    $this->SetFont('Arial', 'B', 8);
    $this->SetTextColor(0);
    $this->Text(11, 44, $this->def('entry_sold_to'));
    $this->SetX(0);
    $this->SetY(47);
    $this->Cell(9);
    $this->MultiCell(70, 3.3, PDF::enc($CLICSHOPPING_Address->addressFormat($billingAddress['format_id'], $billingAddress, '', '', "\n")), 0, 'L');

    $this->SetFont('Arial', 'B', 8);
    $this->SetTextColor(0);
    $this->Text(113, 44, $this->def('entry_ship_to'));
    $this->SetX(0);
    $this->SetY(47);
    $this->Cell(111);
    $this->MultiCell(70, 3.3, PDF::enc($CLICSHOPPING_Address->addressFormat($deliveryAddress['format_id'], $deliveryAddress, '', '', "\n")), 0, 'L');

    $this->SetFont('Arial', 'B', 8);
    $this->SetTextColor(0);
    $this->Text(10, 85, $this->def('entry_customer_information'));

    $this->SetFont('Arial', '', 8);
    $this->SetTextColor(0);
    $this->Text(15, 90, $this->def('entry_email') . ' ' . $order->customer['email_address']);

    $this->SetFont('Arial', '', 8);
    $this->SetTextColor(0);
    $this->Text(15, 95, PDF::enc($this->def('entry_customer_number')) . ' ' . (int)($order->customer['customers_id'] ?? 0));

    $this->SetFont('Arial', '', 8);
    $this->SetTextColor(0);
    $this->Text(15, 100, PDF::enc($this->def('entry_phone')) . ' ' . Hash::displayDecryptedDataText($order->customer['telephone']));

    $this->renderOrderNumberLine($oID, $invoiceStatusId, $invoiceTitle, $invoiceDate);
    $this->renderOrderDateLine($invoiceStatusId, $invoiceTitle, $order->info['date_purchased']);

    $temp = substr(PDF::enc($order->info['payment_method']), 0, 30);
    $this->Text(120, 113, $this->def('entry_payment_method') . ' ' . $temp);

    if ($this->drawTitleBox) {
      $this->SetDrawColor(0);
      $this->SetLineWidth(0.2);
      $this->SetFillColor(245);
      $this->roundedRect(108, 32, 90, 7, 2, 'DF');
    }
    $this->SetFont('Arial', '', 10);
    $this->SetY(32);
    $this->SetX(108);
    $this->MultiCell(90, 7, $invoiceTitle, 0, 'C');

    $Y_Fields_Name_position = 125;
    $Y_Table_Position = 131;
    $this->outputTableHeadingPdf($Y_Fields_Name_position);

    $item_count = 0;
    $lines_excluding_tax = 0.0;
    $products = $order->products;
    for ($i = 0, $n = count($products); $i < $n; $i++) {
      $this->SetFont('Arial', '', 7);
      $this->SetY($Y_Table_Position);
      $this->SetX(6);
      $this->MultiCell(9, 6, $products[$i]['qty'], 1, 'C');

      $prod_attribs = '';
      if (isset($products[$i]['attributes']) && count($products[$i]['attributes']) > 0) {
        for ($j = 0, $n2 = count($products[$i]['attributes']); $j < $n2; $j++) {
          $reference = '';
          if (!empty($products[$i]['attributes'][$j]['reference'])) {
            $reference = $products[$i]['attributes'][$j]['reference'] . ' / ';
          }
          $prod_attribs .= ' - ' . $reference . $products[$i]['attributes'][$j]['option'] . ' : ' . $products[$i]['attributes'][$j]['value'];
        }
      }

      $product_name_attrib_contact = $products[$i]['name'] . $prod_attribs;

      $this->SetY($Y_Table_Position);
      $this->SetX(40);
      $this->SetFont('Arial', '', 6);
      if (strlen($product_name_attrib_contact) > 95) {
        $this->MultiCell(103, 6, PDF::enc(substr($product_name_attrib_contact, 0, 95)) . ' .. ', 1, 'L');
      } else {
        $this->MultiCell(103, 6, PDF::enc($product_name_attrib_contact), 1, 'L');
        if (strlen($product_name_attrib_contact) <= 40) {
          $this->Ln();
        }
      }

      $this->SetY($Y_Table_Position);
      $this->SetX(15);
      $this->SetFont('Arial', '', 7);
      $this->MultiCell(25, 6, PDF::enc($products[$i]['model']), 1, 'C');

      $this->SetFont('Arial', '', 7);
      $this->SetY($Y_Table_Position);
      $this->SetX(143);
      $this->MultiCell(15, 6, Tax::displayTaxRateValue($products[$i]['tax']), 1, 'C');

      $this->SetY($Y_Table_Position);
      $this->SetX(158);
      $this->SetFont('Arial', '', 7);
      $this->MultiCell(20, 6, PDF::enc(html_entity_decode($CLICSHOPPING_Currencies->format($products[$i]['final_price'], true, $order->info['currency'], $order->info['currency_value']))), 1, 'C');

      $this->SetY($Y_Table_Position);
      $this->SetX(178);
      $this->MultiCell(20, 6, PDF::enc(html_entity_decode($CLICSHOPPING_Currencies->format($products[$i]['final_price'] * $products[$i]['qty'], true, $order->info['currency'], $order->info['currency_value']))), 1, 'C');
      $Y_Table_Position += 6;

      $lines_excluding_tax += (float)$products[$i]['final_price'] * (float)$products[$i]['qty'];

      $item_count++;
      if ((is_int($item_count / 32) && $i >= 20) || ($i == 20)) {
        $this->AddPage();
        $Y_Table_Position = 70;
        $this->outputTableHeadingPdf($Y_Table_Position - 6);
        if ($i == 20) $item_count = 1;
      }
    }

    // A tax-inclusive order stores a TTC subtotal while the product columns are titled "excluding
    // tax": the HT base is then on no line at all. Print it. Summed from the order's own amounts and
    // converted once, like every stored total row — converting line by line and adding rounds twice.
    if ((string)($order->info['prices_include_tax'] ?? '') === '1') {
      $this->SetY($Y_Table_Position + 5);
      $this->SetX(102);
      $this->SetFont('Arial', '', 7);
      $this->MultiCell(94, 6, PDF::enc(html_entity_decode($this->def('entry_total_excluding_tax')))
        . ' : ' . PDF::enc(html_entity_decode($CLICSHOPPING_Currencies->format($lines_excluding_tax, true, $order->info['currency'], $order->info['currency_value']))), 0, 'R');
      $Y_Table_Position += 5;
    }

    $totals = $order->totals;
    for ($i = 0, $n = count($totals); $i < $n; $i++) {
      $this->SetY($Y_Table_Position + 5);
      $this->SetX(102);

      $temp = substr($totals[$i]['text'], 0, 3);
      if ($temp == '<strong>') {
        $this->SetFont('Arial', 'B', 7);
        $temp2 = substr($totals[$i]['text'], 3);
        $totals[$i]['text'] = substr($temp2, 0, strlen($temp2) - 4);
      }

      $this->MultiCell(94, 6, substr(PDF::enc(html_entity_decode($totals[$i]['title'])), 0, 30) . ' : ' . PDF::enc(html_entity_decode($totals[$i]['text'])), 0, 'R');
      $Y_Table_Position += 5;
    }
  }

  private function renderOrderNumberLine(int $oID, int $statusId, string $title, string $invoiceDate): void
  {
    if ($statusId === 2) {
      $temp = str_replace('&nbsp;', ' ', 'No ' . $title . ' : ' . $this->invoiceNumber($invoiceDate, $oID));
      $this->Text(10, 113, $temp);
    } elseif ($statusId === 3) {
      $temp = str_replace('&nbsp;', ' ', $title . ': ');
      $this->Text(10, 113, $temp);
    } else {
      $temp = str_replace('&nbsp;', ' ', 'No ' . $title . ' : ');
      $this->Text(10, 113, $temp . $oID);
    }
  }

  private function renderOrderDateLine(int $statusId, string $title, string $datePurchased): void
  {
    if ($statusId === 3) {
      return;
    }
    $temp = str_replace('&nbsp;', ' ', $this->def('print_order_date') . ' ' . $title . ' : ');
    $this->Text(60, 113, $temp . DateTime::toShort($datePurchased));
  }
}
