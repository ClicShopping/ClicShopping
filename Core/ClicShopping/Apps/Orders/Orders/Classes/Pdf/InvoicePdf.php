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
    $this->MultiCell(70, 3.3, mb_convert_encoding($CLICSHOPPING_Address->addressFormat($billingAddress['format_id'], $billingAddress, '', '', "\n"), 'ISO-8859-1', 'UTF-8'), 0, 'L');

    $this->SetFont('Arial', 'B', 8);
    $this->SetTextColor(0);
    $this->Text(113, 44, $this->def('entry_ship_to'));
    $this->SetX(0);
    $this->SetY(47);
    $this->Cell(111);
    $this->MultiCell(70, 3.3, mb_convert_encoding($CLICSHOPPING_Address->addressFormat($deliveryAddress['format_id'], $deliveryAddress, '', '', "\n"), 'ISO-8859-1', 'UTF-8'), 0, 'L');

    $this->SetFont('Arial', 'B', 8);
    $this->SetTextColor(0);
    $this->Text(10, 85, $this->def('entry_customer_information'));

    $this->SetFont('Arial', '', 8);
    $this->SetTextColor(0);
    $this->Text(15, 90, $this->def('entry_email') . ' ' . $order->customer['email_address']);

    $this->SetFont('Arial', '', 8);
    $this->SetTextColor(0);
    $this->Text(15, 95, mb_convert_encoding($this->def('entry_customer_number'), 'ISO-8859-1', 'UTF-8') . ' ' . (int)($order->customer['customers_id'] ?? 0));

    $this->SetFont('Arial', '', 8);
    $this->SetTextColor(0);
    $this->Text(15, 100, mb_convert_encoding($this->def('entry_phone'), 'ISO-8859-1', 'UTF-8') . ' ' . Hash::displayDecryptedDataText($order->customer['telephone']));

    $this->renderOrderNumberLine($oID, $invoiceStatusId, $invoiceTitle, $invoiceDate);
    $this->renderOrderDateLine($invoiceStatusId, $invoiceTitle, $order->info['date_purchased']);

    $temp = substr(mb_convert_encoding($order->info['payment_method'], 'ISO-8859-1', 'UTF-8'), 0, 30);
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
        $this->MultiCell(103, 6, mb_convert_encoding(substr($product_name_attrib_contact, 0, 95), 'ISO-8859-1', 'UTF-8') . ' .. ', 1, 'L');
      } else {
        $this->MultiCell(103, 6, mb_convert_encoding($product_name_attrib_contact, 'ISO-8859-1', 'UTF-8'), 1, 'L');
        if (strlen($product_name_attrib_contact) <= 40) {
          $this->Ln();
        }
      }

      $this->SetY($Y_Table_Position);
      $this->SetX(15);
      $this->SetFont('Arial', '', 7);
      $this->MultiCell(25, 6, mb_convert_encoding($products[$i]['model'], 'ISO-8859-1', 'UTF-8'), 1, 'C');

      $this->SetFont('Arial', '', 7);
      $this->SetY($Y_Table_Position);
      $this->SetX(143);
      $this->MultiCell(15, 6, Tax::displayTaxRateValue($products[$i]['tax']), 1, 'C');

      $this->SetY($Y_Table_Position);
      $this->SetX(158);
      $this->SetFont('Arial', '', 7);
      $this->MultiCell(20, 6, mb_convert_encoding(html_entity_decode($CLICSHOPPING_Currencies->format($products[$i]['final_price'], true, $order->info['currency'], $order->info['currency_value'])), 'ISO-8859-1', 'UTF-8'), 1, 'C');

      $this->SetY($Y_Table_Position);
      $this->SetX(178);
      $this->MultiCell(20, 6, mb_convert_encoding(html_entity_decode($CLICSHOPPING_Currencies->format($products[$i]['final_price'] * $products[$i]['qty'], true, $order->info['currency'], $order->info['currency_value'])), 'ISO-8859-1', 'UTF-8'), 1, 'C');
      $Y_Table_Position += 6;

      $item_count++;
      if ((is_int($item_count / 32) && $i >= 20) || ($i == 20)) {
        $this->AddPage();
        $Y_Table_Position = 70;
        $this->outputTableHeadingPdf($Y_Table_Position - 6);
        if ($i == 20) $item_count = 1;
      }
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

      $this->MultiCell(94, 6, substr(mb_convert_encoding(html_entity_decode($totals[$i]['title']), 'ISO-8859-1', 'UTF-8'), 0, 30) . ' : ' . mb_convert_encoding(html_entity_decode($totals[$i]['text']), 'ISO-8859-1', 'UTF-8'), 0, 'R');
      $Y_Table_Position += 5;
    }
  }

  private function renderOrderNumberLine(int $oID, int $statusId, string $title, string $invoiceDate): void
  {
    if ($statusId === 2) {
      $temp = str_replace('&nbsp;', ' ', 'No ' . $title . ' : ' . DateTime::toDateReferenceShort($invoiceDate) . 'S');
      $this->Text(10, 113, $temp . $oID);
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
