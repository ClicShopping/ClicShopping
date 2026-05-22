<?php
/**
 *
 * @copyright 2008 - https://www.clicshopping.org
 * @Brand : ClicShoppingAI(TM) at Inpi all right Reserved
 * @Licence GPL 2 & MIT
 * @Info : https://www.clicshopping.org/forum/trademark/
 *
 */

namespace ClicShopping\Sites\Shop\Pages\Account\Actions;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\DateTime;
use ClicShopping\OM\Hash;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;
use ClicShopping\Sites\Common\PDF;
use ClicShopping\Sites\Shop\Tax;
use pdfInvoice;
use function count;
use function is_null;
use function strlen;

class OrderInvoice extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {
    $CLICSHOPPING_Customer = Registry::get('Customer');
    $CLICSHOPPING_Currencies = Registry::get('Currencies');
    $CLICSHOPPING_NavigationHistory = Registry::get('NavigationHistory');
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_Language = Registry::get('Language');
    $CLICSHOPPING_Address = Registry::get('Address');
    $CLICSHOPPING_Order = Registry::get('Order');
    $CLICSHOPPING_Hooks = Registry::get('Hooks');

    $CLICSHOPPING_Hooks->call('OrderInvoice', 'PreAction');

    if (!$CLICSHOPPING_Customer->isLoggedOn()) {
      $CLICSHOPPING_NavigationHistory->setSnapshot();
      CLICSHOPPING::redirect(null, 'Account&LogIn');
    }

    define('FPDF_FONTPATH', CLICSHOPPING::BASE_DIR . 'External/vendor/setasign/fpdf/font/');
    require_once(CLICSHOPPING::BASE_DIR . 'External/vendor/setasign/fpdf/fpdf.php');

    require_once('Core/ClicShopping/Sites/Common/pdfInvoice.php');

    $pdf = new pdfInvoice();
    $_SESSION['pdf'] = $pdf;

    $CLICSHOPPING_Language->loadDefinitions('orders_invoice');

// Recuperation de la valeur id de order.php
    if (isset($_GET['order_id'])) {
      $oID = HTML::sanitize((int)$_GET['order_id']);

      if (is_null($oID)) {
        CLICSHOPPING::redirect(null, 'Account&Main');
      }
    } else {
      CLICSHOPPING::redirect(null, 'Account&Main');
    }

