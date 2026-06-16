<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Payment\Stripe\Module\Payment;

use ClicShopping\OM\HTML;
use ClicShopping\OM\Hash;
use ClicShopping\OM\Registry;

use ClicShopping\Apps\Payment\Stripe\Stripe as StripeApp;
use ClicShopping\Sites\Common\B2BCommon;
use Stripe\PaymentIntent;
use Stripe\Stripe as StripeAPI;
/**
 * Class ST
 *
 * This class represents the Stripe Payment integration module for the ClicShopping environment.
 * It implements the PaymentInterface to handle all payment-related processes, including
 * enabling/disabling the module, updating its status, and processing payments.
 *
 * The class interacts with the Stripe API for secure payment handling and supports configurations
 * like custom order statuses, payment zones, and B2B group-based access.
 */

class ST implements \ClicShopping\OM\Modules\PaymentInterface
{
  public string $code;
  public $title;
  public $description;
  public $enabled = false;
  public mixed $app;
  protected $currency;
  public $signature;
  public $public_title;
  public int|null $sort_order = 0;
  protected $api_version;
  public $group;

  public $intent;
  public $private_key;
  public $public_key;

  /**
   * Constructor method for initializing the Stripe payment module.
   *
   * This method sets up the necessary configurations and properties required
   * for the Stripe payment module. It handles registry initialization,
   * module definitions, versioning, API configuration, and enabling or disabling
   * the module based on certain conditions, such as customer group settings
   * and environment (production or test).
   *
   * @return void
   */
  public function __construct()
  {
    $CLICSHOPPING_Customer = Registry::get('Customer');

    if (Registry::exists('Order')) {
      $CLICSHOPPING_Order = Registry::get('Order');
    }

    if (!Registry::exists('Stripe')) {
      Registry::set('Stripe', new StripeApp());
    }

    $this->app = Registry::get('Stripe');
    $this->app->loadDefinitions('Module/Shop/ST/ST');

    $this->signature = 'Stripe|' . $this->app->getVersion() . '|1.0';
    $this->api_version = $this->app->getApiVersion();

    $this->code = 'ST';
    $this->title = $this->app->getDef('module_stripe_title');
    $this->public_title = $this->app->getDef('module_stripe_public_title');

// Activation module du paiement selon les groupes B2B
    if (\defined('CLICSHOPPING_APP_STRIPE_ST_STATUS')) {
      if ($CLICSHOPPING_Customer->getCustomersGroupID() != 0) {
        if (B2BCommon::getPaymentUnallowed($this->code)) {
          if (CLICSHOPPING_APP_STRIPE_ST_STATUS == 'True') {
            $this->enabled = true;
          } else {
            $this->enabled = false;
          }
        }
      } else {
        if (CLICSHOPPING_APP_STRIPE_ST_NO_AUTHORIZE == 'True' && $CLICSHOPPING_Customer->getCustomersGroupID() == 0) {
          if ($CLICSHOPPING_Customer->getCustomersGroupID() == 0) {
            if (CLICSHOPPING_APP_STRIPE_ST_STATUS == 'True') {
              $this->enabled = true;
            } else {
              $this->enabled = false;
            }
          }
        }
      }

      if ((int)CLICSHOPPING_APP_STRIPE_ST_PREPARE_ORDER_STATUS_ID > 0) {
        $this->order_status = CLICSHOPPING_APP_STRIPE_ST_PREPARE_ORDER_STATUS_ID;
      }

      if ($this->enabled === true) {
        if (isset($CLICSHOPPING_Order) && \is_object($CLICSHOPPING_Order)) {
          $this->update_status();
        }
      }

      if (CLICSHOPPING_APP_STRIPE_ST_SERVER_PROD == 'True') {
        $this->private_key = CLICSHOPPING_APP_STRIPE_ST_PRIVATE_KEY;
        $this->public_key = CLICSHOPPING_APP_STRIPE_ST_PUBLIC_KEY;
      } else {
        $this->private_key = CLICSHOPPING_APP_STRIPE_ST_PRIVATE_KEY_TEST;
        $this->public_key = CLICSHOPPING_APP_STRIPE_ST_PUBLIC_KEY_TEST;
      }

      // Disable the module when the keys for the ACTIVE mode (test vs production) are missing.
      // Previously this always checked the production keys, so a test-mode shop with only the
      // _TEST keys filled was wrongly disabled and never appeared in the payment selection.
      if (empty($this->private_key) || empty($this->public_key)) {
        $this->enabled = false;
      }

      $this->sort_order = \defined('CLICSHOPPING_APP_STRIPE_ST_SORT_ORDER') ? CLICSHOPPING_APP_STRIPE_ST_SORT_ORDER : 0;
    }
  }

