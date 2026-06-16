<?php
/**
 * Shared PDF rendering engine for invoices and packingslips.
 * Replaces duplicated code in OrderInvoice.php, invoice_batch.php,
 * packingslip.php, packingslip_batch.php.
 *
 * Usage:
 *   // Single order
 *   $renderer = new PDF_Invoice('invoice');        // or 'packingslip'
 *   $renderer->render($pdf, $order, $extra_data);
 *
 *   // Batch mode
 *   $renderer = new PDF_Invoice('invoice');
 *   $renderer->renderBatch($pdf, $order, $batch_options);
 */

namespace ClicShopping\Sites\Common;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\DateTime;
use ClicShopping\OM\Hash;
use ClicShopping\Sites\Shop\Tax;
use  ClicShopping\OM\Registry;

class PDF_Invoice
{
  /** @var string Document type: 'invoice' or 'packingslip' */
  private string $type;

  /** @var array Column configuration per type */
  private array $config;

  /**
   * @param string $type 'invoice' or 'packingslip'
   */
  public function __construct(string $type = 'invoice')
  {
    $this->type = $type;
    $this->config = $this->getConfig();
  }

  /**
   * Column layout configuration per document type.
   * All X positions are absolute from left margin.
   */
  private function getConfig(): array
  {
    return [
      'invoice' => [
        'qty_width'    => 9,
        'model_width'  => 25,
        'name_width'   => 103,
        'tax_width'    => 15,
        'price_width'  => 20,
        'total_width'  => 20,
        'qty_x'        => 6,
        'model_x'      => 15,
        'name_x'       => 40,
        'tax_x'        => 143,
        'price_x'      => 158,
        'total_x'      => 178,
        'name_trunc'   => 95,
        'header_func'  => 'outputTableHeadingPdf',
      ],
      'packingslip' => [
        'qty_width'    => 14,
        'model_width'  => 40,
        'name_width'   => 138,
        'tax_width'    => 0,       // no tax column
        'price_width'  => 0,       // no price column
        'total_width'  => 0,       // no total column
        'qty_x'        => 6,
        'model_x'      => 20,
        'name_x'       => 60,
        'tax_x'        => 0,
        'price_x'      => 0,
        'total_x'      => 0,
        'name_trunc'   => 70,
        'header_func'  => 'outputTableHeadingPackingslip',
      ],
    ][$this->type] ?? $this->getConfig()['invoice'];
  }

  /**
   * Render a single order PDF.
   *
   * @param \FPDF $pdf        FPDF instance (must be new FPDF(), not pdfInvoice)
   * @param object $order     Order object with ->info, ->customer, ->delivery, ->billing, ->products, ->totals
   * @param array  $extra     Extra data: oID, customers_id, order_status_invoice_id,
   *                          orders_status_invoice_name, date_added, date_purchased, payment_method
   */
  public function render(\FPDF $pdf, object $order, array $extra): void
  {
    $cfg = $this->config;

    // ---- Page setup ----
    $pdf->SetMargins(10, 2, 6);
    $pdf->AddPage();

    // ---- Header (company info) ----
    $this->renderHeader($pdf);

    // ---- Fold line ----
    $pdf->Cell(-5);
    $pdf->SetY(103);
    $pdf->SetX(0);
    $pdf->SetDrawColor(220, 220, 220);
    $pdf->Cell(3, .1, '', 1, 1, '', 1);

    // ---- Billing address ----
    $this->renderAddressBox($pdf, $order, 'billing', $this->getDef('entry_sold_to'), 6, 40, 90, 35);

    // ---- Delivery address ----
    $this->renderAddressBox($pdf, $order, 'delivery', $this->getDef('entry_ship_to'), 108, 40, 90, 35);

    // ---- Customer info ----
    $pdf->SetFont('Arial', 'B', 8);
    $pdf->SetTextColor(0);
    $pdf->Text(10, 85, $this->getDef('entry_customer_information'));

    $pdf->SetFont('Arial', '', 8);
    $pdf->SetTextColor(0);
    $pdf->Text(15, 90, $this->getDef('entry_email') . ' ' . $order->customer['email_address']);
    $pdf->Text(15, 95, $this->getDef('entry_customer_number') . ' ' . ($extra['customers_id'] ?? ''));
    $pdf->Text(15, 100, $this->getDef('entry_phone') . ' ' . ($order->customer['telephone'] ?? ''));

    // ---- Order number / date / payment box ----
    $this->renderOrderBox($pdf, $order, $extra);

    // ---- Product table ----
    $Y_Fields_Name_position = 125;
    $Y_Table_Position = 131;

    $pdf->SetFont('Arial', '', 7);
    $pdf->SetY($Y_Fields_Name_position);
    $pdf->SetX(0);
    $func = $cfg['header_func'];
    PDF::${func}($Y_Fields_Name_position);

    $this->renderProducts($pdf, $order, $extra, $cfg, $Y_Table_Position);

    // ---- Totals ----
    $this->renderTotals($pdf, $order, $cfg, $Y_Table_Position);

    // ---- Footer ----
    if (DISPLAY_INVOICE_FOOTER === 'false') {
      $this->renderFooter($pdf);
    }
  }

