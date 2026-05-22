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
use ClicShopping\OM\DateTime;
use ClicShopping\OM\Hash;
use ClicShopping\OM\HTML;
use ClicShopping\OM\HTTP;
use ClicShopping\OM\Registry;

use ClicShopping\Apps\Orders\Orders\Classes\ClicShoppingAdmin\OrderAdmin as OrderAdminPackingSlip;
use ClicShopping\Sites\Common\PDF;

$CLICSHOPPING_Template = Registry::get('TemplateAdmin');
$CLICSHOPPING_Orders = Registry::get('Db');
$CLICSHOPPING_Language = Registry::get('Language');
$CLICSHOPPING_Orders = Registry::get('Orders');
$CLICSHOPPING_Address = Registry::get('Address');

define('FPDF_FONTPATH', CLICSHOPPING::BASE_DIR . 'External/vendor/setasign/fpdf/font/');
require_once(CLICSHOPPING::BASE_DIR . 'External/vendor/setasign/fpdf/fpdf.php');

/**
 * Helper to set text color from a comma-separated RGB string (e.g. '158,11,14').
 * FPDF::SetTextColor() expects three separate numeric arguments.
 */
function SetTextColorRGB(\FPDF $pdf, string $rgb): void
{
  $parts = array_map('intval', explode(',', $rgb));
  $pdf->SetTextColor($parts[0] ?? 0, $parts[1] ?? 0, $parts[2] ?? 0);
}

$pdf = new \FPDF();

Registry::set('PDF', new PDF());
$PDF = Registry::get('PDF');

// Recuperation de la valeur no id de order.php
if (isset($_GET['oID'])) {
  $oID = HTML::sanitize($_GET['oID']);

  if (\is_null($oID)) {
    $CLICSHOPPING_Orders->redirect('Orders');
  }
} else {
  $CLICSHOPPING_Orders->redirect('Orders');
}

// Recuperations de la facture