  /**
   * Updates the status of the payment module based on the delivery address and configured geo zones.
   *
   * This method checks if the module is enabled and if the delivery address matches the configured geo zone restrictions.
   * If the delivery address does not fall within the specified geo zones, the module is disabled.
   *
   * @return void
   */
  public function update_status()
  {
    $CLICSHOPPING_Order = Registry::get('Order');

    if (($this->enabled === true) && ((int)CLICSHOPPING_APP_STRIPE_ST_ZONE > 0)) {
      $check_flag = false;

      $sql_array = [
        'geo_zone_id' => CLICSHOPPING_APP_STRIPE_ST_ZONE,
        'zone_country_id' => $CLICSHOPPING_Order->delivery['country']['id']
      ];

      $Qcheck = $this->app->db->get('zones_to_geo_zones', 'zone_id', $sql_array, 'zone_id');

      while ($Qcheck->fetch()) {
        if (($Qcheck->valueInt('zone_id') < 1) || ($Qcheck->valueInt('zone_id') === $CLICSHOPPING_Order->delivery['zone_id'])) {
          $check_flag = true;
          break;
        }
      }

      if ($check_flag === false) {
        $this->enabled = false;
      }
    }
  }

  /**
   * Provides JavaScript validation for the application.
   *
   * @return bool Returns false indicating that JavaScript validation is not implemented or disabled.
   */
  public function javascript_validation()
  {
    return false;
  }

  /**
   * Prepares and returns the module selection details.
   *
   * This method builds the module's selection details, including the module's
   * unique identifier and public title. If a Stripe logo is set and available
   * in the specified directory, it appends the logo to the public title.
   *
   * @return array An associative array containing the 'id' as the unique
   * module identifier and 'module' as the public title, optionally including
   * a logo image if configured.
   */
  public function selection()
  {
    $CLICSHOPPING_Template = Registry::get('Template');

    if (!empty(CLICSHOPPING_APP_STRIPE_ST_LOGO)) {
      if (!empty(CLICSHOPPING_APP_STRIPE_ST_LOGO) && is_file($CLICSHOPPING_Template->getDirectoryTemplateImages() . 'logos/payment/' . CLICSHOPPING_APP_STRIPE_ST_LOGO)) {
        $this->public_title = $this->public_title . '&nbsp;&nbsp;&nbsp;' . HTML::image($CLICSHOPPING_Template->getDirectoryTemplateImages() . 'logos/payment/' . CLICSHOPPING_APP_STRIPE_ST_LOGO);
      } else {
        $this->public_title = $this->public_title;
      }
    }

    return [
      'id' => $this->app->vendor . '\\' . $this->app->code . '\\' . $this->code,
      'module' => $this->public_title
    ];
  }

  /**
   * Performs a pre-confirmation check before processing the payment.
   *
   * @return bool Always returns false.
   */
  public function pre_confirmation_check()
  {
//      $CLICSHOPPING_Template = Registry::get(('Template'));
//      $result = $CLICSHOPPING_Template->addBlock($this->getSubmitCardDetailsJavascript(), 'footer_scripts');
    return false;
  }