    $QordersInfo = $CLICSHOPPING_Db->prepare('select orders_id,
                                                       customers_id
                                                from :table_orders
                                                where orders_id = :orders_id
                                               ');
    $QordersInfo->bindInt(':orders_id', (int)$oID);
    $QordersInfo->execute();

    if ($QordersInfo->fetch() === false) {
      CLICSHOPPING::redirect(null, 'Account&Main');
    }

    $QordersHistory = $CLICSHOPPING_Db->prepare('select orders_status_id,
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

    $QOrdersStatusInvoice = $CLICSHOPPING_Db->prepare('select orders_status_invoice_id,
                                                                orders_status_invoice_name,
                                                                language_id
                                                         from :table_orders_status_invoice
                                                         where orders_status_invoice_id = :orders_status_invoice_id
                                                         and language_id = :language_id
                                                        ');
    $QOrdersStatusInvoice->bindInt(':orders_status_invoice_id', (int)$orders_history_display);
    $QOrdersStatusInvoice->bindInt(':language_id', (int)$CLICSHOPPING_Language->getId());
    $QOrdersStatusInvoice->execute();

    $order_status_invoice_display = $QOrdersStatusInvoice->value('orders_status_invoice_name');

    $QstatusOrder = $CLICSHOPPING_Db->prepare('select orders_status,
                                                       date_purchased,
                                                       payment_method
                                                from :table_orders
                                                where orders_id = :orders_id
                                               ');
    $QstatusOrder->bindInt(':orders_id', (int)$oID);
    $QstatusOrder->execute();

    $order_date_purchased = $QstatusOrder->value('date_purchased');
    $order_payment_method = $QstatusOrder->value('payment_method');
// Set the Page Margins
// Marge de la page
    $pdf->SetMargins(10, 2, 6);

// Add the first page
// Ajoute page
    $pdf->AddPage();

//Draw the bottom line with invoice text
// Ligne pour le pied de page
    if (DISPLAY_INVOICE_FOOTER == 'false') {
      $pdf->Cell(50);
      $pdf->SetY(-25);
      $pdf->SetDrawColor(153, 153, 153);
      $pdf->Cell(185, .1, '', 1, 1, 'L', 1);
    }

// Ligne de pliage pour mise en enveloppe
    $pdf->Cell(-5);
    $pdf->SetY(103);
    $pdf->SetX(0);
    $pdf->SetDrawColor(220, 220, 220);
    $pdf->Cell(3, .1, '', 1, 1, '', 1);

// Cadre pour l'adresse de facturation
    /*
          $pdf->SetDrawColor(0);
          $pdf->SetLineWidth(0.2);
          $pdf->SetFillColor(245);
          $pdf->roundedRect(6, 40, 90, 35, 2, 'DF');
    */
//Draw the invoice address text
// Adresse de facturation
    $pdf->SetFont('Arial', 'B', 8);
    $pdf->SetTextColor(0);
    $pdf->Text(11, 44, CLICSHOPPING::getDef('entry_sold_to'));
    $pdf->SetX(0);
    $pdf->SetY(47);
    $pdf->Cell(9);
    $pdf->MultiCell(70, 3.3, mb_convert_encoding($CLICSHOPPING_Address->addressFormat($CLICSHOPPING_Order->customer['format_id'], $CLICSHOPPING_Order->customer, '', '', "\n"), 'ISO-8859-1', 'UTF-8'), 0, 'L');

//Draw Box for Delivery Address
// Cadre pour l'adresse de livraison
    /*
          $pdf->SetDrawColor(0);
          $pdf->SetLineWidth(0.2);
          $pdf->SetFillColor(255);
          $pdf->roundedRect(108, 40, 90, 35, 2, 'DF');
    */
//Draw the invoice delivery address text
// Adresse de livraison
    $pdf->SetFont('Arial', 'B', 8);
    $pdf->SetTextColor(0);
    $pdf->Text(113, 44, CLICSHOPPING::getDef('entry_ship_to'));
    $pdf->SetX(0);
    $pdf->SetY(47);
    $pdf->Cell(111);
    $pdf->MultiCell(70, 3.3, mb_convert_encoding($CLICSHOPPING_Address->addressFormat($CLICSHOPPING_Order->delivery['format_id'], $CLICSHOPPING_Order->delivery, '', '', "\n"), 'ISO-8859-1', 'UTF-8'), 0, 'L');

// Information client
    $pdf->SetFont('Arial', 'B', 8);
    $pdf->SetTextColor(0);
    $pdf->Text(10, 85, CLICSHOPPING::getDef('entry_customer_information'));

//  email
// Email du client
    $pdf->SetFont('Arial', '', 8);
    $pdf->SetTextColor(0);
    $pdf->Text(15, 90, CLICSHOPPING::getDef('entry_email') . ' ' . $CLICSHOPPING_Order->customer['email_address']);

//  Customer Number
// Numero de client
    $pdf->SetFont('Arial', '', 8);
    $pdf->SetTextColor(0);
    $pdf->Text(15, 95, mb_convert_encoding(CLICSHOPPING::getDef('entry_customer_number'), 'ISO-8859-1', 'UTF-8') . ' ' . $QordersInfo->valueInt('customers_id'));

//  Customer phone
// Telephone du client
    $pdf->SetFont('Arial', '', 8);
    $pdf->SetTextColor(0);
    $pdf->Text(15, 100, mb_convert_encoding(CLICSHOPPING::getDef('entry_phone'), 'ISO-8859-1', 'UTF-8') . ' ' . Hash::displayDecryptedDataText($CLICSHOPPING_Order->customer['telephone']));

//Draw Box for Order Number, Date & Payment method
// Cadre du numero de commande, date de commande et methode de paiemenent
    /*
          $pdf->SetDrawColor(0);
          $pdf->SetLineWidth(0.2);
          $pdf->SetFillColor(245);
          $pdf->roundedRect(6, 107, 192, 11, 2, 'DF');
    */
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
      $temp = str_replace('&nbsp;', ' ', CLICSHOPPING::getDef('print_order_date') . ' ' . $order_status_invoice_display . ' : ');
      $pdf->Text(60, 113, $temp . DateTime::toShort($order_date_purchased ?? ''));
    } elseif ($QordersHistory->valueInt('orders_status_invoice_id') == 2) {
//Display the invoice
      $temp = str_replace('&nbsp;', ' ', CLICSHOPPING::getDef('print_order_date') . ' ' . $order_status_invoice_display . ' : ');
      $pdf->Text(60, 113, $temp . DateTime::toShort($order_date_purchased ?? ''));
    } elseif ($QordersHistory->valueInt('orders_status_invoice_id') == 3) {
//Display the cancelling
      $temp = str_replace('&nbsp;', ' ', '');
      $pdf->Text(10, 113, $temp);
    } else {
// Display the order
      $temp = str_replace('&nbsp;', ' ', CLICSHOPPING::getDef('print_order_date') . ' ' . $order_status_invoice_display . ' : ');
      $pdf->Text(60, 113, $temp . DateTime::toShort($order_date_purchased ?? ''));
    }


//Draw Payment Method Text
//      $payment_info = substr(mb_convert_encoding($CLICSHOPPING_Order->info['payment_method'], 'ISO-8859-1', 'UTF-8') , 0, 30);
//      $pdf->Text(120,113, CLICSHOPPING::getDef('entry_payment_method') . ' ' . $payment_info);

    $temp = substr(mb_convert_encoding($order_payment_method, 'ISO-8859-1', 'UTF-8'), 0, 30);
    $pdf->Text(120, 113, CLICSHOPPING::getDef('entry_payment_method') . ' ' . $temp);

// Cadre pour afficher "BON DE COMMANDE" ou "FACTURE"
    /*
          $pdf->SetDrawColor(0);
          $pdf->SetLineWidth(0.2);
          $pdf->SetFillColor(245);
          $pdf->roundedRect(108, 32, 90, 7, 2, 'DF');
    */
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

    PDF::outputTableHeadingPdf($Y_Fields_Name_position);

    $item_count = 0;
// Boucle sur les produits
// Show the products information line by line
    for ($i = 0, $n = count($CLICSHOPPING_Order->products); $i < $n; $i++) {

// Quantity
      $pdf->SetFont('Arial', '', 7);
      $pdf->SetY($Y_Table_Position);
      $pdf->SetX(6);
      $pdf->MultiCell(9, 6, $CLICSHOPPING_Order->products[$i]['qty'], 1, 'C');

// Attribut management and Product Name
      $prod_attribs = '';

      // Get attribs and concat
      if ((isset($CLICSHOPPING_Order->products[$i]['attributes'])) && (count($CLICSHOPPING_Order->products[$i]['attributes']) > 0)) {
        for ($j = 0, $n2 = count($CLICSHOPPING_Order->products[$i]['attributes']); $j < $n2; $j++) {

          if (!empty($CLICSHOPPING_Order->products[$i]['attributes'][$j]['reference'])) {
            $reference = $CLICSHOPPING_Order->products[$i]['attributes'][$j]['reference'] . ' / ';
          }

          $prod_attribs .= ' - ' . $reference . $CLICSHOPPING_Order->products[$i]['attributes'][$j]['option'] . ' : ' . $CLICSHOPPING_Order->products[$i]['attributes'][$j]['value'];
        }
      }

      $product_name_attrib_contact = $CLICSHOPPING_Order->products[$i]['name'] . $prod_attribs;

//product name
// Nom du produit
      $pdf->SetY($Y_Table_Position);
      $pdf->SetX(40);
      if (strlen($product_name_attrib_contact) > 40 && strlen($product_name_attrib_contact) < 95) {
        $pdf->SetFont('Arial', '', 6);
        $pdf->MultiCell(103, 6, mb_convert_encoding($product_name_attrib_contact, 'ISO-8859-1', 'UTF-8'), 1, 'L');
      } elseif (strlen($product_name_attrib_contact) > 95) {
        $pdf->SetFont('Arial', '', 6);
        $pdf->MultiCell(103, 6, mb_convert_encoding(substr($product_name_attrib_contact, 0, 95), 'ISO-8859-1', 'UTF-8') . " .. ", 1, 'L');
      } else {
        $pdf->SetFont('Arial', '', 6);
        $pdf->MultiCell(103, 6, mb_convert_encoding($product_name_attrib_contact, 'ISO-8859-1', 'UTF-8'), 1, 'L');
        $pdf->Ln();
      }

// Model
      $pdf->SetY($Y_Table_Position);
      $pdf->SetX(15);
      $pdf->SetFont('Arial', '', 7);
      $pdf->MultiCell(25, 6, mb_convert_encoding($CLICSHOPPING_Order->products[$i]['model'], 'ISO-8859-1', 'UTF-8'), 1, 'C');

// Taxes
      $pdf->SetFont('Arial', '', 7);
      $pdf->SetY($Y_Table_Position);
      $pdf->SetX(143);
      $pdf->MultiCell(15, 6, Tax::displayTaxRateValue($CLICSHOPPING_Order->products[$i]['tax']), 1, 'C');

// Prix HT
      $pdf->SetY($Y_Table_Position);
      $pdf->SetX(158);
      $pdf->SetFont('Arial', '', 7);
      $pdf->MultiCell(20, 6, mb_convert_encoding(html_entity_decode($CLICSHOPPING_Currencies->format($CLICSHOPPING_Order->products[$i]['final_price'], true, $CLICSHOPPING_Order->info['currency'], $CLICSHOPPING_Order->info['currency_value'])), 'ISO-8859-1', 'UTF-8'), 1, 'C');
      /*
      // Prix TTC
      $pdf->SetY($Y_Table_Position);
      $pdf->SetX(138);
      $pdf->MultiCell(20,6,$currencies->format(Tax::addTax($CLICSHOPPING_Order->products[$i]['final_price'], $CLICSHOPPING_Order->products[$i]['tax']), true, $CLICSHOPPING_Order->info['currency'], $CLICSHOPPING_Order->info['currency_value']),1,'C');
      */
// Total HT
      $pdf->SetY($Y_Table_Position);
      $pdf->SetX(178);
      $pdf->MultiCell(20, 6, mb_convert_encoding(html_entity_decode($CLICSHOPPING_Currencies->format($CLICSHOPPING_Order->products[$i]['final_price'] * $CLICSHOPPING_Order->products[$i]['qty'], true, $CLICSHOPPING_Order->info['currency'], $CLICSHOPPING_Order->info['currency_value'])), 'ISO-8859-1', 'UTF-8'), 1, 'C');
      $Y_Table_Position += 6;
      /*
      // Total TTC
        $pdf->SetY($Y_Table_Position);
        $pdf->SetX(178);
        $pdf->MultiCell(20,6,$CLICSHOPPING_Currencies->format(Tax::addTax($CLICSHOPPING_Order->products[$i]['final_price'], $CLICSHOPPING_Order->products[$i]['tax']) * $CLICSHOPPING_Order->products[$i]['qty'], true, $CLICSHOPPING_Order->info['currency'], $CLICSHOPPING_Order->info['currency_value']),1,'C');
        $Y_Table_Position += 6;
      */
// Check for product line overflow
      $item_count++;

      if ((is_int($item_count / 32) && $i >= 20) || ($i == 20)) {
        $pdf->AddPage();
// Fields Name position
//          $Y_Fields_Name_position = 125;
// Table position, under Fields Name
        $Y_Table_Position = 70;
        PDF::outputTableHeadingPdf($Y_Table_Position - 6);
        if ($i == 20) $item_count = 1;
      }
    }

    for ($i = 0, $n = count($CLICSHOPPING_Order->totals); $i < $n; $i++) {
      $pdf->SetY($Y_Table_Position + 5);
      $pdf->SetX(102);

      $temp = substr($CLICSHOPPING_Order->totals[$i]['text'], 0, 3);

      if ($temp == '<strong>') {
        $pdf->SetFont('Arial', 'B', 7);
        $temp2 = substr($CLICSHOPPING_Order->totals[$i]['text'], 3);
        $CLICSHOPPING_Order->totals[$i]['text'] = substr($temp2, 0, strlen($temp2) - 4);
      }

      $pdf->MultiCell(94, 6, substr(mb_convert_encoding(html_entity_decode($CLICSHOPPING_Order->totals[$i]['title']), 'ISO-8859-1', 'UTF-8'), 0, 30) . ' : ' . mb_convert_encoding(html_entity_decode($CLICSHOPPING_Order->totals[$i]['text']), 'ISO-8859-1', 'UTF-8'), 0, 'R');
      $Y_Table_Position += 5;
    }

// PDF's created now output the file
    $pdf->Output();
  }
}
