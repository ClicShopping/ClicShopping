<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Orders\Orders\Classes\Shop;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\DateTime;
use ClicShopping\OM\Hash;
use ClicShopping\OM\HTML;
use ClicShopping\OM\HTTP;
use ClicShopping\OM\Registry;

use ClicShopping\Sites\Shop\AddressBook;

use ClicShopping\Apps\Configuration\TemplateEmail\Classes\Shop\TemplateEmail;

use function defined;
use function is_array;

/**
 * Sends the transactional e-mails tied to a shop order: the customer order
 * confirmation and the two store-owner stock alerts (sold-out / reorder level).
 *
 * Extracted verbatim from {@see Order} to give the order-confirmation flow a
 * single responsibility. The notifier reads the already-populated order state
 * from the owning {@see Order} instance (its public info/customer/content_type
 * arrays) and resolves its own services (Db, Mail) from the Registry, mirroring
 * the standard ClicShopping pattern.
 */
class OrderNotifier
{
  private mixed $db;
  private mixed $mail;
  private mixed $hooks;
  private Order $order;

  public function __construct(Order $order)
  {
    $this->order = $order;
    $this->db = Registry::get('Db');
    $this->mail = Registry::get('Mail');
    $this->hooks = Registry::get('Hooks');
  }

  /**
   * Sends an email to the customer containing order details, including the order summary,
   * product information, delivery and billing addresses, payment method details,
   * and any additional comments or footer text. Optionally, extra order-related emails
   * can also be sent to specified recipients.
   *
   * @param int $order_id The ID of the order to send the email for.
   * @return void
   */
  public function sendCustomerEmail(int $order_id): void
  {
    $CLICSHOPPING_Customer = Registry::get('Customer');
    $CLICSHOPPING_Currencies = Registry::get('Currencies');

    if (str_contains($_SESSION['payment'], '\\')) {
      $code = 'Payment_' . str_replace('\\', '_', $_SESSION['payment']);

      if (Registry::exists($code)) {
        $CLICSHOPPING_PM = Registry::get($code);
      }
    }

    $Qorder = $this->db->prepare('select *
                                     from :table_orders
                                     where orders_id = :orders_id
                                     limit 1
                                     ');
    $Qorder->bindInt(':orders_id', $order_id);
    $Qorder->execute();

    if ($Qorder->fetch() !== false) {
      $Qproducts = $this->db->prepare('select orders_products_id,
                                                 products_id,
                                                 products_model,
                                                 products_name,
                                                 products_price,
                                                 products_tax,
                                                 products_quantity
                                         from :table_orders_products
                                         where orders_id = :orders_id
                                         order by orders_products_id
                                         ');
      $Qproducts->bindInt(':orders_id', $order_id);
      $Qproducts->execute();

      $message_order = stripslashes(CLICSHOPPING::getDef('entry_text_order_number')) . ' ' . $order_id . "\n" . stripslashes(CLICSHOPPING::getDef('email_text_invoice_url'));

      $email_order = $message_order . ' ' . CLICSHOPPING::link(null, 'Account&HistoryInfo&order_id=' . $order_id) . "\n" . CLICSHOPPING::getDef('email_text_date_ordered') . ' ' . DateTime::strftime(CLICSHOPPING::getDef('date_format_long')) . "\n\n";

      if ($this->order->info['comments']) {
        $email_order .= HTML::outputProtected($this->order->info['comments']) . "\n\n";
      }

      $message_order = stripslashes(CLICSHOPPING::getDef('email_text_products'));

      $email_order .= html_entity_decode($message_order) . "\n" . CLICSHOPPING::getDef('email_separator') . "\n";

      while ($Qproducts->fetch()) {
        if ($CLICSHOPPING_Customer->getCustomersGroupID() != 0) {
          $QproductsModuleCustomersGroup = $this->db->prepare('select products_model_group
                                                                  from :table_products_groups
                                                                  where products_id = :products_id
                                                                  and customers_group_id =  :customers_group_id
                                                                ');

          $QproductsModuleCustomersGroup->bindInt(':products_id', $Qproducts->valueInt('products_id'));
          $QproductsModuleCustomersGroup->bindInt(':customers_group_id', $CLICSHOPPING_Customer->getCustomersGroupID());
          $QproductsModuleCustomersGroup->execute();

          $products_model = $QproductsModuleCustomersGroup->value('products_model_group');

          if (empty($products_model)) {
            $products_model = $Qproducts->value('products_model');
          }

        } else {
          $products_model = $Qproducts->value('products_model');
        }

        $email_order .= $Qproducts->valueInt('products_quantity') . ' x ' . $Qproducts->value('products_name') . ' (' . $products_model . ') = ' . html_entity_decode($CLICSHOPPING_Currencies->displayPrice($Qproducts->value('products_price'), $Qproducts->value('products_tax'), $Qproducts->valueInt('products_quantity'))) . "\n";
      }

      $email_order .= CLICSHOPPING::getDef('email_separator') . "\n";

      $Qtotals = $this->db->prepare('select title,
                                               text
                                       from :table_orders_total
                                       where orders_id = :orders_id
                                       order by sort_order
                                       ');
      $Qtotals->bindInt(':orders_id', $order_id);
      $Qtotals->execute();

      while ($Qtotals->fetch()) {
        $email_order .= strip_tags($Qtotals->value('title') . ' ' . $Qtotals->value('text'));
        $email_order = str_replace('&nbsp;', ' ', $email_order) . "\n";
      }

      if ($this->order->content_type != 'virtual') {
        $message_order = stripslashes(CLICSHOPPING::getDef('email_text_delivery_address'));
        $email_order .= "\n" . $message_order . "\n" . CLICSHOPPING::getDef('email_separator') . "\n" . AddressBook::addressLabel($CLICSHOPPING_Customer->getID(), $_SESSION['sendto'], false, '', "\n") . "\n";
      }

      $message_order = stripslashes(CLICSHOPPING::getDef('email_text_billing_address'));
      $email_order .= "\n" . $message_order . "\n" . CLICSHOPPING::getDef('email_separator') . "\n" . AddressBook::addressLabel($CLICSHOPPING_Customer->getID(), $_SESSION['billto'], false, '', "\n") . "\n\n";

      if (isset($CLICSHOPPING_PM)) {
        $message_order = stripslashes(CLICSHOPPING::getDef('email_text_payment_method'));
        $email_order .= $message_order . "\n" . CLICSHOPPING::getDef('email_separator') . "\n";

        $email_order .= $this->order->info['payment_method'] . "\n\n";

        if (isset($CLICSHOPPING_PM->email_footer)) {
          $email_order .= $CLICSHOPPING_PM->email_footer . "\n\n";
        }
      }

      if (isset($_SESSION['payment'])) {
        if (str_contains($_SESSION['payment'], '\\')) {
          $code = 'Payment_' . str_replace('\\', '_', $_SESSION['payment']);

          if (Registry::exists($code)) {
            $CLICSHOPPING_PM = Registry::get($code);
          }
        }

        if (isset($CLICSHOPPING_PM)) {
          $message_order = stripslashes(CLICSHOPPING::getDef('email_text_payment_method'));
          $email_order .= $message_order . "\n" . CLICSHOPPING::getDef('email_separator') . "\n";

          $payment_class = $CLICSHOPPING_PM;
          $email_order .= $this->order->info['payment_method'] . "\n\n";

          if (isset($payment_class->email_footer)) {
            $email_order .= $payment_class->email_footer . "\n";
          }
        }
      } // end $GLOBALS[$_SESSION['payment']]

      $email_order .= TemplateEmail::getTemplateEmailSignature() . "\n\n";
      $email_order .= TemplateEmail::getTemplateEmailTextFooter() . "\n";

      $to_email_address = Hash::displayDecryptedDataText($this->order->customer['email_address']);
      $to_name = Hash::displayDecryptedDataText($this->order->customer['firstname']) . ' ' . Hash::displayDecryptedDataText($this->order->customer['lastname']);
      $email_subject = CLICSHOPPING::getDef('email_text_subject', ['store_name' => STORE_NAME]);

      $to_addr = $to_email_address;
      $from_name = \defined('STORE_NAME') ? STORE_NAME : '';
      $from_addr = \defined('STORE_OWNER_EMAIL_ADDRESS') ? STORE_OWNER_EMAIL_ADDRESS : '';
      $to_name = $to_name;
      $subject = $email_subject;

      $this->mail->addHtml($email_order);
      $this->mail->send($to_addr, $from_name, $from_addr, $to_name, $subject);

// SEND_EXTRA_ORDER_EMAILS_TO does'nt work like this, test<test@test.com>, just with test@test.com
      if (!empty(\defined('SEND_EXTRA_ORDER_EMAILS_TO') ? SEND_EXTRA_ORDER_EMAILS_TO : '')) {
        $email_text_subject = stripslashes(CLICSHOPPING::getDef('email_text_subject', ['store_name' => STORE_NAME]));
        $email_text_subject = html_entity_decode($email_text_subject);

        if (!empty(SEND_EXTRA_ORDER_EMAILS_TO)) {
          $email[] = TemplateEmail::getExtractEmailAddress(SEND_EXTRA_ORDER_EMAILS_TO);

          // $email is always an array here (built via $email[] above), so the former
          // is_array()/else branch was dead code — B9.
          foreach ($email as $key => $value) {
            $to_addr = $value[$key];
            $from_name = \defined('STORE_NAME') ? STORE_NAME : '';
            $from_addr = \defined('STORE_OWNER_EMAIL_ADDRESS') ? STORE_OWNER_EMAIL_ADDRESS : '';
            $to_name = null;
            $subject = $email_text_subject;

            $this->mail->addHtml($email_order);
            $this->mail->send($to_addr, $from_name, $from_addr, $to_name, $subject);
          }
        }
      }
    }

    $this->hooks->call('OrderNotifier', 'CustomerEmail', ['order_id' => $order_id]);
  }

  /**
   * Sends an email alert to notify store administrators when products are sold out.
   * The alert is triggered based on stock levels and configurable stock checking settings.
   *
   * @param int $insert_id The ID of the order associated with the product stock updates.
   * @return void
   */
  public function sendProductsSoldOutAlert(int $insert_id): void
  {
    $CLICSHOPPING_Prod = Registry::get('Prod');

    if (\defined('STOCK_ALERT_PRODUCT_SOLD_OUT') && STOCK_ALERT_PRODUCT_SOLD_OUT == 'true') {
      $Qproducts = $this->db->prepare('select orders_products_id,
                                                 products_id,
                                                 products_model,
                                                 products_name,
                                                 products_quantity
                                         from :table_orders_products
                                         where orders_id = :orders_id
                                         order by orders_products_id
                                         ');
      $Qproducts->bindInt(':orders_id', $insert_id);
      $Qproducts->execute();

      if ($Qproducts->fetch() !== false) {
        while ($Qproducts->fetch()) {
          $Qstock = $this->db->prepare('select products_quantity_alert,
                                                  products_quantity
                                            from :table_products
                                            where products_id = :products_id
                                          ');

          $Qstock->bindInt(':products_id', $Qproducts->valueInt('products_id'));
          $Qstock->execute();

          $stock_left = $Qstock->valueInt('products_quantity');

          if (($stock_left < 1) && (\defined('STOCK_ALLOW_CHECKOUT') && STOCK_ALLOW_CHECKOUT == 'false') && (\defined('STOCK_CHECK') && STOCK_CHECK == 'true')) {
            $email_text_subject_stock = stripslashes(CLICSHOPPING::getDef('email_text_subject_stock', ['store_name' => defined('STORE_NAME') ? STORE_NAME : '']));
            $email_product_sold_out_stock = stripslashes(CLICSHOPPING::getDef('email_text_stock'));
            $email_product_sold_out_stock .= "\n" . CLICSHOPPING::getDef('email_text_date_alert') . ' ' . date(CLICSHOPPING::getDef('date_format_long')) . "\n" .
              CLICSHOPPING::getDef('email_text_model') . '  ' . $Qproducts->value('products_model') . "\n" .
              CLICSHOPPING::getDef('email_text_products_name') . ' ' . $Qproducts->value('products_name') . "\n" .
              CLICSHOPPING::getDef('email_text_id_product') . ' ' . $CLICSHOPPING_Prod::getProductID($Qproducts->value('products_id')) . "\n";

            $to_addr = \defined('STORE_OWNER_EMAIL_ADDRESS') ? STORE_OWNER_EMAIL_ADDRESS : '';
            $from_name = \defined('STORE_NAME') ? STORE_NAME : '';
            $from_addr = \defined('STORE_OWNER_EMAIL_ADDRESS') ? STORE_OWNER_EMAIL_ADDRESS : '';
            $to_name = \defined('STORE_OWNER_EMAIL_ADDRESS') ? STORE_OWNER_EMAIL_ADDRESS : '';
            $subject = $email_text_subject_stock;

            $this->mail->addHtml($email_product_sold_out_stock);
            $this->mail->send($to_addr, $from_name, $from_addr, $to_name, $subject);
          }
        } // end stock alert
      }  // end while
    }

    $this->hooks->call('OrderNotifier', 'ProductsSoldOutAlert', ['order_id' => $insert_id]);
  }

  /**
   * Sends an email alert when the stock quantity of a product reaches a specified reorder level
   * or falls below a defined threshold. It checks product stock levels and generates alerts
   * to notify the store owner about low stock or critical stock conditions.
   *
   * @param int $insert_id The ID of the order to evaluate for stock-level alerts.
   * @return void
   */
  public function sendStockWarningAlert(int $insert_id): void
  {
    $CLICSHOPPING_Prod = Registry::get('Prod');

    if (\defined('STOCK_ALERT_PRODUCT_REORDER_LEVEL') && STOCK_ALERT_PRODUCT_REORDER_LEVEL == 'true') {
      if ((\defined('STOCK_ALLOW_CHECKOUT') && STOCK_ALLOW_CHECKOUT == 'false') && (\defined('STOCK_CHECK') && STOCK_CHECK == 'true')) {
        $Qproducts = $this->db->prepare('select orders_products_id,
                                                   products_id,
                                                   products_model,
                                                   products_name,
                                                   products_quantity
                                           from :table_orders_products
                                           where orders_id = :orders_id
                                           order by orders_products_id
                                           ');
        $Qproducts->bindInt(':orders_id', $insert_id);
        $Qproducts->execute();

        if ($Qproducts->fetch() !== false) {
          while ($Qproducts->fetch()) {
            $Qstock = $this->db->prepare('select products_quantity_alert,
                                                    products_quantity
                                            from :table_products
                                            where products_id = :products_id
                                          ');

            $Qstock->bindInt(':products_id', $CLICSHOPPING_Prod::getProductID($Qproducts->valueInt('products_id')));
            $Qstock->execute();

            $stock_products_quantity_alert = $Qstock->valueInt('products_quantity_alert');

            $warning_stock = (int)STOCK_REORDER_LEVEL;
            $current_stock = $Qstock->valueInt('products_quantity');

// alert email if stock product alert < warning stock
            if (($stock_products_quantity_alert <= $warning_stock) && ($stock_products_quantity_alert != '0')) {
              $email_text_subject_stock = stripslashes(CLICSHOPPING::getDef('email_text_suject_stock', ['store_name' => STORE_NAME]));

              $reorder_stock_email = stripslashes(CLICSHOPPING::getDef('email_reorder_level_text_alert_stock'));
              $reorder_stock_email .= "\n" . CLICSHOPPING::getDef('email_text_date_alert') . ' ' . date(CLICSHOPPING::getDef('date_format_long')) . "\n" .
                CLICSHOPPING::getDef('email_text_model') . ' ' . $Qproducts->value('products_model') . "\n" .
                CLICSHOPPING::getDef('email_text_products_name') . ' ' . $Qproducts->value('products_name') . "\n" .
                CLICSHOPPING::getDef('email_text_id_product') . ' ' . $CLICSHOPPING_Prod::getProductID($Qproducts->value('products_id')) . "\n" .
                '<strong>' . CLICSHOPPING::getDef('email_text_product_url') . ' </strong>' . HTTP::getShopUrlDomain() . 'index.php?Products&Description&products_id=' . $Qproducts->value('products_id') . "\n" .
                '<strong>' . CLICSHOPPING::getDef('email_text_product_stock') . ' ' . $stock_products_quantity_alert . '</strong>';

              $to_addr = \defined('STORE_OWNER_EMAIL_ADDRESS') ? STORE_OWNER_EMAIL_ADDRESS : '';
              $from_name = \defined('STORE_OWNER') ? STORE_OWNER : '';
              $from_addr = \defined('STORE_OWNER_EMAIL_ADDRESS') ? STORE_OWNER_EMAIL_ADDRESS : '';
              $to_name = \defined('STORE_OWNER_EMAIL_ADDRESS') ? STORE_OWNER_EMAIL_ADDRESS : '';
              $subject = $email_text_subject_stock;

              $this->mail->addHtml($reorder_stock_email);
              $this->mail->send($to_addr, $from_name, $from_addr, $to_name, $subject);
            }

            if ($current_stock <= $warning_stock) {
              $email_text_subject_stock = stripslashes(CLICSHOPPING::getDef('email_text_suject_stock', ['store_name' => STORE_NAME]));

              $reorder_stock_email = stripslashes(CLICSHOPPING::getDef('email_reorder_level_text_stock'));
              $reorder_stock_email .= "\n" . CLICSHOPPING::getDef('email_text_date_alert') . ' ' . date(CLICSHOPPING::getDef('date_format_long')) . "\n" .
                CLICSHOPPING::getDef('email_text_model') . ' ' . $Qproducts->value('products_model') . "\n" .
                CLICSHOPPING::getDef('email_text_products_name') . ' ' . $Qproducts->value('products_name') . "\n" .
                CLICSHOPPING::getDef('email_text_id_product') . ' ' . $CLICSHOPPING_Prod::getProductID($Qproducts->value('products_id')) . "\n" .
                '<strong>' . CLICSHOPPING::getDef('email_text_product_url') . ' </strong>' . HTTP::getShopUrlDomain() . 'index.php?Products&Description&products_id=' . $Qproducts->value('products_id') . "\n" .
                '<strong>' . CLICSHOPPING::getDef('email_text_product_stock') . ' ' . $stock_products_quantity_alert . '</strong>';

              $to_addr = \defined('STORE_OWNER_EMAIL_ADDRESS') ? STORE_OWNER_EMAIL_ADDRESS : '';
              $from_name = \defined('STORE_OWNER') ? STORE_OWNER : '';
              $from_addr = \defined('STORE_OWNER_EMAIL_ADDRESS') ? STORE_OWNER_EMAIL_ADDRESS : '';
              $to_name = \defined('STORE_OWNER_EMAIL_ADDRESS') ? STORE_OWNER_EMAIL_ADDRESS : '';
              $subject = $email_text_subject_stock;

              $this->mail->addHtml($reorder_stock_email);
              $this->mail->send($to_addr, $from_name, $from_addr, $to_name, $subject);
            }
          }
        }
      }
    }

    $this->hooks->call('OrderNotifier', 'StockWarningAlert', ['order_id' => $insert_id]);
  }
}