  /**
   * Render company header (logo + name + address + email + website).
   */
  private function renderHeader(\FPDF $pdf): void
  {
    if (DISPLAY_INVOICE_HEADER !== 'false') {
      return;
    }

    $logo = $this->getInvoiceLogo();
    if ($logo !== false) {
      $pdf->Image($logo, 5, 10, 50);
    }

    $pdf->SetX(0);
    $pdf->SetY(10);
    $pdf->SetFont('Arial', 'B', 10);
    SetTextColorRGB($pdf, INVOICE_RGB);
    $pdf->Ln(0);
    $pdf->Cell(125);
    $pdf->MultiCell(100, 3.5, mb_convert_encoding(STORE_NAME, 'ISO-8859-1', 'UTF-8'), 0, 'L');

    $pdf->SetX(0);
    $pdf->SetY(15);
    $pdf->SetFont('Arial', '', 8);
    SetTextColorRGB($pdf, INVOICE_RGB);
    $pdf->Ln(0);
    $pdf->Cell(125);
    $pdf->MultiCell(100, 3.5, mb_convert_encoding(STORE_NAME_ADDRESS, 'ISO-8859-1', 'UTF-8'), 0, 'L');

    $pdf->SetX(0);
    $pdf->SetY(30);
    $pdf->SetFont('Arial', '', 8);
    SetTextColorRGB($pdf, INVOICE_RGB);
    $pdf->Ln(0);
    $pdf->Cell(-3);
    $pdf->MultiCell(100, 3.5, mb_convert_encoding($this->getDef('entry_email'), 'ISO-8859-1', 'UTF-8') . ' ' . STORE_OWNER_EMAIL_ADDRESS, 0, 'L');

    $pdf->SetX(0);
    $pdf->SetY(34);
    $pdf->SetFont('Arial', '', 8);
    SetTextColorRGB($pdf, INVOICE_RGB);
    $pdf->Ln(0);
    $pdf->Cell(-3);
    $pdf->MultiCell(100, 3.5, $this->getDef('entry_http_site') . ' ' . CLICSHOPPING::getConfig('http_server', 'Shop') . CLICSHOPPING::getConfig('http_path', 'Shop'), 0, 'L');
  }

  /**
   * Render one address box (billing or delivery).
   */
  private function renderAddressBox(\FPDF $pdf, object $order, string $address_type, string $title, int $x, int $y, int $w, int $h): void
  {
    $pdf->SetDrawColor(0);
    $pdf->SetLineWidth(0.2);
    $pdf->SetFillColor($address_type === 'billing' ? 245 : 255);
    PDF::roundedRect($x, $y, $w, $h, 2, 'DF');

    $pdf->SetFont('Arial', 'B', 8);
    $pdf->SetTextColor(0);
    $pdf->Text($x + 5, $y + 4, $title);

    $pdf->SetX(0);
    $pdf->SetY($y + 7);
    $pdf->Cell($address_type === 'billing' ? 9 : 111);
    $pdf->MultiCell(70, 3.3, mb_convert_encoding(
      $order->$address_type['format_id'] !== null
        ? $order->$address_type['format_id']
        : 0, 'ISO-8859-1', 'UTF-8'
    ), 0, 'L');
  }