  /**
   * Creates a payment intent and prepares the necessary information for confirming payment with Stripe.
   *
   * This method initializes the Stripe payment intent using order and customer details.
   * It generates metadata based on the order's products and details, sets up either automatic
   * or manual transaction capture mode depending on configuration, and includes relevant payment
   * information in the confirmation content. The content includes embedded JavaScript for Stripe
   * payment handling and essential input data.
   *
   * @return array The confirmation details, including generated HTML content and payment intent data,
   *         to be displayed for confirming payment with Stripe.
   */
  public function confirmation()
  {
    $CLICSHOPPING_Order = Registry::get('Order');
    $CLICSHOPPING_Customer = Registry::get('Customer');
    $CLICSHOPPING_Address = Registry::get('Address');

    StripeAPI::setApiKey($this->private_key);

    $customer_id = $CLICSHOPPING_Customer->getId();
    $currency = mb_strtoupper($CLICSHOPPING_Order->info['currency']);
    // Stripe expects the amount in the currency's smallest unit, which depends on its decimals:
    //  - zero-decimal currencies (JPY, KRW, …): the plain amount, no ×100;
    //  - three-decimal currencies (BHD, KWD, …): ×1000, but rounded to the nearest 10 (last digit 0);
    //  - everyone else: ×100.
    $zeroDecimalCurrencies = ['BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA', 'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF'];
    $threeDecimalCurrencies = ['BHD', 'JOD', 'KWD', 'OMR', 'TND'];

    if (\in_array($currency, $zeroDecimalCurrencies, true)) {
      $total_amount = (int)round($CLICSHOPPING_Order->info['total']);
    } elseif (\in_array($currency, $threeDecimalCurrencies, true)) {
      $total_amount = (int)(round($CLICSHOPPING_Order->info['total'] * 1000 / 10) * 10);
    } else {
      $total_amount = (int)round($CLICSHOPPING_Order->info['total'] * 100);
    }

    $metadata = ['customer_id' => (int)$customer_id,
      'customer_name' => $CLICSHOPPING_Customer->getName(),
      'customer_laster_name' => $CLICSHOPPING_Customer->getLastName(),
      'company' => HTML::output($CLICSHOPPING_Order->customer['company'])
    ];

    $i = 0;

    if (\count($CLICSHOPPING_Order->products) < 7) {
      foreach ($CLICSHOPPING_Order->products as $product) {
        $i++;

        $metadata['product_' . $i . '_name'] = $product['name'];
        $metadata['product_' . $i . '_model'] = $product['model'];
        $metadata['product_' . $i . '_id'] = $product['id'];
        $metadata['product_' . $i . '_qty'] = $product['qty'];
        $metadata['product_' . $i . '_price'] = $product['price'];
        $metadata['product_' . $i . '_tax'] = $product['tax'];
      }
    }

    if (CLICSHOPPING_APP_STRIPE_ST_TRANSACTION_METHOD == 'automatic') {
      $capture_method = 'automatic';
    } else {
      $capture_method = 'manual';
    }

// have to create intent before loading the javascript because it needs the intent id
    if (isset($_SESSION['stripe_payment_intent_id'])) {
      $stripe_payment_intent_id = HTML::sanitize($_SESSION['stripe_payment_intent_id']);

      try {
        // PaymentIntent::update() replaces the deprecated retrieve() + property mutation + ->save()
        // flow (the magic ->save() on Stripe resources is deprecated in stripe-php; the modern API
        // is a single update call, already used in after_process()).
        $this->intent = PaymentIntent::update($stripe_payment_intent_id, [
          'amount' => $total_amount,
          'currency' => $currency,
          'metadata' => $metadata,
        ]);

      } catch (\Exception $err) {
        //$this->event_log($customer_id, 'page create intent', $stripe_payment_intent_id, $err->getMessage());
// failed to save existing intent, so create new one
        unset($stripe_payment_intent_id);
      }
    }

    if (!isset($stripe_payment_intent_id)) {
      $description = STORE_NAME . ' - Order date time : ' . date('Y-m-d H:i:s');
      $token = $_POST['stripeToken'] ?? '';

      $params = [
        'amount' => $total_amount,
        'currency' => $currency,
        'setup_future_usage' => 'off_session',
        'description' => $description,
        'capture_method' => $capture_method,
        'metadata' => $metadata,
        'payment_method_types' => ['card'],
      ];

      $this->intent = PaymentIntent::create($params);

      // $this->event_log($customer_id, "page create intent", json_encode($params), $this->intent);
      $stripe_payment_intent_id = $this->intent->id;
    }

    // Keep the intent id server-side so it can be reused and linked to the order in after_process().
    $_SESSION['stripe_payment_intent_id'] = $stripe_payment_intent_id;

    // Persist the agnostic payment-attempt buffer (before the client-side payment) so the webhook
    // can reconcile the payment with the order even if the customer drops right after paying.
    $this->upsertPaymentActionBuffer($stripe_payment_intent_id, (int)$customer_id, $CLICSHOPPING_Order);

    $content = '';

    if (CLICSHOPPING_APP_STRIPE_ST_SERVER_PROD == 'False') {
      $content .= '<div class="alert alert-warning"> ' . $this->app->getDef('text_stripe_alert_mode_test') . '</div>';
    }

    $content .= $this->app->getDef('text_stripe_title');
    $content .= '<div class="mt-1"></div>';
// have to create intent before loading the javascript because it needs the intent id
    $content .= '<input type="hidden" id="intent_id" name="intent_id" value="' . HTML::output($stripe_payment_intent_id) . '" />' .
      '<input type="hidden" id="secret" value="' . HTML::output($this->intent->client_secret) . '" />';
    $content .= '<div id="stripe_table_new_card">' .
      '<div><label for="cardholder-name" class="control-label">' . $this->app->getDef('text_stripe_credit_card_owner') . '</label>' .
      '<div><input type="text" id="cardholder-name" class="form-control" value="' . HTML::output(trim(Hash::displayDecryptedDataText($CLICSHOPPING_Order->billing['firstname']) . ' ' . Hash::displayDecryptedDataText($CLICSHOPPING_Order->billing['lastname']))) . '" required></text></div>
                  </div>' .
      '<div class="mt-1"></div>' .
      '<div><label for="card-element" class="control-label">' . $this->app->getDef('text_stripe_credit_card_type') . '</label>' .
      '<div id="card-element" class="stripeCardElement"></div>
                  </div>';

    /*
          if (MODULE_PAYMENT_STRIPE_SCA_TOKENS == 'True') {
            $content .= '<div class="form-check">' .
                '<div>' . tep_draw_checkbox_field('card-save', 'true', null, 'class="form-check-input') . '<label class="form-check-label">' . MODULE_PAYMENT_STRIPE_SCA_CREDITCARD_SAVE . '</label></div></div>';
          }
    */

    $content .= '<div id="card-errors" role="alert" class="messageStackError payment-errors"></div></div>';
    // Billing PII is stored AES-encrypted on the Order object; decrypt it before sending the
    // address to Stripe (otherwise Stripe receives the ciphertext, not the real values).
    $content .= '<input type="hidden" id="city" value="' . HTML::output(Hash::displayDecryptedDataText($CLICSHOPPING_Order->billing['city'])) . '" />';
    $content .= '<input type="hidden" id="line1" value="' . HTML::output(Hash::displayDecryptedDataText($CLICSHOPPING_Order->customer['street_address'])) . '" />';
    $content .= '<input type="hidden" id="line2" value="' . HTML::output(Hash::displayDecryptedDataText($CLICSHOPPING_Order->billing['suburb'])) . '" />';
    $content .= '<input type="hidden" id="postal_code" value="' . HTML::output(Hash::displayDecryptedDataText($CLICSHOPPING_Order->customer['postcode'])) . '" />';
    $content .= '<input type="hidden" id="state" value="' . HTML::output($CLICSHOPPING_Address->getZoneName($CLICSHOPPING_Order->billing['country']['id'], $CLICSHOPPING_Order->billing['zone_id'], Hash::displayDecryptedDataText($CLICSHOPPING_Order->billing['state']))) . '" />';
    $content .= '<input type="hidden" id="country" value="' . HTML::output($CLICSHOPPING_Order->billing['country']['iso_code_2']) . '" />';
    $content .= '<input type="hidden" id="email_address" value="' . HTML::output(Hash::displayDecryptedEmail($CLICSHOPPING_Order->customer['email_address'])) . '" />';
    $content .= '<input type="hidden" id="customer_id" value="' . HTML::output($customer_id) . '" />';

    $content .= $this->getSubmitCardDetailsJavascript();

    // Return the markup under 'content' (not 'title'): the confirmation content module renders a
    // 'title' inside a <table>, which foster-parents the Stripe <div id="card-element"> and its
    // <script> out of the table and breaks the Element mount. The 'content' branch renders it in a
    // plain <div>, so the card Element mounts correctly.
    $confirmation = ['content' => $content];

    return $confirmation;
  }