$QordersInfo = $CLICSHOPPING_Orders->db->prepare('select orders_id,
                                                           customers_id
                                                    from :table_orders
                                                    where orders_id = :orders_id
                                                   ');
$QordersInfo->bindInt(':orders_id', (int)$oID);
$QordersInfo->execute();

if ($QordersInfo->fetch() === false) {
  $CLICSHOPPING_Orders->redirect('Orders');
}

// Recuperations de la date de la facture (Voir aussi french.php & invoice.php)
$QordersHistory = $CLICSHOPPING_Orders->db->prepare('select orders_status_id,
                                                               date_added,
                                                               customer_notified,
                                                               orders_status_invoice_id,
                                                               comments
                                                       from :table_orders_status_history
                                                       where orders_id = :orders_id
                                                       order by date_added desc
                                                       limit 1
                                                      ');
$QordersHistory->bindInt(':orders_id', (int)$oID);
$QordersHistory->execute();

$orders_history_display = $QordersHistory->valueInt('orders_status_invoice_id');

// Recuperations du nom du type de facture generee

$QordersStatusInvoice = $CLICSHOPPING_Orders->db->prepare('select orders_status_invoice_id,
                                                                    orders_status_invoice_name,
                                                                    language_id
                                                             from :table_orders_status_invoice
                                                             where orders_status_invoice_id = :orders_status_invoice_id
                                                             and language_id = :language_id
                                                           ');
$QordersStatusInvoice->bindInt(':orders_status_invoice_id', (int)$orders_history_display);
$QordersStatusInvoice->bindInt(':language_id', (int)$CLICSHOPPING_Language->getId());
$QordersStatusInvoice->execute();

$order_status_invoice_display = $QordersStatusInvoice->value('orders_status_invoice_name');

$QstatusOrder = $CLICSHOPPING_Orders->db->prepare('select orders_status,
                                                     date_purchased
                                                     from :table_orders
                                                     where orders_id = :orders_id
                                                   ');
$QstatusOrder->bindInt(':orders_id', (int)$oID);
$QstatusOrder->execute();

$status_order = $QstatusOrder->fetch();
$order_date_purchased = $QstatusOrder->value('date_purchased');

Registry::set('Order', new OrderAdminPackingSlip($oID));
$order = Registry::get('Order');

//Instanciation of inherited class
// Classe pdf.php
$pdf = new \FPDF();

// Set the Page Margins
// Marge de la page
$pdf->SetMargins(10, 2, 6);

// Add the first page
// Ajoute page
$pdf->AddPage();

if (DISPLAY_INVOICE_HEADER == 'false') {
// Logo
  if (OrderAdminPackingSlip::getOrderPdfInvoiceLogo() !== false) {
    $pdf->Image(OrderAdminPackingSlip::getOrderPdfInvoiceLogo(), 5, 10, 50);
  }

  // Nom de la compagnie
  $pdf->SetX(0);
  $pdf->SetY(10);
  $pdf->SetFont('Arial', 'B', 10);
  SetTextColorRGB($pdf, INVOICE_RGB);
  $pdf->Ln(0);
  $pdf->Cell(125);
  $pdf->MultiCell(100, 3.5, mb_convert_encoding(STORE_NAME, 'ISO-8859-1', 'UTF-8'), 0, 'L');

  // Adresse de la compagnie
  $pdf->SetX(0);
  $pdf->SetY(15);
  $pdf->SetFont('Arial', '', 8);
  SetTextColorRGB($pdf, INVOICE_RGB);
  $pdf->Ln(0);
  $pdf->Cell(125);
  $pdf->MultiCell(100, 3.5, mb_convert_encoding(STORE_NAME_ADDRESS, 'ISO-8859-1', 'UTF-8'), 0, 'L');

  // Email
  $pdf->SetX(0);
  $pdf->SetY(30);
  $pdf->SetFont('Arial', '', 8);
  SetTextColorRGB($pdf, INVOICE_RGB);
  $pdf->Ln(0);
  $pdf->Cell(-3);
  $pdf->MultiCell(100, 3.5, mb_convert_encoding($CLICSHOPPING_Orders->getDef('entry_email'), 'ISO-8859-1', 'UTF-8') . ' ' . STORE_OWNER_EMAIL_ADDRESS, 0, 'L');

  // Website
  $pdf->SetX(0);
  $pdf->SetY(34);
  $pdf->SetFont('Arial', '', 8);
  SetTextColorRGB($pdf, INVOICE_RGB);
  $pdf->Ln(0);
  $pdf->Cell(-3);
  $pdf->MultiCell(100, 3.5, $CLICSHOPPING_Orders->getDef('entry_http_site') . ' ' . HTTP::typeUrlDomain(), 0, 'L');
}

// Ligne de pliage pour mise en enveloppe
$pdf->Cell(-5);
$pdf->SetY(103);
$pdf->SetX(0);
$pdf->SetDrawColor(220, 220, 220);
$pdf->Cell(3, .1, '', 1, 1, '', 1);

// Cadre pour l'adresse de facturation
$pdf->SetDrawColor(0);
$pdf->SetLineWidth(0.2);
$pdf->SetFillColor(245);
$PDF->roundedRect(6, 40, 90, 35, 2, 'DF');

//Draw the invoice address text
// Adresse de facturation
$pdf->SetFont('Arial', 'B', 8);
$pdf->SetTextColor(0);
$pdf->Text(11, 44, $CLICSHOPPING_Orders->getDef('entry_sold_to'));
$pdf->SetX(0);
$pdf->SetY(47);
$pdf->Cell(9);
$pdf->MultiCell(70, 3.3, mb_convert_encoding($CLICSHOPPING_Address->addressFormat($order->customer['format_id'], $order->customer, '', '', "\n"), 'ISO-8859-1', 'UTF-8'), 0, 'L');

//Draw Box for Delivery Address
// Cadre pour l'adresse de livraison
$pdf->SetDrawColor(0);
$pdf->SetLineWidth(0.2);
$pdf->SetFillColor(255);
$PDF->roundedRect(108, 40, 90, 35, 2, 'DF');

//Draw the invoice delivery address text
// Adresse de livraison
$pdf->SetFont('Arial', 'B', 8);
$pdf->SetTextColor(0);
$pdf->Text(113, 44, mb_convert_encoding($CLICSHOPPING_Orders->getDef('entry_ship_to'), 'ISO-8859-1', 'UTF-8'));
$pdf->SetX(0);
$pdf->SetY(47);
$pdf->Cell(111);
$pdf->MultiCell(70, 3.3, mb_convert_encoding($CLICSHOPPING_Address->addressFormat($order->delivery['format_id'], $order->delivery, '', '', "\n"), 'ISO-8859-1', 'UTF-8'), 0, 'L');

// Information client
$pdf->SetFont('Arial', 'B', 8);
$pdf->SetTextColor(0);
$pdf->Text(10, 85, $CLICSHOPPING_Orders->getDef('entry_customer_information'));

//  email
// Email du client
$pdf->SetFont('Arial', '', 8);
$pdf->SetTextColor(0);
$pdf->Text(15, 90, $CLICSHOPPING_Orders->getDef('entry_email') . ' ' . $order->customer['email_address']);

//  Customer Number
// Numero de client
$pdf->SetFont('Arial', '', 8);
$pdf->SetTextColor(0);
$pdf->Text(15, 95, mb_convert_encoding($CLICSHOPPING_Orders->getDef('entry_customer_number'), 'ISO-8859-1', 'UTF-8') . ' ' . $QordersInfo->valueInt('customers_id'));

//  Customer phone
// Telephone du client
$pdf->SetFont('Arial', '', 8);
$pdf->SetTextColor(0);
$pdf->Text(15, 100, mb_convert_encoding($CLICSHOPPING_Orders->getDef('entry_phone'), 'ISO-8859-1', 'UTF-8') . ' ' . Hash::displayDecryptedDataText($order->customer['telephone']));

//Draw Box for Order Number, Date & Payment method
// Cadre du numero de commande, date de commande et methode de paiemenent
$pdf->SetDrawColor(0);
$pdf->SetLineWidth(0.2);
$pdf->SetFillColor(245);
$PDF->roundedRect(6, 107, 192, 11, 2, 'DF');

// Order management
if (($QordersHistory->valueInt('orders_status_invoice_id') == 1)) {
// Display the order
  $temp = str_replace('&nbsp;', ' ', 'No ' . $order_status_invoice_display . ' : ');
  $pdf->Text(10, 113, $temp . $oID);
} elseif ($QordersHistory->valueInt('orders_status_invoice_id') == 2) {
//Display the invoice
  $temp = str_replace('&nbsp;', ' ', 'No ' . $order_status_invoice_display . ' : ' . DateTime::toDateReferenceShort($QordersHistory->value('date_added')) . 'S');
  $pdf->Text(10, 113, $temp . $oID);
} elseif ($QordersHistory->valueInt('orders_status_invoice_id') == 3) {
//Display the cancelling
  $temp = str_replace('&nbsp;', ' ', $order_status_invoice_display . ': ');
  $pdf->Text(10, 113, $temp);
} else {
// Display the order
  $temp = str_replace('&nbsp;', ' ', 'No ' . $order_status_invoice_display . ': ');
  $pdf->Text(10, 113, $temp . $oID);
}

// Center information order management
if (($QordersHistory->valueInt('orders_status_invoice_id') == 1)) {
// Display the order
  $temp = str_replace('&nbsp;', ' ', $CLICSHOPPING_Orders->getDef('print_order_date') . ' ' . $order_status_invoice_display . ' : ');
    $pdf->Text(55, 113, $temp . DateTime::toShort($order_date_purchased ?? ''));
} elseif ($QordersHistory->valueInt('orders_status_invoice_id') == 2) {
//Display the invoice
  $temp = str_replace('&nbsp;', ' ', $CLICSHOPPING_Orders->getDef('print_order_date') . ' ' . $order_status_invoice_display . ' : ');
    $pdf->Text(55, 113, $temp . DateTime::toShort($order_date_purchased ?? ''));
} elseif ($QordersHistory->valueInt('orders_status_invoice_id') == 3) {
//Display the cancelling
  $temp = str_replace('&nbsp;', ' ', '');
  $pdf->Text(55, 113, $temp);
} else {
// Display the order
  $temp = str_replace('&nbsp;', ' ', $CLICSHOPPING_Orders->getDef('print_order_date') . ' ' . $order_status_invoice_display . ' : ');
    $pdf->Text(55, 113, $temp . DateTime::toShort($order_date_purchased ?? ''));
}

//Draw Payment Method Text
$temp = substr(mb_convert_encoding($order->info['payment_method'], 'ISO-8859-1', 'UTF-8'), 0, 60);
$pdf->Text(110, 113, mb_convert_encoding($CLICSHOPPING_Orders->getDef('text_payment_method'), 'ISO-8859-1', 'UTF-8') . ' ' . $temp);

// Cadre pour afficher "BON DE COMMANDE" ou "FACTURE"
$pdf->SetDrawColor(0);
$pdf->SetLineWidth(0.2);
$pdf->SetFillColor(245);
$PDF->roundedRect(108, 32, 90, 7, 2, 'DF');

// Affichage titre "BON DE COMMANDE" ou "FACTURE"
$pdf->SetFont('Arial', '', 10);
$pdf->SetY(32);
$pdf->SetX(108);
$pdf->MultiCell(90, 7, $order_status_invoice_display, 0, 'C');

// Fields Name position
$Y_Fields_Name_position = 125;

// Table position, under Fields Name
$Y_Table_Position = 131;

// Entete du tableau des produits
$PDF->outputTableHeadingPackingslip($Y_Fields_Name_position);

// Boucle sur les produits
// Show the products information line by line
$item_count = 0;

for ($i = 0, $n = \count($order->products); $i < $n; $i++) {

// Quantity
  $pdf->SetFont('Arial', '', 7);
  $pdf->SetY($Y_Table_Position);
  $pdf->SetX(6);
  $pdf->MultiCell(14, 6, $order->products[$i]['qty'], 1, 'C');

// Attribut management and Product Name
  $prod_attribs = '';

  // Get attribs and concat
  if ((isset($order->products[$i]['attributes'])) && (\count($order->products[$i]['attributes']) > 0)) {
    for ($j = 0, $n2 = \count($order->products[$i]['attributes']); $j < $n2; $j++) {
      $prod_attribs .= " - " . $order->products[$i]['attributes'][$j]['option'] . ': ' . $order->products[$i]['attributes'][$j]['value'];
    }
}

  $product_name_attrib_contact = $order->products[$i]['name'] . $prod_attribs;

//	product name
// Nom du produit
  $pdf->SetY($Y_Table_Position);
  $pdf->SetX(60);
  if (\strlen($product_name_attrib_contact) > 40 && \strlen($product_name_attrib_contact) < 70) {
    $pdf->SetFont('Arial', '', 6);
    $pdf->MultiCell(138, 6, mb_convert_encoding($product_name_attrib_contact, 'ISO-8859-1', 'UTF-8'), 1, 'L');
  } elseif (\strlen($product_name_attrib_contact) > 70) {
    $pdf->SetFont('Arial', '', 6);
    $pdf->MultiCell(138, 6, mb_convert_encoding(substr($product_name_attrib_contact, 0, 70), 'ISO-8859-1', 'UTF-8') . " .. ", 1, 'L');
  } else {
    $pdf->SetFont('Arial', '', 6);
    $pdf->MultiCell(138, 6, mb_convert_encoding($product_name_attrib_contact, 'ISO-8859-1', 'UTF-8'), 1, 'L');
    $pdf->Ln();
  }

// Model
  $pdf->SetY($Y_Table_Position);
  $pdf->SetX(20);
  $pdf->SetFont('Arial', '', 7);
  $pdf->MultiCell(40, 6, mb_convert_encoding($order->products[$i]['model'], 'ISO-8859-1', 'UTF-8'), 1, 'C');
  $Y_Table_Position += 6;

// Check for product line overflow
  $item_count++;
  if ((is_long($item_count / 32) && $i >= 20) || ($i == 20)) {
    $pdf->AddPage();
// Fields Name position
    $Y_Fields_Name_position = 125;
// Table position, under Fields Name
    $Y_Table_Position = 70;
    $PDF->OutputTableHeadingPackingslip($Y_Table_Position - 6);

    if ($i == 20) $item_count = 1;
  }
}

//Draw the bottom line with invoice text
// Ligne pour le pied de page
if (DISPLAY_INVOICE_FOOTER == 'false') {
  $pdf->Cell(50);
  $pdf->SetY(-67);
  $pdf->SetDrawColor(153, 153, 153);
  $pdf->Cell(185, .1, '', 1, 1, 'L', 1);

  // Remerciement
  $pdf->SetY(-65);
  $pdf->SetFont('Arial', 'B', 8);
  SetTextColorRGB($pdf, INVOICE_RGB);
  $pdf->Cell(0, 10, mb_convert_encoding($CLICSHOPPING_Orders->getDef('thank_you_customer'), 'ISO-8859-1', 'UTF-8'), 0, 0, 'C');

// Proprieties Legal
  $pdf->SetY(-60);
  $pdf->SetFont('Arial', '', 7);
  SetTextColorRGB($pdf, INVOICE_RGB);
  $pdf->Cell(0, 10, mb_convert_encoding($CLICSHOPPING_Orders->getDef('reserve_propriete', ['store_name' => STORE_NAME]), 'ISO-8859-1', 'UTF-8'), 0, 0, 'C');

  $pdf->SetY(-55);
  $pdf->SetFont('Arial', '', 7);
  SetTextColorRGB($pdf, INVOICE_RGB);
  $pdf->Cell(0, 10, mb_convert_encoding($CLICSHOPPING_Orders->getDef('reserve_propriete_next'), 'ISO-8859-1', 'UTF-8'), 0, 0, 'C');

  $pdf->SetY(-50);
  $pdf->SetFont('Arial', '', 7);
  SetTextColorRGB($pdf, INVOICE_RGB);
  $pdf->Cell(0, 10, mb_convert_encoding($CLICSHOPPING_Orders->getDef('reserve_propriete_next1', ['url_sell_conditions' => HTTP::getShopUrlDomain() . SHOP_CODE_URL_CONDITIONS_VENTE]), 'ISO-8859-1', 'UTF-8'), 0, 0, 'C');

// Informations de la compagnie
  if (DISPLAY_DOUBLE_TAXE == 'false') {
    $pdf->SetY(-45);
    $pdf->SetFont('Arial', '', 8);
    SetTextColorRGB($pdf, INVOICE_RGB);
    $pdf->Cell(0, 10, mb_convert_encoding($CLICSHOPPING_Orders->getDef('entry_info_societe', ['info_societe' => SHOP_CODE_CAPITAL . ' - ' . SHOP_CODE_RCS . ' - ' . SHOP_CODE_APE]), 'ISO-8859-1', 'UTF-8'), 0, 0, 'C');

    $pdf->SetY(-40);
    $pdf->SetFont('Arial', '', 8);
    SetTextColorRGB($pdf, INVOICE_RGB);
    $pdf->Cell(0, 10, mb_convert_encoding($CLICSHOPPING_Orders->getDef('entry_info_societe_next', ['tva_intracom' => TVA_SHOP_INTRACOM]), 'ISO-8859-1', 'UTF-8'), 0, 0, 'C');
  } else {
    $pdf->SetY(-45);
    $pdf->SetFont('Arial', '', 8);
    SetTextColorRGB($pdf, INVOICE_RGB);
    $pdf->Cell(0, 10, mb_convert_encoding($CLICSHOPPING_Orders->getDef('entry_info_societe1', ['info_societe1' => SHOP_CODE_CAPITAL . ' - ' . SHOP_CODE_RCS . ' - ' . SHOP_CODE_APE]), 'ISO-8859-1', 'UTF-8'), 0, 0, 'C');

    $pdf->SetY(-40);
    $pdf->SetFont('Arial', '', 8);
    SetTextColorRGB($pdf, INVOICE_RGB);
    $pdf->Cell(0, 10, mb_convert_encoding($CLICSHOPPING_Orders->getDef('entry_info_societe_next1', ['info_societe1' => TVA_SHOP_PROVINCIAL . ' - ' . TVA_SHOP_FEDERAL]), 'ISO-8859-1', 'UTF-8'), 0, 0, 'C');
  }

// Autres informations (champ libre) sur la compagnie
  $pdf->SetY(-35);
  $pdf->SetFont('Arial', '', 8);
  SetTextColorRGB($pdf, INVOICE_RGB);
  $pdf->Cell(0, 10, mb_convert_encoding(SHOP_DIVERS, 'ISO-8859-1', 'UTF-8'), 0, 0, 'C');
}

// PDF's created now output the file
$pdf->Output();