  /**
   * Render order number, date, payment method box.
   */
  private function renderOrderBox(\FPDF $pdf, object $order, array $extra): void
  {
    $cfg = $this->config;
    $invoice_id = $extra['order_status_invoice_id'] ?? 0;

    // Rounded rect box
    $pdf->SetDrawColor(0);
    $pdf->SetLineWidth(0.2);
    $pdf->SetFillColor(245);
    PDF::roundedRect(6, 107, 192, 11, 2, 'DF');

    // Left: order number / status text
    $this->renderOrderStatusText($pdf, $invoice_id, $extra);

    // Center: date
    $this->renderOrderDateText($pdf, $invoice_id, $extra);

    // Right: payment method
    $payment = $order->info['payment_method'] ?? '';
    $payment = mb_convert_encoding($payment, 'ISO-8859-1', 'UTF-8');
    $pdf->Text(110, 113, $this->getDef('entry_payment_method') . ' ' . substr($payment, 0, 60));

    // Title box (BON DE COMMANDE / FACTURE)
    $title = $extra['orders_status_invoice_name'] ?? '';
    $pdf->SetDrawColor(0);
    $pdf->SetLineWidth(0.2);
    $pdf->SetFillColor(245);
    PDF::roundedRect(108, 32, 90, 7, 2, 'DF');
    $pdf->SetFont('Arial', '', 10);
    $pdf->SetY(32);
    $pdf->SetX(108);
    $pdf->MultiCell(90, 7, $title, 0, 'C');
  }

  /**
   * Render the order status text on the left side of the order box.
   */
  private function renderOrderStatusText(\FPDF $pdf, int $invoice_id, array $extra): void
  {
    $oID = $extra['oID'] ?? '';
    $status_name = $extra['orders_status_invoice_name'] ?? '';
    $date_added = $extra['date_added'] ?? '';

    switch ($invoice_id) {
      case 1: // Order
        $temp = str_replace('&nbsp;', ' ', 'No ' . $status_name . ' : ');
        $pdf->Text(10, 113, $temp . $oID);
        break;
      case 2: // Invoice
        $temp = str_replace('&nbsp;', ' ', 'No ' . $status_name . ' : ' . DateTime::toDateReferenceShort($date_added) . 'S');
        $pdf->Text(10, 113, $temp . $oID);
        break;
      case 3: // Cancelled
        $temp = str_replace('&nbsp;', ' ', $status_name . ': ');
        $pdf->Text(10, 113, $temp);
        break;
      default:
        $temp = str_replace('&nbsp;', ' ', 'No ' . $status_name . ': ');
        $pdf->Text(10, 113, $temp . $oID);
        break;
    }
  }

  /**
   * Render the order date text in the center of the order box.
   */
  private function renderOrderDateText(\FPDF $pdf, int $invoice_id, array $extra): void
  {
    $date_purchased = $extra['date_purchased'] ?? '';
    $status_name = $extra['orders_status_invoice_name'] ?? '';

    switch ($invoice_id) {
      case 1:
      case 2:
      case 0:
        $temp = str_replace('&nbsp;', ' ', $this->getDef('print_order_date') . ' ' . $status_name . ' : ');
        $pdf->Text(60, 113, $temp . DateTime::toShort($date_purchased ?? ''));
        break;
      case 3:
        $temp = '';
        $pdf->Text(60, 113, $temp);
        break;
    }
  }