  /**
   * Processes the button action for the payment or form submission.
   *
   * @return bool Returns false to indicate no further processing is required.
   */
  public function process_button()
  {
    return false;
  }

  /**
   * Executes any necessary logic prior to processing an operation.
   *
   * @return bool Returns false indicating no processing is performed.
   */
  public function before_process()
  {
    return false;
  }

  /**
   * Processes additional operations after a successful order placement.
   *
   * This method retrieves the last order ID and updates its status and history
   * in the database. If the order ID is unavailable, it retrieves the latest
   * order ID from the database. It determines the new order status using the
   * configured Stripe order status ID or falls back to the default order status ID.
   * The function also logs any relevant comments with the updated order status.
   *
   * @return void
   */
  public function after_process()
  {
    $CLICSHOPPING_Order = Registry::get('Order');

    $orders_id = $CLICSHOPPING_Order->getLastOrderId();

    if (empty($orders_id) || $orders_id == 0 || \is_null($orders_id)) {
      // Scope the fallback to the current customer: a bare "last order id" can target
      // another customer's order under concurrent checkouts.
      $customers_id = (int)(Registry::get('Customer')->getId());

      $Qorder = $CLICSHOPPING_Order->db->prepare('select orders_id
                                                    from :table_orders
                                                    where customers_id = :customers_id
                                                    order by orders_id desc
                                                    limit 1
                                                   ');
      $Qorder->bindInt(':customers_id', $customers_id);
      $Qorder->execute();

      $orders_id = $Qorder->valueInt('orders_id');
    }

    // Internal order note (customer_notified = 0 below): records the initial Stripe processing,
    // which is never displayed to the customer.
    $comment = $this->app->getDef('text_reference_transaction') . ' - ' . $this->app->getDef('text_stripe_initial_processing');

    if (CLICSHOPPING_APP_STRIPE_ST_PREPARE_ORDER_STATUS_ID > 0) {
      $new_order_status = CLICSHOPPING_APP_STRIPE_ST_PREPARE_ORDER_STATUS_ID;
    } elseif (CLICSHOPPING_APP_STRIPE_ST_ORDER_STATUS_ID == 0 || empty(CLICSHOPPING_APP_STRIPE_ST_ORDER_STATUS_ID)) {
      $new_order_status = DEFAULT_ORDERS_STATUS_ID;
    } else {
      $new_order_status = CLICSHOPPING_APP_STRIPE_ST_ORDER_STATUS_ID;
    }

    $sql_data_array = [
      'orders_id' => $orders_id,
      'orders_status_id' => (int)$new_order_status,
      'date_added' => 'now()',
      'customer_notified' => '0',
      'comments' => $comment
    ];

    $this->app->db->save('orders_status_history', $sql_data_array);

    $sql_data_array = ['orders_status' => (int)$new_order_status];
    $sql_insert = ['orders_id' => (int)$orders_id];

    $this->app->db->save('orders', $sql_data_array, $sql_insert);

    // Link the payment buffer to the created order and push the real order id into the
    // PaymentIntent metadata so the webhook can reconcile (buffer keyed by payment_intent_id).
    $intentId = $_SESSION['stripe_payment_intent_id'] ?? null;

    if (!empty($intentId) && !empty($orders_id)) {
      $this->app->db->save('order_customer_payment_action',
        ['orders_id' => (int)$orders_id, 'status' => 'completed', 'date_modified' => 'now()'],
        ['payment_intent_id' => $intentId, 'type_apps_payment' => 'Stripe']
      );

      try {
        StripeAPI::setApiKey($this->private_key);
        PaymentIntent::update($intentId, ['metadata' => ['orders_id' => (int)$orders_id]]);
      } catch (\Exception $e) {
        // Non-blocking: the order is already recorded; the webhook falls back to the buffer lookup.
      }

      unset($_SESSION['stripe_payment_intent_id']);
    }
  }

  /**
   * Persists / refreshes the agnostic payment-attempt buffer for the current PaymentIntent.
   *
   * Written at confirmation time (before the client-side payment) so the webhook can reconcile
   * the payment with the order even if the customer drops after paying. Also runs an
   * opportunistic TTL purge of stale pending rows (no cron required).
   *
   * @param string $intentId The Stripe PaymentIntent id.
   * @param int $customerId The logged-in customer id.
   * @param mixed $order The current Order object (snapshot source).
   * @return void
   */
  private function upsertPaymentActionBuffer(string $intentId, int $customerId, $order): void
  {
    if (empty($intentId)) {
      return;
    }

    // Opportunistic purge (no cron). TTL must exceed Stripe's webhook retry window (~3 days); default 30 days.
    $ttl = \defined('CLICSHOPPING_APP_STRIPE_ST_BUFFER_TTL_DAYS') ? (int)CLICSHOPPING_APP_STRIPE_ST_BUFFER_TTL_DAYS : 30;
    $cutoff = date('Y-m-d H:i:s', time() - ($ttl * 86400));

    $Qpurge = $this->app->db->prepare("delete from :table_order_customer_payment_action
                                         where type_apps_payment = 'Stripe'
                                         and status = 'pending'
                                         and date_added < :cutoff
                                       ");
    $Qpurge->bindValue(':cutoff', $cutoff);
    $Qpurge->execute();

    $data = [
      'type_apps_payment' => 'Stripe',
      'payment_intent_id' => $intentId,
      'customers_id' => $customerId,
      'status' => 'pending',
      'sendto' => isset($_SESSION['sendto']) ? (int)$_SESSION['sendto'] : null,
      'billto' => isset($_SESSION['billto']) ? (int)$_SESSION['billto'] : null,
      'cart_snapshot' => $this->buildCartSnapshot($order),
      'date_modified' => 'now()',
    ];

    $Qexists = $this->app->db->get('order_customer_payment_action', 'payment_order_customer_action_id', [
      'payment_intent_id' => $intentId,
      'type_apps_payment' => 'Stripe'
    ]);

    if ($Qexists->fetch() === false) {
      $data['date_added'] = 'now()';
      $this->app->db->save('order_customer_payment_action', $data);
    } else {
      $this->app->db->save('order_customer_payment_action', $data, [
        'payment_intent_id' => $intentId,
        'type_apps_payment' => 'Stripe'
      ]);
    }
  }

  /**
   * Builds a minimal JSON snapshot of the order-to-be (no raw PII; addresses are referenced
   * by sendto/billto). Used for reconciliation/recovery and a future stateless order rebuild.
   *
   * @param mixed $order The current Order object.
   * @return string JSON snapshot.
   */
  private function buildCartSnapshot($order): string
  {
    $products = [];

    if (!empty($order->products) && \is_array($order->products)) {
      foreach ($order->products as $p) {
        $products[] = [
          'id' => $p['id'] ?? null,
          'qty' => $p['qty'] ?? null,
          'name' => $p['name'] ?? '',
          'price' => $p['price'] ?? null,
        ];
      }
    }

    $data = [
      'currency' => $order->info['currency'] ?? null,
      'total' => $order->info['total'] ?? null,
      'payment' => $_SESSION['payment'] ?? null,
      'shipping' => $_SESSION['shipping'] ?? null,
      'comments' => $_SESSION['comments'] ?? null,
      'products' => $products,
    ];

    try {
      return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    } catch (\JsonException $e) {
      return '{}';
    }
  }

  /**
   * Retrieves the error details based on the provided error parameter in the URL.
   *
   * This method checks for the presence of an 'error' parameter in the URL. If found and its value matches specific cases,
   * it updates the error message accordingly. Otherwise, it uses a general error message. The result is an associative
   * array containing the error's title and message.
   *
   * @return array An associative array with two keys:
   *               - 'title': The title of the error message.
   *               - 'error': The detailed error message.
   */
  public function get_error()
  {
    $message = $this->app->getDef('module_stripe_error_general');

    if (isset($_GET['error']) && !empty($_GET['error'])) {
      switch ($_GET['error']) {
        case 'cardstored':
          $message = $this->app->getDef('module_stripe_error_stripe');
          break;
      }
    }

    $error = ['title' => $this->app->getDef('module_stripe_error_title'),
      'error' => $message
    ];

    return $error;
  }

  /**
   * Checks if the Stripe module status is defined and not an empty string.
   *
   * @return bool True if the Stripe module status is set and not empty, false otherwise.
   */
  public function check()
  {
    return \defined('CLICSHOPPING_APP_STRIPE_ST_STATUS') && (trim(CLICSHOPPING_APP_STRIPE_ST_STATUS) != '');
  }

  /**
   * Redirects to the installation configuration page for the specified module.
   *
   * @return void
   */
  public function install()
  {
    $this->app->redirect('Configure&Install&module=Stripe');
  }

  /**
   * Redirects to the uninstall module configuration page for Stripe.
   *
   * @return void
   */
  public function remove()
  {
    $this->app->redirect('Configure&Uninstall&module=Stripe');
  }

  /**
   * Retrieves the configuration keys used by the Stripe module.
   *
   * @return array An array of configuration key names.
   */
  public function keys()
  {
    return array('CLICSHOPPING_APP_STRIPE_ST_SORT_ORDER');
  }

  /**
   * Generates the JavaScript necessary for handling Stripe payment processing,
   * including creating and managing card elements, submitting the payment form,
   * and handling saved cards or new card details for Stripe intents.
   *
   * @param mixed $intent Optional argument for passing additional intent-related information.
   *                      Defaults to null if not provided.
   * @return string The JavaScript code as a string to be included on the payment processing page.
   */
  public function getSubmitCardDetailsJavascript(mixed $intent = null)
  {
    $stripe_publishable_key = $this->public_key;
    $intent_url = ''; // return url

    $js = <<<EOD
<style>
#stripe_table_new_card #card-element,
.stripeCardElement {
  background-color: #fff;
  padding: 10px 12px;
  border: 1px solid #ccc;
  border-radius: 4px;
  width: 100%;
  max-width: 500px;
  box-sizing: border-box;
}
</style>
<script src="https://js.stripe.com/v3/"></script>
<script>
(function clicStripeInit() {
    // The card form is rendered in the page body, but jQuery (and sometimes Stripe.js v3) are
    // loaded later in the footer. Defer initialisation until BOTH are available, otherwise the
    // inline script runs before jQuery/Stripe exist and the card Element never mounts.
    if (typeof window.jQuery === 'undefined' || typeof window.Stripe === 'undefined') {
        return window.setTimeout(clicStripeInit, 50);
    }

    jQuery(function($) {
    $('[name=checkout_confirmation]').attr('id','payment-form');

    var stripe = Stripe('{$stripe_publishable_key}');
    var elements = stripe.elements();

    // Create an instance of the card Element.
    var card = elements.create('card', {hidePostalCode: true});
    
    // Add an instance of the card Element into the `card-element` <div>.
    card.mount('#card-element');

    $('#payment-form').submit(function(event) {
        // Respect the "law Hamon" agreement checkbox (rendered by a separate confirmation module):
        // if it is present and not ticked, abort BEFORE charging the card. The form's inline
        // onsubmit=checkCheckBox already shows the alert; this just stops the Stripe flow too.
        var agreeBox = document.getElementById('agree');
        if (agreeBox && !agreeBox.checked) {
            return false;
        }

        var form = $(this);

        // Disable the submit button to prevent repeated clicks
        form.find('button').prop('disabled', true);

        var selected =  $("input[name='stripe_card']:checked").val();
        var cc_save = $('[name=card-save]').prop('checked');
        try {
            if ((selected != null && selected != '0') || cc_save) {
                // update intent to use saved card, then process payment if successful
                updatePaymentIntent(cc_save, selected);
            } else {
                // using new card details without save
                processNewCardPayment();
            }
        } catch (error) {
            form.find('.payment-errors').text(error);
        }

        // Prevent the form from submitting with the default action
        return false;
    });

    if ($('#stripe_table').length > 0) {
        if (typeof($('#stripe_table').parent().closest('table').attr('width')) == 'undefined') {
          $('#stripe_table').parent().closest('table').attr('width', '100%');
        }

        $('#stripe_table .moduleRowExtra').hide();

        $('#stripe_table_new_card').hide();
        $('#card-element').prop('id','new-card-element');
        $('#save-card-element').prop('id','card-element');

        $('form[name="checkout_confirmation"] input[name="stripe_card"]').change(function() {
            if ($(this).val() == '0') {
                stripeShowNewCardFields();
            } else {
                if ($('#stripe_table_new_card').is(':visible')) {
                    $('#card-element').prop('id','new-card-element');
                    $('#save-card-element').prop('id','card-element');
                }
                $('#stripe_table_new_card').hide();
            }
            $('tr[id^="stripe_card_"]').removeClass('moduleRowSelected');
            $('#stripe_card_' + $(this).val()).addClass('moduleRowSelected');
        });

        $('form[name="checkout_confirmation"] input[name="stripe_card"]:first').prop('checked', true).trigger('change');

        $('#stripe_table .moduleRow').hover(function() {
            $(this).addClass('moduleRowOver');
        }, function() {
            $(this).removeClass('moduleRowOver');
        }).click(function(event) {
            var target = $(event.target);

            if (!target.is('input:radio')) {
                $(this).find('input:radio').each(function() {
                    if ($(this).prop('checked') == false) {
                        $(this).prop('checked', true).trigger('change');
                    }
                });
            }
        });
    } else {
        if (typeof($('#stripe_table_new_card').parent().closest('table').attr('width')) == 'undefined') {
            $('#stripe_table_new_card').parent().closest('table').attr('width', '100%');
        }
    }

    function stripeShowNewCardFields() {
        $('#card-element').attr('id','save-card-element');
        $('#new-card-element').attr('id','card-element');
        $('#stripe_table_new_card').show();
    }

    function updatePaymentIntent(cc_save, token) {
        // add card save option to payment intent, so card can be saved in webhook
        // or customer/payment method if using saved card
        $.getJSON("{$intent_url}", {
            "id": $('#intent_id').val(),
            "token": token, 
            "customer_id": $('#customer_id').val(), 
            "cc_save": cc_save
        }, function(data) {
            if (data.status == 'ok') {
                var selected = $("input[name='stripe_card']:checked").val();
                if (selected == null || selected == '0') {
                    processNewCardPayment();
                } else {
                    processSavedCardPayment(data.payment_method);
                }
            } else {
                var form = $('#payment-form');
                form.find('button').prop('disabled', false);
                $('#card-errors').text(data.error);    
            }
        });
    }

    function processNewCardPayment() {
        stripe.confirmCardPayment($('#secret').val(), {
            payment_method: {
                card: card,
                billing_details: {
                    name: $('#cardholder-name').val(),
                    address: {
                        city: $('#city').val(),
                        line1: $('#line1').val(),
                        postal_code: $('#postal_code').val(),
                        state: $('#state').val(),
                        country: $('#country').val()
                    },
                    email: $('#email_address').val()
                }
            }
        }).then(function(result) { 
            stripeResponseHandler(result);
        });
    }

    function processSavedCardPayment(payment_method_id) {
        stripe.confirmCardPayment($('#secret').val(), {
            payment_method: payment_method_id
        }).then(function(result) { 
            stripeResponseHandler(result);
        });
    }

    function stripeResponseHandler(result) {
        var form = $('#payment-form');
        if (result.error) {
            $('#card-errors').text(result.error.message);
            form.find('button').prop('disabled', false);
        } else {
            $('#card-errors').text('Processing');
            // Insert the token into the form so it gets submitted to the server
            form.append($('<input type="hidden" name="stripeIntentId" />').val(result.paymentIntent.id));
            // and submit
            form.get(0).submit();
        }
    }
    });
})();
</script>
EOD;

    return $js;
  }
}