  /**
   * Render product table rows.
   */
  private function renderProducts(\FPDF $pdf, object $order, array $extra, array $cfg, int &$Y_Table_Position): void
  {
    $item_count = 0;

    for ($i = 0, $n = count($order->products); $i < $n; $i++) {
      // -- Quantity --
      $pdf->SetFont('Arial', '', 7);
      $pdf->SetY($Y_Table_Position);
      $pdf->SetX($cfg['qty_x']);
      $pdf->MultiCell($cfg['qty_width'], 6, $order->products[$i]['qty'], 1, 'C');

      // -- Attributes --
      $prod_attribs = '';
      if (isset($order->products[$i]['attributes']) && count($order->products[$i]['attributes']) > 0) {
        foreach ($order->products[$i]['attributes'] as $attr) {
          $reference = !empty($attr['reference']) ? $attr['reference'] . ' / ' : '';
          $prod_attribs .= " - " . $attr['option'] . ' (' . $reference . '): ' . $attr['value'];
        }
      }

      $product_name_attrib_contact = $order->products[$i]['name'] . $prod_attribs;

      // -- Product name --
      $pdf->SetY($Y_Table_Position);
      $pdf->SetX($cfg['name_x']);
      $pdf->SetFont('Arial', '', 6);
      $name_len = strlen($product_name_attrib_contact);
      if ($name_len > $cfg['name_trunc']) {
        $pdf->MultiCell($cfg['name_width'], 6, mb_convert_encoding(substr($product_name_attrib_contact, 0, $cfg['name_trunc']), 'ISO-8859-1', 'UTF-8') . " .. ", 1, 'L');
      } else {
        $pdf->MultiCell($cfg['name_width'], 6, mb_convert_encoding($product_name_attrib_contact, 'ISO-8859-1', 'UTF-8'), 1, 'L');
        $pdf->Ln();
      }

      // -- Model --
      $pdf->SetY($Y_Table_Position);
      $pdf->SetX($cfg['model_x']);
      $pdf->SetFont('Arial', '', 7);
      $pdf->MultiCell($cfg['model_width'], 6, mb_convert_encoding($order->products[$i]['model'], 'ISO-8859-1', 'UTF-8'), 1, 'C');

      // -- Tax (invoice only) --
      if ($cfg['tax_width'] > 0) {
        $pdf->SetFont('Arial', '', 7);
        $pdf->SetY($Y_Table_Position);
        $pdf->SetX($cfg['tax_x']);
        $pdf->MultiCell($cfg['tax_width'], 6, Tax::displayTaxRateValue($order->products[$i]['tax']), 1, 'C');

        // -- Price HT (invoice only) --
        $pdf->SetY($Y_Table_Position);
        $pdf->SetX($cfg['price_x']);
        $pdf->SetFont('Arial', '', 7);
        $price = $order->products[$i]['final_price'];
        $currency = $order->info['currency'] ?? '';
        $currency_value = $order->info['currency_value'] ?? 1;
        $pdf->MultiCell($cfg['price_width'], 6, mb_convert_encoding(html_entity_decode(CLICSHOPPING_Currencies::format($price, true, $currency, $currency_value)), 'ISO-8859-1', 'UTF-8'), 1, 'C');

        // -- Total HT (invoice only) --
        $pdf->SetY($Y_Table_Position);
        $pdf->SetX($cfg['total_x']);
        $total = $order->products[$i]['final_price'] * $order->products[$i]['qty'];
        $pdf->MultiCell($cfg['total_width'], 6, mb_convert_encoding(html_entity_decode(CLICSHOPPING_Currencies::format($total, true, $currency, $currency_value)), 'ISO-8859-1', 'UTF-8'), 1, 'C');
      }

      $Y_Table_Position += 6;

      // -- Page overflow --
      $item_count++;
      if (($item_count % 32 === 0 && $i >= 20) || ($i === 20)) {
        $pdf->AddPage();
        $Y_Fields_Name_position = 125;
        $Y_Table_Position = 70;
        PDF::outputTableHeadingPdf($Y_Table_Position - 6);
        if ($i === 20) $item_count = 1;
      }
    }
  }

  /**
   * Render order totals.
   */
  private function renderTotals(\FPDF $pdf, object $order, array $cfg, int &$Y_Table_Position): void
  {
    for ($i = 0, $n = count($order->totals); $i < $n; $i++) {
      $pdf->SetY($Y_Table_Position + 5);

      // Align right for invoice, adjust for packingslip
      $x_pos = $cfg['tax_width'] > 0 ? 102 : 60;
      $pdf->SetX($x_pos);

      $temp = substr($order->totals[$i]['text'], 0, 3);
      if ($temp === '<strong>') {
        $pdf->SetFont('Arial', 'B', 7);
        $temp2 = substr($order->totals[$i]['text'], 3);
        $order->totals[$i]['text'] = substr($temp2, 0, strlen($temp2) - 4);
      }

      $width = $cfg['tax_width'] > 0 ? 94 : 140;
      $pdf->MultiCell($width, 6, substr(mb_convert_encoding(html_entity_decode($order->totals[$i]['title']), 'ISO-8859-1', 'UTF-8'), 0, 30) . ' ' . mb_convert_encoding(html_entity_decode($order->totals[$i]['text']), 'ISO-8859-1', 'UTF-8'), 0, 'R');
      $Y_Table_Position += 5;
    }
  }

  /**
   * Render footer (legal, company info, thank you).
   */
  private function renderFooter(\FPDF $pdf): void
  {
    $pdf->Cell(50);
    $pdf->SetY(-67);
    $pdf->SetDrawColor(153, 153, 153);
    $pdf->Cell(185, .1, '', 1, 1, 'L', 1);

    // Thank you
    $pdf->SetY(-65);
    $pdf->SetFont('Arial', 'B', 8);
    SetTextColorRGB($pdf, INVOICE_RGB);
    $pdf->Cell(0, 10, mb_convert_encoding($this->getDef('thank_you_customer'), 'ISO-8859-1', 'UTF-8'), 0, 0, 'C');

    // Legal
    $pdf->SetY(-60);
    $pdf->SetFont('Arial', '', 7);
    SetTextColorRGB($pdf, INVOICE_RGB);
    $pdf->Cell(0, 10, mb_convert_encoding($this->getDef('reserve_propriete', ['store_name' => STORE_NAME]), 'ISO-8859-1', 'UTF-8'), 0, 0, 'C');

    $pdf->SetY(-55);
    $pdf->SetFont('Arial', '', 7);
    SetTextColorRGB($pdf, INVOICE_RGB);
    $pdf->Cell(0, 10, mb_convert_encoding($this->getDef('reserve_propriete_next'), 'ISO-8859-1', 'UTF-8'), 0, 0, 'C');

    $pdf->SetY(-50);
    $pdf->SetFont('Arial', '', 7);
    SetTextColorRGB($pdf, INVOICE_RGB);
    $pdf->Cell(0, 10, mb_convert_encoding($this->getDef('reserve_propriete_next1', ['url_sell_conditions' => HTTP::getShopUrlDomain() . SHOP_CODE_URL_CONDITIONS_VENTE]), 'ISO-8859-1', 'UTF-8'), 0, 0, 'C');

    // Company info
    if (DISPLAY_DOUBLE_TAXE === 'false') {
      $pdf->SetY(-45);
      $pdf->SetFont('Arial', '', 8);
      SetTextColorRGB($pdf, INVOICE_RGB);
      $pdf->Cell(0, 10, mb_convert_encoding($this->getDef('entry_info_societe', ['info_societe' => SHOP_CODE_CAPITAL . ' - ' . SHOP_CODE_RCS . ' - ' . SHOP_CODE_APE]), 'ISO-8859-1', 'UTF-8'), 0, 0, 'C');

      $pdf->SetY(-40);
      $pdf->SetFont('Arial', '', 8);
      SetTextColorRGB($pdf, INVOICE_RGB);
      $pdf->Cell(0, 10, mb_convert_encoding($this->getDef('entry_info_societe_next', ['tva_intracom' => TVA_SHOP_INTRACOM]), 'ISO-8859-1', 'UTF-8'), 0, 0, 'C');
    } else {
      $pdf->SetY(-45);
      $pdf->SetFont('Arial', '', 8);
      SetTextColorRGB($pdf, INVOICE_RGB);
      $pdf->Cell(0, 10, mb_convert_encoding($this->getDef('entry_info_societe1', ['info_societe1' => SHOP_CODE_CAPITAL . ' - ' . SHOP_CODE_RCS . ' - ' . SHOP_CODE_APE]), 'ISO-8859-1', 'UTF-8'), 0, 0, 'C');

      $pdf->SetY(-40);
      $pdf->SetFont('Arial', '', 8);
      SetTextColorRGB($pdf, INVOICE_RGB);
      $pdf->Cell(0, 10, mb_convert_encoding($this->getDef('entry_info_societe_next1', ['info_societe1' => TVA_SHOP_PROVINCIAL . ' - ' . TVA_SHOP_FEDERAL]), 'ISO-8859-1', 'UTF-8'), 0, 0, 'C');
    }

    // Misc
    $pdf->SetY(-35);
    $pdf->SetFont('Arial', '', 8);
    SetTextColorRGB($pdf, INVOICE_RGB);
    $pdf->Cell(0, 10, mb_convert_encoding(SHOP_DIVERS, 'ISO-8859-1', 'UTF-8'), 0, 0, 'C');
  }

  // ---- Helpers ----

  private function getDef(string $key, array $params = []): string
  {
    // Try Orders module def first, then fallback to CLICSHOPPING
    $Orders = Registry::get('Orders', false);
    if ($Orders !== null) {
      return $Orders->getDef($key, $params);
    }
    return CLICSHOPPING::getDef($key, $params);
  }

  private function getInvoiceLogo(): string|false
  {
    $Orders = Registry::get('Orders', false);
    if ($Orders !== null && method_exists($Orders, 'getOrderPdfInvoiceLogo')) {
      return $Orders->getOrderPdfInvoiceLogo();
    }
    // Fallback
    return false;
  }
}
