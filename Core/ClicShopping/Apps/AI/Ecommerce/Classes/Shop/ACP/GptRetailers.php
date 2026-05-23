<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\AI\Ecommerce\Classes\Shop\ACP;

use ClicShopping\Apps\Catalog\Manufacturers\Classes\ClicShoppingAdmin\ManufacturerAdmin;
use ClicShopping\Apps\Configuration\ChatGpt\ChatGpt;
use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTTP;
use ClicShopping\OM\Registry;
use ClicShopping\OM\SimpleLogger;
use ClicShopping\Sites\Shop\Address;
use Stripe\PaymentIntent;
use Stripe\Stripe;
use Stripe\Webhook;

/**
 * GptRetailers class.
 *
 * This class acts as the main interface between the ClicShopping e-commerce platform
 * and the OpenAI Retailers API. It manages product catalog synchronization,
 * checkout session creation/management, delegated payment handling (Stripe),
 * webhook communication with the ACP agent, and order creation.
 */

class GptRetailers
{
  /**
   * @var ChatGpt The main ChatGpt application object.
   */
  protected ChatGpt $app;

  /**
   * @var object The language object from the Registry.
   */
  protected object $lang;

  /**
   * @var object The database connection object from the Registry.
   */
  protected object $db;

  /**
   * @var SimpleLogger The simple logger instance for logging events and errors.
   */
  protected SimpleLogger $logger;

  /**
   * @var GptOrderManager Manages the creation and handling of shop orders.
   */
  protected GptOrderManager $orderManager;

  /**
   * @var GptCustomerManager Manages customer account creation and retrieval.
   */
  protected GptCustomerManager $customerManager;

  /**
   * @var string Directory path for storing session files.
   */
  protected string $dirSession;

  /**
   * GptRetailers constructor.
   * Initializes dependencies, checks application status, configures Stripe,
   * and sets up the session directory.
   */
  public function __construct()
  {
    if (!Registry::exists('ChatGpt')) {
      Registry::set('ChatGpt', new ChatGpt());
    }

    $this->app = Registry::get('ChatGpt');
    $this->lang = Registry::get('Language');
    $this->db = Registry::get('Db');

    if (!Registry::exists('SimpleLogger')) {
      // Use a custom logger instance if the global one doesn't exist
      $this->logger = new SimpleLogger('ACP_ClicShopping');
    } else {
      $this->logger = Registry::get('SimpleLogger');
    }

    $this->orderManager = new GptOrderManager();
    $this->customerManager = new GptCustomerManager();

    // Perform initial checks
    $this->checkStatus();
    //$this->checkAppStripe();

    // Setup session directory
    //$this->dirSession = CLICSHOPPING::BASE_DIR . 'Work/Sessions/Shop/OpenAIACP';
    // Configure Stripe API key dynamically (live or test)
     //Stripe::setApiKey($this->AppStripeKey());
  }

  // ---
  ## Configuration and Status Checks

  /**
   * Checks if the ChatGpt Retailer application status is enabled and configured.
   *
   * @return bool True if the application is enabled and the OpenAI API key is set, false otherwise.
   */
  private function checkStatus() :bool
  {
    $result = true;

    if(\defined('CLICSHOPPING_APP_ECOMMERCE_ACP_STATUS') && CLICSHOPPING_APP_ECOMMERCE_ACP_STATUS == 'False') {
      $result = false;
    }

    if(\defined('CLICSHOPPING_APP_ECOMMERCE_ACP_API_KEY_OPENAI_RETAIL') && empty(CLICSHOPPING_APP_ECOMMERCE_ACP_API_KEY_OPENAI_RETAIL)) {
      $result = false;
    }

    return $result;
  }

  /**
   * Checks if the Stripe application is installed, activated, and properly configured.
   *
   * @return bool True if Stripe is ready to be used, false otherwise.
   */
  private function checkAppStripe() :bool
  {
    $result = true;

    if(\defined('CLICSHOPPING_APP_STRIPE_ST_STATUS') && CLICSHOPPING_APP_STRIPE_ST_STATUS == 'False') {
      $result = false;
    }

    if (\defined('CLICSHOPPING_APP_STRIPE_ST_KEY_WEBHOOK_ENDPOINT') && empty(CLICSHOPPING_APP_STRIPE_ST_KEY_WEBHOOK_ENDPOINT)) {
      // Note: CLICSHOPPING_APP_STRIPE_ST_KEY_WEBHOOK_ENDPOINT seems to be used as the secret here, which may be incorrect based on typical Stripe integration.
      $result = false;
    }

    return $result;
  }

  // Webhook helpers removed per ACP requirements.

  /**
   * Retrieves the appropriate Stripe private key (live or test) based on the application's production status.
   *
   * @return string The Stripe private API key.
   */
  public function AppStripeKey() :string
  {
    $private_key  = '';

    if (\defined('CLICSHOPPING_APP_STRIPE_ST_SERVER_PROD')) {
      if (CLICSHOPPING_APP_STRIPE_ST_SERVER_PROD == 'True' && \defined('CLICSHOPPING_APP_STRIPE_ST_PRIVATE_KEY')) {
        $private_key = CLICSHOPPING_APP_STRIPE_ST_PRIVATE_KEY;
      } elseif (\defined('CLICSHOPPING_APP_STRIPE_ST_PRIVATE_KEY_TEST')) {
        $private_key = CLICSHOPPING_APP_STRIPE_ST_PRIVATE_KEY_TEST;
      }
}

    return $private_key;
  }

  /**
   * Convert a major currency amount into minor units (e.g., EUR -> cents).
   *
   * @param float $amount
   * @return int
   */
  private function toMinorUnits(float $amount): int
  {
    return (int)round($amount * 100);
  }

  /**
   * Returns a RFC 3339 date from a relative time string.
   *
   * @param string $time
   * @return string
   */
  private function toRfc3339(string $time): string
  {
    return gmdate('c', strtotime($time));
  }

  /**
   * Returns the base shop URL.
   *
   * @return string
   */
  private function getBaseShopUrl(): string
  {
    return CLICSHOPPING::getConfig('http_server', 'Shop') . CLICSHOPPING::getConfig('http_path', 'Shop');
  }

  /**
   * Build absolute URL for shop assets and pages.
   *
   * @param string $path
   * @return string
   */
  private function buildAbsoluteUrl(string $path): string
  {
    if ($path === '') {
      return '';
    }

    if (preg_match('#^https?://#i', $path)) {
      return $path;
    }

    $baseUrl = $this->getBaseShopUrl();
    return rtrim($baseUrl, '/') . '/' . ltrim($path, '/');
  }

  /**
   * Returns the store country ISO-3166-1 alpha-2 code.
   *
   * @return string
   */
  private function getStoreCountryIso(): string
  {
    if (\defined('STORE_COUNTRY')) {
      $country = Address::getCountries((int)STORE_COUNTRY, true);
      if (!empty($country['countries_iso_code_2'])) {
        return $country['countries_iso_code_2'];
      }
    }

    return 'US';
  }

  /**
   * Build ACP line items from hydrated products.
   *
   * @param array $items
   * @param float $taxRate
   * @return array
   */
  private function buildLineItems(array $items, float $taxRate): array
  {
    $lineItems = [];

    foreach ($items as $item) {
      $quantity = (int)($item['quantity'] ?? 1);
      $unitPrice = (float)($item['unit_price'] ?? $item['price'] ?? 0);
      $baseAmount = $this->toMinorUnits($unitPrice * $quantity);
      $discount = 0;
      $subtotal = $baseAmount - $discount;
      $tax = (int)round($subtotal * $taxRate);
      $total = $subtotal + $tax;

      $lineItems[] = [
        'id' => uniqid('li_'),
        'item' => [
          'id' => (string)($item['id'] ?? ''),
          'quantity' => $quantity
        ],
        'base_amount' => $baseAmount,
        'discount' => $discount,
        'subtotal' => $subtotal,
        'tax' => $tax,
        'total' => $total
      ];
    }

    return $lineItems;
  }

  /**
   * Build totals array for ACP checkout session.
   *
   * @param array $lineItems
   * @param int $fulfillmentSubtotal
   * @param int $fulfillmentTax
   * @return array
   */
  private function buildTotals(array $lineItems, int $fulfillmentSubtotal, int $fulfillmentTax): array
  {
    $itemsBaseAmount = 0;
    $itemsDiscount = 0;
    $itemsTax = 0;

    foreach ($lineItems as $lineItem) {
      $itemsBaseAmount += (int)($lineItem['base_amount'] ?? 0);
      $itemsDiscount += (int)($lineItem['discount'] ?? 0);
      $itemsTax += (int)($lineItem['tax'] ?? 0);
    }

    $subtotal = $itemsBaseAmount - $itemsDiscount;
    $discount = 0;
    $tax = $itemsTax + $fulfillmentTax;
    $fee = 0;
    $total = $itemsBaseAmount - $itemsDiscount - $discount + $fulfillmentSubtotal + $tax + $fee;

    $totals = [];

    $totals[] = [
      'type' => 'items_base_amount',
      'display_text' => 'Item(s) total',
      'amount' => $itemsBaseAmount
    ];

    if ($itemsDiscount > 0) {
      $totals[] = [
        'type' => 'items_discount',
        'display_text' => 'Items discount',
        'amount' => $itemsDiscount
      ];
    }

    $totals[] = [
      'type' => 'subtotal',
      'display_text' => 'Subtotal',
      'amount' => $subtotal
    ];

    if ($discount > 0) {
      $totals[] = [
        'type' => 'discount',
        'display_text' => 'Discount',
        'amount' => $discount
      ];
    }

    $totals[] = [
      'type' => 'fulfillment',
      'display_text' => 'Fulfillment',
      'amount' => $fulfillmentSubtotal
    ];

    $totals[] = [
      'type' => 'tax',
      'display_text' => 'Tax',
      'amount' => $tax
    ];

    if ($fee > 0) {
      $totals[] = [
        'type' => 'fee',
        'display_text' => 'Fees',
        'amount' => $fee
      ];
    }

    $totals[] = [
      'type' => 'total',
      'display_text' => 'Total',
      'amount' => $total
    ];

    return $totals;
  }

  /**
   * Build fulfillment options for checkout sessions.
   *
   * @param int $shippingSubtotal
   * @param int $shippingTax
   * @return array
   */
  private function buildFulfillmentOptions(int $shippingSubtotal, int $shippingTax): array
  {
    $earliest = \defined('CLICSHOPPING_APP_ECOMMERCE_ACP_DELIVERY_EARLIEST') ? CLICSHOPPING_APP_ECOMMERCE_ACP_DELIVERY_EARLIEST : '+3 days';
    $latest = \defined('CLICSHOPPING_APP_ECOMMERCE_ACP_DELIVERY_LATEST') ? CLICSHOPPING_APP_ECOMMERCE_ACP_DELIVERY_LATEST : '+5 days';
    $defaultDelivery = \defined('CLICSHOPPING_APP_ECOMMERCE_ACP_DEFAULT_DELIVERY') ? CLICSHOPPING_APP_ECOMMERCE_ACP_DEFAULT_DELIVERY : '3-5 jours ouvrés';
    $total = $shippingSubtotal + $shippingTax;

    return [
      [
        'type' => 'shipping',
        'id' => 'shipping_standard',
        'title' => 'Standard',
        'subtitle' => $defaultDelivery,
        'carrier' => 'Standard',
        'earliest_delivery_time' => $this->toRfc3339($earliest),
        'latest_delivery_time' => $this->toRfc3339($latest),
        'subtotal' => $shippingSubtotal,
        'tax' => $shippingTax,
        'total' => $total
      ]
    ];
  }

  /**
   * Build supported payment providers list.
   *
   * @return array
   */
  private function buildPaymentProviders(): array
  {
    if ($this->checkAppStripe()) {
      return [
        [
          'provider' => 'stripe',
          'supported_payment_methods' => ['card']
        ]
      ];
    }

    return [];
  }

  /**
   * Build a single payment provider entry for ACP responses.
   *
   * @return array
   */
  private function buildPaymentProvider(): array
  {
    $providers = $this->buildPaymentProviders();
    if (!empty($providers[0])) {
      return $providers[0];
    }

    return [
      'provider' => 'stripe',
      'supported_payment_methods' => ['card']
    ];
  }

  /**
   * Validate ACP payloads and return error messages.
   *
   * @param array $input
   * @param string $context
   * @param array $itemsInput
   * @return array
   */
  public function validateAcpInput(array $input, string $context, array $itemsInput = []): array
  {
    $messages = [];

    if (!empty($itemsInput)) {
      $messages = array_merge($messages, $this->validateItemsInput($itemsInput, '$.items'));
    }

    if (!empty($input['line_items']) && is_array($input['line_items'])) {
      foreach ($input['line_items'] as $index => $lineItem) {
        $itemPath = '$.line_items[' . $index . '].item';
        $item = $lineItem['item'] ?? [];
        $messages = array_merge($messages, $this->validateItem($item, $itemPath));
      }
    }

    if (!empty($input['shipping_address']) && is_array($input['shipping_address'])) {
      $messages = array_merge($messages, $this->validateAddress($input['shipping_address'], '$.shipping_address'));
    }

    if (!empty($input['fulfillment_address']) && is_array($input['fulfillment_address'])) {
      $messages = array_merge($messages, $this->validateAddress($input['fulfillment_address'], '$.fulfillment_address'));
    }

    if (!empty($input['billing_address']) && is_array($input['billing_address'])) {
      $messages = array_merge($messages, $this->validateAddress($input['billing_address'], '$.billing_address'));
    }

    if (!empty($input['buyer']) && is_array($input['buyer'])) {
      $messages = array_merge($messages, $this->validateBuyer($input['buyer'], '$.buyer'));
    }

    if (!empty($input['messages']) && is_array($input['messages'])) {
      foreach ($input['messages'] as $index => $message) {
        $messages = array_merge($messages, $this->validateMessage($message, '$.messages[' . $index . ']'));
      }
    }

    if (!empty($input['links']) && is_array($input['links'])) {
      foreach ($input['links'] as $index => $link) {
        $messages = array_merge($messages, $this->validateLink($link, '$.links[' . $index . ']'));
      }
    }

    if (!empty($input['payment_data']) && is_array($input['payment_data'])) {
      $messages = array_merge($messages, $this->validatePaymentData($input['payment_data'], '$.payment_data'));
    }

    return $messages;
  }

  /**
   * Build an ACP error message.
   *
   * @param string $code
   * @param string $param
   * @param string $content
   * @return array
   */
  private function buildErrorMessage(string $code, string $param, string $content): array
  {
    return [
      'type' => 'error',
      'code' => $code,
      'param' => $param,
      'content_type' => 'plain',
      'content' => $content
    ];
  }

  /**
   * Validate item input.
   *
   * @param array $item
   * @param string $path
   * @return array
   */
  private function validateItem(array $item, string $path): array
  {
    $messages = [];

    if (empty($item['id'])) {
      $messages[] = $this->buildErrorMessage('missing', $path . '.id', 'Item id is required.');
    }

    if (!isset($item['quantity'])) {
      $messages[] = $this->buildErrorMessage('missing', $path . '.quantity', 'Item quantity is required.');
    } elseif (!is_numeric($item['quantity']) || (int)$item['quantity'] <= 0) {
      $messages[] = $this->buildErrorMessage('invalid', $path . '.quantity', 'Item quantity must be a positive integer.');
    }

    return $messages;
  }

  /**
   * Validate items payload input.
   *
   * @param array $itemsInput
   * @param string $path
   * @return array
   */
  private function validateItemsInput(array $itemsInput, string $path): array
  {
    $messages = [];
    $index = 0;

    foreach ($itemsInput as $key => $value) {
      if (is_array($value)) {
        $messages = array_merge($messages, $this->validateItem($value, $path . '[' . $index . ']'));
      } else {
        if (empty($key)) {
          $messages[] = $this->buildErrorMessage('missing', $path . '[' . $index . ']', 'Item id is required.');
        }
        if (!is_numeric($value) || (int)$value <= 0) {
          $messages[] = $this->buildErrorMessage('invalid', $path . '[' . $index . '].quantity', 'Item quantity must be a positive integer.');
        }
      }
      $index++;
    }

    return $messages;
  }

  /**
   * Validate address fields.
   *
   * @param array $address
   * @param string $path
   * @return array
   */
  private function validateAddress(array $address, string $path): array
  {
    $messages = [];
    $required = ['name','line_one','city','state','country','postal_code'];

    foreach ($required as $field) {
      if (empty($address[$field])) {
        $messages[] = $this->buildErrorMessage('missing', $path . '.' . $field, 'Address field is required.');
      }
    }

    $lengths = [
      'name' => 256,
      'line_one' => 60,
      'line_two' => 60,
      'city' => 60,
      'state' => 60,
      'country' => 60,
      'postal_code' => 20
    ];

    foreach ($lengths as $field => $max) {
      if (!empty($address[$field]) && strlen((string)$address[$field]) > $max) {
        $messages[] = $this->buildErrorMessage('invalid', $path . '.' . $field, 'Address field exceeds max length.');
      }
    }

    if (!empty($address['country']) && !preg_match('/^[A-Z]{2,3}$/', (string)$address['country'])) {
      $messages[] = $this->buildErrorMessage('invalid', $path . '.country', 'Country must be ISO 3166-1 alpha-2 or alpha-3.');
    }

    if (!empty($address['phone_number']) && !preg_match('/^\+[1-9]\d{1,14}$/', (string)$address['phone_number'])) {
      $messages[] = $this->buildErrorMessage('invalid', $path . '.phone_number', 'Phone number must follow E.164.');
    }

    return $messages;
  }

  /**
   * Validate buyer fields.
   *
   * @param array $buyer
   * @param string $path
   * @return array
   */
  private function validateBuyer(array $buyer, string $path): array
  {
    $messages = [];

    if (empty($buyer['name'])) {
      $messages[] = $this->buildErrorMessage('missing', $path . '.name', 'Buyer name is required.');
    } elseif (strlen((string)$buyer['name']) > 256) {
      $messages[] = $this->buildErrorMessage('invalid', $path . '.name', 'Buyer name exceeds max length.');
    }

    if (empty($buyer['email'])) {
      $messages[] = $this->buildErrorMessage('missing', $path . '.email', 'Buyer email is required.');
    } elseif (strlen((string)$buyer['email']) > 256 || !filter_var($buyer['email'], FILTER_VALIDATE_EMAIL)) {
      $messages[] = $this->buildErrorMessage('invalid', $path . '.email', 'Buyer email is invalid.');
    }

    if (!empty($buyer['phone_number']) && !preg_match('/^\+[1-9]\d{1,14}$/', (string)$buyer['phone_number'])) {
      $messages[] = $this->buildErrorMessage('invalid', $path . '.phone_number', 'Buyer phone number must follow E.164.');
    }

    return $messages;
  }

  /**
   * Validate message structure.
   *
   * @param array $message
   * @param string $path
   * @return array
   */
  private function validateMessage(array $message, string $path): array
  {
    $messages = [];
    $type = $message['type'] ?? null;

    if ($type !== 'info' && $type !== 'error') {
      $messages[] = $this->buildErrorMessage('invalid', $path . '.type', 'Message type must be info or error.');
      return $messages;
    }

    if (empty($message['content_type']) || !in_array($message['content_type'], ['plain','markdown'], true)) {
      $messages[] = $this->buildErrorMessage('invalid', $path . '.content_type', 'Message content_type must be plain or markdown.');
    }

    if (empty($message['content'])) {
      $messages[] = $this->buildErrorMessage('missing', $path . '.content', 'Message content is required.');
    }

    if ($type === 'info') {
      if (empty($message['param'])) {
        $messages[] = $this->buildErrorMessage('missing', $path . '.param', 'Info message param is required.');
      }
    }

    if ($type === 'error') {
      $allowedCodes = ['missing','invalid','out_of_stock','payment_declined','requires_sign_in','requires_3ds'];
      if (empty($message['code']) || !in_array($message['code'], $allowedCodes, true)) {
        $messages[] = $this->buildErrorMessage('invalid', $path . '.code', 'Error code is invalid.');
      }
    }

    return $messages;
  }

  /**
   * Validate link structure.
   *
   * @param array $link
   * @param string $path
   * @return array
   */
  private function validateLink(array $link, string $path): array
  {
    $messages = [];
    $allowedTypes = ['terms_of_use','privacy_policy','seller_shop_policies'];

    if (empty($link['type']) || !in_array($link['type'], $allowedTypes, true)) {
      $messages[] = $this->buildErrorMessage('invalid', $path . '.type', 'Link type is invalid.');
    }

    if (empty($link['url']) || !filter_var($link['url'], FILTER_VALIDATE_URL)) {
      $messages[] = $this->buildErrorMessage('invalid', $path . '.url', 'Link URL is invalid.');
    }

    return $messages;
  }

  /**
   * Validate payment data structure.
   *
   * @param array $paymentData
   * @param string $path
   * @return array
   */
  private function validatePaymentData(array $paymentData, string $path): array
  {
    $messages = [];
    $providers = ['stripe','adyen','braintree'];

    if (empty($paymentData['token'])) {
      $messages[] = $this->buildErrorMessage('missing', $path . '.token', 'Payment token is required.');
    }

    if (empty($paymentData['provider']) || !in_array($paymentData['provider'], $providers, true)) {
      $messages[] = $this->buildErrorMessage('invalid', $path . '.provider', 'Payment provider is invalid.');
    }

    if (!empty($paymentData['billing_address']) && is_array($paymentData['billing_address'])) {
      $messages = array_merge($messages, $this->validateAddress($paymentData['billing_address'], $path . '.billing_address'));
    }

    return $messages;
  }

  /**
   * Handle delegated payment vaulting request.
   *
   * @param array $input
   * @param array $headers
   * @return array
   */
  public function handleDelegatePayment(array $input, array $headers = []): array
  {
    $error = $this->validateDelegatePaymentInput($input);
    if (!empty($error)) {
      return [
        'status' => $error['status'],
        'body' => $error['body']
      ];
    }

    $metadata = is_array($input['metadata'] ?? null) ? $input['metadata'] : [];
    if (!empty($headers['idempotency_key'])) {
      $metadata['idempotency_key'] = $headers['idempotency_key'];
    }
    if (!empty($headers['request_id'])) {
      $metadata['request_id'] = $headers['request_id'];
    }

    return [
      'status' => 201,
      'body' => [
        'id' => uniqid('vt_'),
        'created' => gmdate('c'),
        'metadata' => $metadata
      ]
    ];
  }

  /**
   * Validate delegated payment input payload.
   *
   * @param array $input
   * @return array
   */
  private function validateDelegatePaymentInput(array $input): array
  {
    if (empty($input['payment_method']) || !is_array($input['payment_method'])) {
      return $this->delegatePaymentError(400, 'invalid_request', 'invalid_request', 'Missing payment_method.', '$.payment_method');
    }

    $paymentMethod = $input['payment_method'];
    if (($paymentMethod['type'] ?? '') !== 'card') {
      return $this->delegatePaymentError(400, 'invalid_request', 'invalid_request', 'payment_method.type must be card.', '$.payment_method.type');
    }

    $cardNumberType = $paymentMethod['card_number_type'] ?? null;
    if (!in_array($cardNumberType, ['fpan','network_token'], true)) {
      return $this->delegatePaymentError(400, 'invalid_request', 'invalid_request', 'payment_method.card_number_type must be fpan or network_token.', '$.payment_method.card_number_type');
    }

    if (empty($paymentMethod['number'])) {
      return $this->delegatePaymentError(400, 'invalid_request', 'invalid_request', 'payment_method.number is required.', '$.payment_method.number');
    }
    if (!preg_match('/^\d{12,19}$/', (string)$paymentMethod['number'])) {
      return $this->delegatePaymentError(422, 'invalid_request', 'invalid_card', 'payment_method.number is invalid.', '$.payment_method.number');
    }

    if (!empty($paymentMethod['exp_month']) && strlen((string)$paymentMethod['exp_month']) > 2) {
      return $this->delegatePaymentError(400, 'invalid_request', 'invalid_request', 'payment_method.exp_month is invalid.', '$.payment_method.exp_month');
    }
    if (!empty($paymentMethod['exp_year']) && strlen((string)$paymentMethod['exp_year']) > 4) {
      return $this->delegatePaymentError(400, 'invalid_request', 'invalid_request', 'payment_method.exp_year is invalid.', '$.payment_method.exp_year');
    }
    if (!empty($paymentMethod['cvc']) && strlen((string)$paymentMethod['cvc']) > 4) {
      return $this->delegatePaymentError(400, 'invalid_request', 'invalid_request', 'payment_method.cvc is invalid.', '$.payment_method.cvc');
    }
    if (!empty($paymentMethod['iin']) && strlen((string)$paymentMethod['iin']) > 6) {
      return $this->delegatePaymentError(400, 'invalid_request', 'invalid_request', 'payment_method.iin is invalid.', '$.payment_method.iin');
    }
    if (!empty($paymentMethod['display_last4']) && strlen((string)$paymentMethod['display_last4']) > 4) {
      return $this->delegatePaymentError(400, 'invalid_request', 'invalid_request', 'payment_method.display_last4 is invalid.', '$.payment_method.display_last4');
    }

    $fundingType = $paymentMethod['display_card_funding_type'] ?? null;
    if (!in_array($fundingType, ['credit','debit','prepaid'], true)) {
      return $this->delegatePaymentError(400, 'invalid_request', 'invalid_request', 'payment_method.display_card_funding_type is invalid.', '$.payment_method.display_card_funding_type');
    }

    if (!isset($input['allowance']) || !is_array($input['allowance'])) {
      return $this->delegatePaymentError(400, 'invalid_request', 'invalid_request', 'allowance is required.', '$.allowance');
    }
    $allowance = $input['allowance'];
    if (($allowance['reason'] ?? '') !== 'one_time') {
      return $this->delegatePaymentError(400, 'invalid_request', 'invalid_request', 'allowance.reason must be one_time.', '$.allowance.reason');
    }
    if (!isset($allowance['max_amount']) || !is_numeric($allowance['max_amount']) || (int)$allowance['max_amount'] < 0) {
      return $this->delegatePaymentError(400, 'invalid_request', 'invalid_request', 'allowance.max_amount must be a non-negative integer.', '$.allowance.max_amount');
    }
    if (empty($allowance['currency']) || !preg_match('/^[a-z]{3}$/', (string)$allowance['currency'])) {
      return $this->delegatePaymentError(400, 'invalid_request', 'invalid_request', 'allowance.currency must be ISO-4217 lower case.', '$.allowance.currency');
    }
    if (empty($allowance['checkout_session_id'])) {
      return $this->delegatePaymentError(400, 'invalid_request', 'invalid_request', 'allowance.checkout_session_id is required.', '$.allowance.checkout_session_id');
    }
    if (empty($allowance['merchant_id']) || strlen((string)$allowance['merchant_id']) > 256) {
      return $this->delegatePaymentError(400, 'invalid_request', 'invalid_request', 'allowance.merchant_id is invalid.', '$.allowance.merchant_id');
    }
    if (empty($allowance['expires_at']) || strtotime((string)$allowance['expires_at']) === false) {
      return $this->delegatePaymentError(400, 'invalid_request', 'invalid_request', 'allowance.expires_at must be RFC3339.', '$.allowance.expires_at');
    }

    if (empty($input['risk_signals']) || !is_array($input['risk_signals'])) {
      return $this->delegatePaymentError(400, 'invalid_request', 'invalid_request', 'risk_signals is required.', '$.risk_signals');
    }
    foreach ($input['risk_signals'] as $index => $signal) {
      $signalPath = '$.risk_signals[' . $index . ']';
      if (empty($signal['type'])) {
        return $this->delegatePaymentError(400, 'invalid_request', 'invalid_request', 'risk_signals.type is required.', $signalPath . '.type');
      }
      if (!isset($signal['score']) || !is_numeric($signal['score'])) {
        return $this->delegatePaymentError(400, 'invalid_request', 'invalid_request', 'risk_signals.score is required.', $signalPath . '.score');
      }
      if (empty($signal['action']) || !in_array($signal['action'], ['blocked','manual_review','authorized'], true)) {
        return $this->delegatePaymentError(400, 'invalid_request', 'invalid_request', 'risk_signals.action is invalid.', $signalPath . '.action');
      }
    }

    if (!isset($input['metadata']) || !is_array($input['metadata'])) {
      return $this->delegatePaymentError(400, 'invalid_request', 'invalid_request', 'metadata is required.', '$.metadata');
    }

    if (!empty($input['billing_address']) && is_array($input['billing_address'])) {
      $addressMessages = $this->validateDelegateAddress($input['billing_address'], '$.billing_address');
      if (!empty($addressMessages)) {
        $first = $addressMessages[0];
        return $this->delegatePaymentError(400, 'invalid_request', 'invalid_request', $first['content'], $first['param']);
      }
    }

    return [];
  }

  /**
   * Build delegate payment error response.
   *
   * @param int $status
   * @param string $type
   * @param string $code
   * @param string $message
   * @param string $param
   * @return array
   */
  private function delegatePaymentError(int $status, string $type, string $code, string $message, string $param): array
  {
    return [
      'status' => $status,
      'body' => [
        'type' => $type,
        'code' => $code,
        'message' => $message,
        'param' => $param
      ]
    ];
  }

  /**
   * Validate delegated payment address (state optional).
   *
   * @param array $address
   * @param string $path
   * @return array
   */
  private function validateDelegateAddress(array $address, string $path): array
  {
    $required = ['name','line_one','city','country','postal_code'];
    $messages = [];

    foreach ($required as $field) {
      if (empty($address[$field])) {
        $messages[] = $this->buildErrorMessage('missing', $path . '.' . $field, 'Address field is required.');
      }
    }

    $lengths = [
      'name' => 256,
      'line_one' => 60,
      'line_two' => 60,
      'city' => 60,
      'state' => 60,
      'country' => 60,
      'postal_code' => 20
    ];

    foreach ($lengths as $field => $max) {
      if (!empty($address[$field]) && strlen((string)$address[$field]) > $max) {
        $messages[] = $this->buildErrorMessage('invalid', $path . '.' . $field, 'Address field exceeds max length.');
      }
    }

    if (!empty($address['country']) && !preg_match('/^[A-Z]{2}$/', (string)$address['country'])) {
      $messages[] = $this->buildErrorMessage('invalid', $path . '.country', 'Country must be ISO 3166-1 alpha-2.');
    }

    if (!empty($address['state']) && !preg_match('/^[A-Z0-9-]{1,10}$/', (string)$address['state'])) {
      $messages[] = $this->buildErrorMessage('invalid', $path . '.state', 'State must be ISO 3166-2.');
    }

    return $messages;
  }

  /**
   * Normalize items input to hydrated product items.
   *
   * @param array $itemsInput
   * @return array
   */
  private function normalizeItemsInput(array $itemsInput): array
  {
    if (empty($itemsInput)) {
      return [];
    }

    $itemsMap = [];
    $requiresHydration = false;
    $hasMissingPrice = false;

    foreach ($itemsInput as $key => $value) {
      if (is_array($value)) {
        if (isset($value['id']) && isset($value['quantity'])) {
          $itemsMap[$value['id']] = $value['quantity'];
          if (!isset($value['unit_price']) && !isset($value['price'])) {
            $hasMissingPrice = true;
          }
        }
      } elseif (is_numeric($value)) {
        $itemsMap[$key] = $value;
        $requiresHydration = true;
      }
    }

    if (($requiresHydration || $hasMissingPrice) && !empty($itemsMap)) {
      return $this->buildItemsFromIds($itemsMap);
    }

    return $itemsInput;
  }

  // ---
  ## Product Catalog Synchronization

  /**
   * Retrieves all available, in-stock, and visible products to generate the catalog data required for the ACP (Agent Controlled Purchase) agent.
   *
   * The data includes product details, pricing, stock, images, categories, shipping information, and promotional metadata.
   *
   * @return array A list of products formatted for the OpenAI ACP specification.
   */
  public function getProducts(): array
  {
    $language_id = $this->lang->getId();
    $catalog = [];
    $baseUrl = $this->getBaseShopUrl();
    $storeCountry = $this->getStoreCountryIso();
    $sellerName = \defined('STORE_NAME') ? STORE_NAME : 'ClicShopping';
    $sellerUrl = $baseUrl;
    $policyUrl = $baseUrl . 'index.php?Info&Content&pagesId=4';
    $currencyCode = \defined('DEFAULT_CURRENCY') ? DEFAULT_CURRENCY : 'EUR';

    $Qproducts = $this->db->prepare('SELECT p.products_id,
                                             p.products_model,
                                             pd.products_name,
                                             pd.products_description,
                                             p.products_price,
                                             p.products_quantity,
                                             p.products_image_small,
                                             p.products_image_medium,
                                             p.products_image_zoom,
                                             p.products_weight,
                                             p.products_dimension_depth,
                                             p.products_dimension_width,
                                             p.products_dimension_height,
                                             p.products_min_qty_order,
                                             p.products_price_kilo,
                                             pd.products_description_summary,
                                             pd.products_head_title_tag,
                                             pd.products_head_desc_tag,
                                             pd.products_head_keywords_tag,
                                             pd.products_shipping_delay,
                                             p.products_sku,
                                             p.products_ean,
                                             p.parent_id,
                                             GROUP_CONCAT(cd.categories_name) AS categories,
                                             pc.categories_id
                                      FROM :table_products p
                                      LEFT JOIN :table_products_description pd ON p.products_id = pd.products_id
                                      LEFT JOIN :table_products_to_categories pc ON p.products_id = pc.products_id
                                      LEFT JOIN :table_categories_description cd ON pc.categories_id = cd.categories_id
                                      WHERE p.products_status = 1
                                        AND p.products_id = pd.products_id
                                        AND pd.language_id = :language_id
                                        AND cd.language_id = :language_id
                                        AND p.products_view = 1
                                        AND p.products_archive = 0
                                        AND p.products_quantity > 0
                                      GROUP BY p.products_id');

    $Qproducts->bindInt(':language_id', $language_id);
    $Qproducts->execute();

    while ($p = $Qproducts->fetch()) {
      $productId = (int)$p['products_id'];
      $brandName = ManufacturerAdmin::getManufacturerName($productId);
      $favorites = $this->getFavorites($productId);
      $featured = $this->getFeatured($productId);
      $special = $this->getSpecial($productId);

      $productUrl = $baseUrl . 'index.php?Products&Description&products_id=' . $productId;
      $imageUrl = $p['products_image_medium'] ?: ($p['products_image_small'] ?: $p['products_image_zoom']);
      $imageUrl = $this->buildAbsoluteUrl($imageUrl);
      $availability = ((int)$p['products_quantity'] > 0) ? 'in_stock' : 'out_of_stock';
      $itemId = !empty($p['products_sku']) ? $p['products_sku'] : (string)$productId;
      $categoryPath = null;
      if (!empty($p['categories'])) {
        $categoryPath = implode(' > ', array_map('trim', explode(',', $p['categories'])));
      }
      $length = (float)$p['products_dimension_depth'];
      $width = (float)$p['products_dimension_width'];
      $height = (float)$p['products_dimension_height'];
      $hasDimensions = $length > 0 || $width > 0 || $height > 0;
      $dimensions = $hasDimensions ? sprintf('%sx%sx%s cm', $length, $width, $height) : null;
      $weight = (float)$p['products_weight'];

      $feedExtras = [
        'age_restriction' => 18
      ];
      if ($categoryPath !== null) {
        $feedExtras['product_category'] = $categoryPath;
      }
      if ($hasDimensions) {
        $feedExtras['dimensions'] = $dimensions;
        $feedExtras['length'] = (string)$length;
        $feedExtras['width'] = (string)$width;
        $feedExtras['height'] = (string)$height;
        $feedExtras['dimensions_unit'] = 'cm';
      }
      if ($weight > 0) {
        $feedExtras['weight'] = (string)$weight;
        $feedExtras['item_weight_unit'] = 'kg';
      }

      $catalog[] = array_merge([
        'id' => (string)$productId,
        'item_id' => $itemId,
        'title' => $p['products_name'],
        'description' => $p['products_description'],
        'url' => $productUrl,
        'brand' => $brandName ?: $sellerName,
        'image_url' => $imageUrl,
        'price' => number_format((float)$p['products_price'], 2, '.', '') . ' ' . $currencyCode,
        'availability' => $availability,
        'is_eligible_search' => true,
        'is_eligible_checkout' => ($availability === 'in_stock'),
        'seller_name' => $sellerName,
        'seller_url' => $sellerUrl,
        'seller_privacy_policy' => $policyUrl,
        'seller_tos' => $policyUrl,
        'return_policy' => $policyUrl,
        'target_countries' => [$storeCountry],
        'store_country' => $storeCountry,
        'currency' => $currencyCode,
        'price_amount' => (float)$p['products_price'],
        'images' => [
          $p['products_image_small'],
          $p['products_image_medium'],
          $p['products_image_zoom']
        ],
        'in_stock' => ((int)$p['products_quantity'] > 0),
        'available_quantity' => (int)$p['products_quantity'],
        'categories' => $p['categories'] !== null ? explode(',', $p['categories']) : [],
        'shipping' => [
          'weight_kg' => (float)$p['products_weight'],
          'dimensions_cm' => [
            'length' => (float)$p['products_dimension_depth'], // Depth mapped as Length
            'width' => (float)$p['products_dimension_width'],
            'height' => (float)$p['products_dimension_height']
          ],
          'methods' => ['standard', 'express'] // Simplified shipping methods
        ],
        'metadata' => [
          'brand' => $brandName,
          'sku' => $p['products_sku'],
          'ean' => $p['products_ean'],
          'model' => $p['products_model'],
          'min_qty_order' => $p['products_min_qty_order'],
          'price_kilo' => $p['products_price_kilo'],
          'description_summary' => $p['products_description_summary'],
          'head_title_tag' => $p['products_head_title_tag'],
          'head_desc_tag' => $p['products_head_desc_tag'],
          'head_keywords_tag' => $p['products_head_keywords_tag'],
          'shipping_delay' => $p['products_shipping_delay'],
          'parent_id' => $p['parent_id'],
          'categories_id' => $p['categories_id'],
          'featured' => [
            'active' => !empty($featured),
            'start_date' => $featured['scheduled_date'] ?? null,
            'end_date' => $featured['expires_date'] ?? null
          ],
          'favorite' => [
            'active' => !empty($favorites),
            'start_date' => $favorites['scheduled_date'] ?? null,
            'end_date' => $favorites['expires_date'] ?? null
          ],
          'promotion' => [
            'active' => !empty($special),
            'specials_new_products_price' => $special['specials_new_products_price'] ?? null,
            'flash_discount' => $special['flash_discount'] ?? null,
            'start_date' => $special['scheduled_date'] ?? null,
            'end_date' => $special['expires_date'] ?? null
          ]
        ]
      ], $feedExtras);
    }

    return $catalog;
  }

  /**
   * Retrieves promotion data for products marked as "favorites".
   *
   * @param int $id The ID of the product.
   * @return array An array containing scheduled and expiration dates, or an empty array if not a favorite.
   */
  public function getFavorites(int $id): array
  {
    $QFavorites = $this->db->prepare('select products_favorites_id,
                                                   scheduled_date,
                                                   expires_date
                                              from :table_products_favorites
                                              where status = 1
                                              and products_id = :products_id
                                           ');

    $QFavorites->bindInt(':products_id', $id);
    $QFavorites->execute();
    $favorites_array = [];

    if (!empty($QFavorites->valueInt('products_favorites_id'))) {
      $favorites_array = [
        'scheduled_date' => $QFavorites->value('scheduled_date'),
        'expires_date' => $QFavorites->value('expires_date')
      ];

    }

    return $favorites_array;
  }

  /**
   * Retrieves promotion data for "featured" products.
   *
   * @param int $id The ID of the product.
   * @return array An array containing scheduled and expiration dates, or an empty array if not featured.
   */
  private function getFeatured(int $id): array
  {
    $QFavorites = $this->db->prepare('select products_featured_id,
                                                   scheduled_date,
                                                   expires_date
                                              from :table_products_featured
                                              where status = 1
                                              and products_id = :products_id
                                           ');

    $QFavorites->bindInt(':products_id',$id);
    $QFavorites->execute();
    $favorites_array = [];

    if (!empty($QFavorites->valueInt('products_featured_id'))) {
      $favorites_array = [
        'scheduled_date' => $QFavorites->value('scheduled_date'),
        'expires_date' => $QFavorites->value('expires_date')
      ];

    }

    return $favorites_array;
  }

  /**
   * Retrieves data for "special" (sale) products.
   *
   * @param int $id The ID of the product.
   * @return array An array containing pricing and scheduling details, or an empty array if not on special.
   */
  private function getSpecial(int $id): array
  {
    $Qspecials = $this->db->prepare('select specials_id,
                                             scheduled_date,
                                             expires_date,
                                             specials_new_products_price,
                                             flash_discount
                                        from :table_specials
                                        where status = 1
                                        and products_id = :products_id
                                     ');

    $Qspecials->bindInt(':products_id',$id);
    $Qspecials->execute();
    $specials_array = [];

    if (!empty($Qspecials->valueInt('specials_id'))) {
      $specials_array = [
        'scheduled_date' => $Qspecials->value('scheduled_date'),
        'expires_date' => $Qspecials->value('expires_date'),
        'specials_new_products_price' => $Qspecials->value('specials_new_products_price'),
        'flash_discount' => $Qspecials->value('flash_discount')
      ];

    }

    return $specials_array;
  }

  /**
   * Builds an array of product items, including detailed information, from a simple list of product IDs and quantities.
   *
   * This is used to hydrate the checkout session items from minimal input.
   *
   * @param array $productIdsQuantities An associative array where keys are product IDs and values are quantities.
   * @return array A list of product items formatted for the ACP checkout session.
   */
  public function buildItemsFromIds(array $productIdsQuantities): array
  {
    $items = [];
    $language_id = $this->lang->getId();

    foreach ($productIdsQuantities as $pid => $qty) {
      $Qproducts = $this->db->prepare('SELECT p.products_id, 
                                             p.products_model,
                                             pd.products_name, 
                                             pd.products_description, 
                                             p.products_price,
                                             p.products_quantity, 
                                             p.products_image_small,
                                             p.products_image_medium,
                                             p.products_image_zoom,
                                             p.products_weight,
                                             p.products_dimension_depth, 
                                             p.products_dimension_width, 
                                             p.products_dimension_height,
                                             p.products_min_qty_order,
                                             p.products_price_kilo,
                                             pd.products_description_summary,
                                             pd.products_head_title_tag,
                                             pd.products_head_desc_tag,
                                             pd.products_head_keywords_tag,
                                             pd.products_shipping_delay,
                                             p.products_sku,
                                             p.products_ean, 
                                             p.parent_id,
                                             GROUP_CONCAT(cd.categories_name) AS categories,
                                             pc.categories_id
                                      FROM :table_products p
                                      LEFT JOIN :table_products_description pd ON p.products_id = pd.products_id
                                      LEFT JOIN :table_products_to_categories pc ON p.products_id = pc.products_id
                                      LEFT JOIN :table_categories_description cd ON pc.categories_id = cd.categories_id
                                      WHERE p.products_status = 1
                                        AND p.products_id = pd.products_id
                                        AND pd.language_id = :language_id
                                        AND cd.language_id = :language_id
                                        AND p.products_view = 1
                                        AND p.products_archive = 0
                                        AND p.products_quantity > 0
                                        AND p.products_id = :product_id
                                      GROUP BY p.products_id
                                  '
      );

      $Qproducts->bindInt(':language_id', $language_id);
      $Qproducts->bindInt(':product_id', (int)$pid);
      $Qproducts->execute();

      while ($p = $Qproducts->fetch()) {
        $productId = (int)$p['products_id'];
        $brand_name = ManufacturerAdmin::getManufacturerName($productId);
        $favorites = $this->getFavorites($productId);
        $featured = $this->getFeatured($productId);
        $special =  $this->getSpecial($productId);
        $items[] = [
          'id' => (string)$productId,
          'title' => $p['products_name'],
          'description' => $p['products_description'],
          'currency' => 'EUR', // Hardcoded currency
          'price' => (float)$p['products_price'],
          'quantity' => (int)$qty,
          'unit_price' => (float)$p['products_price'],
          'images' => [
            $p['products_image_small'],
            $p['products_image_medium'],
            $p['products_image_zoom']
          ],
          'in_stock' => ((int)$p['products_quantity'] > 0),
          'available_quantity' => (int)$p['products_quantity'],
          'categories' => $p['categories'] !== null ? explode(',', $p['categories']) : [],
          'shipping' => [
            'weight_kg' => (float)$p['products_weight'],
            'dimensions_cm' => [
              'length' => (float)$p['products_dimension_depth'],
              'width' => (float)$p['products_dimension_width'],
              'height' => (float)$p['products_dimension_height']
            ],
            'methods' => ['standard', 'express']
          ],

          'metadata' => [
            'brand' => $brand_name,
            'sku' => $p['products_sku'],
            'ean' => $p['products_ean'],
            'model' => $p['products_model'],
            'min_qty_order' => $p['products_min_qty_order'],
            'price_kilo' => $p['products_price_kilo'],
            'description_summary' => $p['products_description_summary'],
            'head_title_tag' => $p['products_head_title_tag'],
            'head_desc_tag' => $p['products_head_desc_tag'],
            'head_keywords_tag' => $p['products_head_keywords_tag'],
            'shipping_delay' => $p['products_shipping_delay'],
            'parent_id' => $p['parent_id'],
            'categories_id' => $p['categories_id'],

            'featured' =>[
              'active' => !empty($featured),
              'start_date' => $featured['scheduled_date'] ?? null,
              'end_date' => $featured['expires_date'] ?? null
            ],

            'favorite' =>[
              'active' => !empty($favorites),
              'start_date' => $favorites['scheduled_date'] ?? null,
              'end_date' => $favorites['expires_date'] ?? null
            ],

            'promotion' => [
              'active' => !empty($special),
              'specials_new_products_price' => $special['specials_new_products_price'] ?? null,
              'flash_discount' => $special['flash_discount'] ?? null,
              'start_date' => $special['scheduled_date'] ?? null,
              'end_date' => $special['expires_date'] ?? null
            ]
          ]
        ];
      }
    }

    return $items;
  }

  // ---
  ## Checkout Session Management

  /**
   * Creates a new ACP checkout session based on the provided input.
   *
   * It calculates totals, saves the session data to a JSON file, and sends an `order.created` webhook event.
   *
   * @param array $input Associative array containing 'items', 'shipping_address', 'billing_address', and 'metadata'.
   * @return array The newly created checkout session data.
   */
  public function createSession(array $input): array
  {
    $sessionId = uniqid('cs_');
    $items = $this->normalizeItemsInput($input['items'] ?? []);
    $shipping = $input['shipping_address'] ?? [];
    $fulfillmentAddress = $input['fulfillment_address'] ?? $shipping;
    $billing = $input['billing_address'] ?? [];

    $taxRate = 0.08;
    $shippingSubtotal = $this->toMinorUnits((float)($shipping['cost'] ?? 0));
    $shippingTax = (int)round($shippingSubtotal * $taxRate);
    $lineItems = $this->buildLineItems($items, $taxRate);
    $fulfillmentOptions = $this->buildFulfillmentOptions($shippingSubtotal, $shippingTax);
    $totals = $this->buildTotals($lineItems, $shippingSubtotal, $shippingTax);

    $fulfillmentOptionId = $fulfillmentOptions[0]['id'] ?? null;

    $session = [
      "id" => $sessionId,
      "status" => "ready_for_payment",
      "currency" => "eur",
      "items" => $items,
      "line_items" => $lineItems,
      "totals" => $totals,
      "fulfillment_options" => $fulfillmentOptions,
      "fulfillment_option_id" => $fulfillmentOptionId,
      "fulfillment_address" => $fulfillmentAddress,
      "shipping_address" => $shipping,
      "billing_address" => $billing,
      "buyer" => $input['buyer'] ?? [],
      "payment_provider" => $this->buildPaymentProvider(),
      "messages" => $input['messages'] ?? [],
      "links" => $input['links'] ?? [],
      "payment" => ["status" => "pending","methods" => ["card"]],
      "metadata" => $input['metadata'] ?? []
    ];

    file_put_contents( $this->dirSession . '/' . $sessionId . '.json', json_encode(["checkout_session"=>$session], JSON_UNESCAPED_SLASHES));

    return $session;
  }

  /**
   * Updates an existing ACP checkout session with new data.
   *
   * It recalculates totals based on updated items, saves the changes, and sends an `order.updated` webhook event.
   *
   * @param string $sessionId The ID of the session to update.
   * @param array $input The data fields to merge (items, addresses, metadata).
   * @return array|null The updated checkout session data, or null if the session file doesn't exist.
   */
  public function updateSession(string $sessionId, array $input): ?array
  {
    $file =  $this->dirSession . '/' . $sessionId . '.json';

    if (!file_exists($file)) {
      return null;
    }

    $session = json_decode(file_get_contents($file), true)['checkout_session'];

    $array_data = [
      'items',
      'line_items',
      'shipping_address',
      'fulfillment_address',
      'billing_address',
      'buyer',
      'payment_provider',
      'messages',
      'links',
      'metadata',
      'fulfillment_option_id'
    ];

    // Merge input data into session
    foreach ($array_data as $f) {
      if (isset($input[$f])) $session[$f] = $input[$f];
    }

    // Recalculate line items and totals
    $taxRate = 0.08;
    $items = $this->normalizeItemsInput($session['items'] ?? []);
    $session['items'] = $items;

    if (!empty($input['line_items']) && is_array($input['line_items'])) {
      $session['line_items'] = $input['line_items'];
      $needsRebuild = false;
      foreach ($session['line_items'] as $lineItem) {
        if (!isset($lineItem['base_amount'])) {
          $needsRebuild = true;
          break;
        }
      }
      if ($needsRebuild) {
        $session['line_items'] = $this->buildLineItems($items, $taxRate);
      }
    } else {
      $session['line_items'] = $this->buildLineItems($items, $taxRate);
    }

    $shippingSubtotal = $this->toMinorUnits((float)($session['shipping_address']['cost'] ?? 0));
    $shippingTax = (int)round($shippingSubtotal * $taxRate);
    $session['fulfillment_options'] = $this->buildFulfillmentOptions($shippingSubtotal, $shippingTax);
    if (isset($session['fulfillment_address']) && empty($session['fulfillment_address']) && isset($session['shipping_address'])) {
      $session['fulfillment_address'] = $session['shipping_address'];
    }
    if (empty($session['fulfillment_option_id']) && !empty($session['fulfillment_options'][0]['id'])) {
      $session['fulfillment_option_id'] = $session['fulfillment_options'][0]['id'];
    }
    $session['totals'] = $this->buildTotals($session['line_items'], $shippingSubtotal, $shippingTax);
    if (empty($session['payment_provider'])) {
      $session['payment_provider'] = $this->buildPaymentProvider();
    }

    file_put_contents($file, json_encode(["checkout_session" => $session], JSON_UNESCAPED_SLASHES));
    return $session;
  }

  /**
   * Completes a checkout session by initiating a Stripe Payment Intent.
   *
   * This is used for a direct payment flow. It updates the session status to 'completed'
   * and stores Stripe-specific payment data.
   *
   * @param string $sessionId The ID of the session to complete.
   * @param array $input Input data, mainly containing the chosen payment method type.
   * @return array|null The updated session data, or null if the session file doesn't exist.
   */
  public function completeSessionWithStripe(string $sessionId, array $input): ?array
  {
    $file =  $this->dirSession . '/' . $sessionId . '.json';

    if (!file_exists($file)) {
      return null;
    }

    $session = json_decode(file_get_contents($file), true)['checkout_session'];
    $totalCents = intval($session['total'] * 100);

    // Create Stripe PaymentIntent
    $paymentIntent = PaymentIntent::create([
      'amount' => $totalCents,
      'currency' => strtolower($session['currency']),
      'payment_method_types' => ['card'],
      'metadata' => [
        'checkout_session_id' => $sessionId,
        'internal_reference' => $session['metadata']['internal_reference'] ?? ''
      ]
    ]);

    $session['status'] = 'completed';

    $session['payment'] = [
      'status' => 'pending',
      'stripe_payment_intent_id' => $paymentIntent->id,
      'client_secret' => $paymentIntent->client_secret,
      'method' => $input['payment_method']['type'] ?? 'card'
    ];
    $session['payment_data'] = [
      'token' => $paymentIntent->id,
      'provider' => 'stripe',
      'billing_address' => $input['billing_address'] ?? ($session['billing_address'] ?? null)
    ];

    // Update addresses and metadata if provided in the completion input
    if (isset($input['billing_address'])) $session['billing_address'] = $input['billing_address'];
    if (isset($input['shipping_address'])) $session['shipping_address'] = $input['shipping_address'];
    if (isset($input['fulfillment_address'])) $session['fulfillment_address'] = $input['fulfillment_address'];
    if (isset($input['metadata'])) $session['metadata'] = $input['metadata'];
    if (isset($input['buyer'])) $session['buyer'] = $input['buyer'];
    if (isset($input['shipping_address']) && empty($session['fulfillment_address'])) {
      $session['fulfillment_address'] = $input['shipping_address'];
    }
    if (empty($session['fulfillment_option_id']) && !empty($session['fulfillment_options'][0]['id'])) {
      $session['fulfillment_option_id'] = $session['fulfillment_options'][0]['id'];
    }

    file_put_contents($file, json_encode(["checkout_session" => $session], JSON_UNESCAPED_SLASHES));
    return $session;
  }

  /**
   * Completes a checkout session using an ACP Delegated Payment payload (e.g., a payment token).
   *
   * This flow signals that payment details have been captured and tokenized by a third-party
   * (like Stripe in a delegated flow) and are now available for merchant capture.
   *
   * @param string $sessionId The ID of the session to complete.
   * @param array $input Input data, including the `delegated_payment` payload.
   * @return array|null The updated session data, or null if the session is not found or no delegated payment payload is present.
   */
  public function completeSessionWithDelegatedPayment(string $sessionId, array $input): ?array
  {
    $file =  $this->dirSession . '/' . $sessionId . '.json';

    if (!file_exists($file)) {
      return null;
    }

    $session = json_decode(file_get_contents($file), true)['checkout_session'];

    $delegated = $input['delegated_payment'] ?? null;
    $paymentData = $input['payment_data'] ?? null;
    if (!$delegated && !$paymentData) {
      return null;
    }

    // Set session status to 'completed' (checkout phase finished)
    $session['status'] = 'completed';
    $session['payment'] = [
      'status' => 'pending', // Payment is pending merchant capture/confirmation
      'method' => 'delegated',
      'delegated' => [
        'psp' => $delegated['psp'] ?? ($paymentData['provider'] ?? 'stripe'),
        'token' => $delegated['token'] ?? ($paymentData['token'] ?? null),
        'max_amount' => $delegated['max_amount'] ?? null,
        'currency' => $delegated['currency'] ?? ($session['currency'] ?? 'EUR'),
        'expires_at' => $delegated['expires_at'] ?? null
      ]
    ];
    $session['payment_data'] = [
      'token' => $paymentData['token'] ?? ($delegated['token'] ?? null),
      'provider' => $paymentData['provider'] ?? ($delegated['psp'] ?? 'stripe'),
      'billing_address' => $paymentData['billing_address'] ?? ($input['billing_address'] ?? null)
    ];

    if (isset($input['billing_address'])) $session['billing_address'] = $input['billing_address'];
    if (isset($input['shipping_address'])) $session['shipping_address'] = $input['shipping_address'];
    if (isset($input['fulfillment_address'])) $session['fulfillment_address'] = $input['fulfillment_address'];
    if (isset($input['metadata'])) $session['metadata'] = $input['metadata'];
    if (isset($input['buyer'])) $session['buyer'] = $input['buyer'];
    if (isset($input['shipping_address']) && empty($session['fulfillment_address'])) {
      $session['fulfillment_address'] = $input['shipping_address'];
    }
    if (empty($session['fulfillment_option_id']) && !empty($session['fulfillment_options'][0]['id'])) {
      $session['fulfillment_option_id'] = $session['fulfillment_options'][0]['id'];
    }

    file_put_contents($file, json_encode(["checkout_session" => $session], JSON_UNESCAPED_SLASHES));
    return $session;
  }

  // ---
  ## Webhook and Session Retrieval


  /**
   * Lists all stored checkout sessions.
   *
   * @param bool $fullData Whether to return the full session array or just a list of session IDs.
   * @return array A list of sessions or session IDs.
   */
  public function listSessions(bool $fullData = true): array
  {
    $sessions = [];

    if (!is_dir($this->dirSession)) {
      return $sessions;
    }

    $files = glob($this->dirSession . '/*.json');

    foreach ($files as $file) {
      $sessionId = basename($file, '.json');

      if ($fullData) {
        $sessionData = json_decode(file_get_contents($file), true);
        if ($sessionData && isset($sessionData['checkout_session'])) {
          $sessions[] = $sessionData['checkout_session'];
        }
      } else {
        $sessions[] = $sessionId;
      }
    }

    return $sessions;
  }

  /**
   * Retrieves a specific checkout session by its ID.
   *
   * @param string $sessionId The ID of the session.
   * @return array|null The session data array, or null if the session file is not found.
   */
  public function getSessionById(string $sessionId): ?array
  {
    $file = $this->dirSession . '/' . $sessionId . '.json';

    if (!file_exists($file)) {
      return null;
    }

    $sessionData = json_decode(file_get_contents($file), true);

    if ($sessionData && isset($sessionData['checkout_session'])) {
      return $sessionData['checkout_session'];
    }

    return null;
  }

  /**
   * Deletes a checkout session file.
   *
   * @param string $sessionId The ID of the session to delete.
   * @return bool True on successful deletion, false if the file was not found.
   */
  public function deleteSession(string $sessionId): bool
  {
    $file = $this->dirSession . '/' . $sessionId . '.json';

    if (!file_exists($file)) {
      return false;
    }

    return unlink($file);
  }

  /**
   * Handles incoming Stripe webhook events.
   *
   * This method verifies the webhook signature and processes events, specifically
   * `payment_intent.succeeded`, to update the local session status and notify the ACP agent.
   *
   * @return void
   */
  public function handleStripeWebhook(): void
  {
    $payload = @file_get_contents('php://input');
    $sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
    // Note: The endpoint secret ('whsec_xxx') must be dynamically retrieved from the database in a production environment.
    $endpoint_secret = 'whsec_xxx';

    try {
      $event = Webhook::constructEvent($payload, $sig_header, $endpoint_secret);
    } catch (\Exception $e) {
      http_response_code(400);
      exit('Invalid payload');
    }

    if ($event->type === 'payment_intent.succeeded') {
      $pi = $event->data->object;
      $sessionId = $pi->metadata->checkout_session_id ?? null;

      if (!$sessionId) {
        http_response_code(404); exit('No session ID');
      }

      $file =  $this->dirSession . '/' . $sessionId . '.json';

      if (!file_exists($file)) {
        http_response_code(404);
        exit('Session not found');
      }

      $session = json_decode(file_get_contents($file), true)['checkout_session'];
      $session['payment']['status'] = 'succeeded';
      $session['payment']['transaction_id'] = $pi->id;

      file_put_contents($file, json_encode(["checkout_session" => $session], JSON_UNESCAPED_SLASHES));
    }

    http_response_code(200);
    echo json_encode(['received' => true]);
  }

  /**
   * Cancels a checkout session by setting status to canceled.
   *
   * @param string $sessionId
   * @return array|null
   */
  public function cancelSession(string $sessionId): ?array
  {
    $file = $this->dirSession . '/' . $sessionId . '.json';

    if (!file_exists($file)) {
      return null;
    }

    $session = json_decode(file_get_contents($file), true)['checkout_session'];
    $session['status'] = 'canceled';

    file_put_contents($file, json_encode(["checkout_session" => $session], JSON_UNESCAPED_SLASHES));
    return $session;
  }

  // ---
  ## Order Creation

  /**
   * Helper function to either create a new customer account or retrieve an existing one.
   *
   * @param array $customerData Customer data, typically derived from the session's address info.
   * @return array Result with 'status', 'message', and 'customer_id'.
   */
  private function createOrGetCustomer(array $customerData): array
  {
    // Check if the customer already exists by email
    if ($this->customerManager->emailExists($customerData['email_address'] ?? '')) {
      // If customer exists, we should ideally retrieve their ID, but current GptCustomerManager lacks getCustomerByEmail
      // For simplicity in this demo, we assume the customer needs to be created, or we'd need a way to link to an existing one.
      // Since the prompt shows a simple order creation flow, we'll try to create it and rely on the GptCustomerManager's check.
      $Qcustomer = $this->db->prepare('SELECT customers_id
                                      FROM :table_customers 
                                      WHERE customers_email_address = :customers_email_address
                                     ');
      $Qcustomer->bindValue(':customers_email_address', $customerData['email_address']);
      $Qcustomer->execute();

      if ($Qcustomer->fetch()) {
        $customerId = $Qcustomer->valueInt('customers_id');
        return [
          'status' => 'success',
          'message' => 'Customer account already exists and retrieved.',
          'customer_id' => $customerId
        ];
      }
}

    // If customer doesn't exist, create a new one
    $customerResult = $this->customerManager->createCustomerAccount($customerData);

    if ($customerResult['status'] === 'success') {
      return $customerResult;
    } else {
      // Fallback if creation fails (e.g., missing address fields)
      return [
        'status' => 'error',
        'message' => 'Failed to create customer account: ' . ($customerResult['message'] ?? 'Unknown error.'),
        'customer_id' => null
      ];
    }
}

  /**
   * Completes a session and creates the corresponding order.
   *
   * @param string $sessionId
   * @param array $input
   * @return array|null
   */
  public function completeSessionAndCreateOrder(string $sessionId, array $input): ?array
  {
    $session = null;

    if (!empty($input['payment_data']) || !empty($input['delegated_payment'])) {
      $session = $this->completeSessionWithDelegatedPayment($sessionId, $input);
    } else {
      $session = $this->completeSessionWithStripe($sessionId, $input);
    }

    if ($session === null) {
      return null;
    }

    $customerData = $input['customer_data'] ?? [];
    $paymentData = $input['payment_data'] ?? [];
    $orderResult = $this->createOrderFromSession($sessionId, $customerData, $paymentData);

    $order = null;
    if (($orderResult['status'] ?? '') === 'success' && !empty($orderResult['order_id'])) {
      $orderId = (string)$orderResult['order_id'];
      $order = [
        'id' => $orderId,
        'checkout_session_id' => $sessionId,
        'permalink_url' => CLICSHOPPING::link('Shop/index.php', 'Account&HistoryInfo&order_id=' . $orderId)
      ];
    }

    return [
      'checkout_session' => $session,
      'order' => $order,
      'result' => $orderResult
    ];
  }

  /**
   * Creates a final e-commerce order from a completed checkout session.
   *
   * This is the crucial step after payment/delegated payment is finalized. It handles
   * customer creation and passes control to the GptOrderManager for database persistence.
   *
   * @param string $sessionId The ID of the completed checkout session.
   * @param array $customerData Full customer details from the session (optional, can be derived from session).
   * @param array $paymentData Payment details (optional, can be derived from session).
   * @return array Result containing 'status', 'message', and the created 'order_id'.
   */
  public function createOrderFromSession(string $sessionId, array $customerData = [], array $paymentData = []): array
  {
    try {
      // Load session data
      $session = $this->getSessionById($sessionId);

      if (!$session) {
        return [
          'status' => 'error',
          'message' => 'Session not found',
          'order_id' => null
        ];
      }

      // Check if session is valid for order creation (e.g., completed or paid)
      if ($session['status'] !== 'completed' && $session['status'] !== 'order_created') {
        return [
          'status' => 'error',
          'message' => 'Session status is ' . $session['status'] . '. Order creation requires a "completed" or "paid" status.',
          'order_id' => null
        ];
      }

      // 1. Prepare customer data (prefer explicit data, fall back to session addresses)
      if (empty($customerData) && !empty($session['billing_address'])) {
        // Simplified conversion from session address to customerData structure
        $customerData = [
          'email_address' => $session['metadata']['customer_email'] ?? $session['billing_address']['email'] ?? 'unknown@example.com',
          'firstname' => $session['billing_address']['first_name'] ?? 'GPT',
          'lastname' => $session['billing_address']['last_name'] ?? 'Customer',
          'street_address' => $session['billing_address']['street_address'] ?? 'N/A',
          'city' => $session['billing_address']['city'] ?? 'N/A',
          'postcode' => $session['billing_address']['postcode'] ?? 'N/A',
          'country' => $session['billing_address']['country'] ?? 'FR', // ISO 2-letter code
          'telephone' => $session['billing_address']['telephone'] ?? 'N/A',
          'cellular_phone' => $session['billing_address']['cellular_phone'] ?? '',
          'company' => $session['billing_address']['company'] ?? '',
          'state' => $session['billing_address']['state'] ?? '',
        ];
      }

      // 2. Create or retrieve customer account
      $customerResult = $this->createOrGetCustomer($customerData);

      if ($customerResult['status'] !== 'success') {
        return $customerResult;
      }

      // Update customer data with the created customer ID
      $customerData['id'] = $customerResult['customer_id'];

      // 3. Create order using the order manager
      // GptOrderManager::createOrderFromSession handles the detailed logic
      $result = $this->orderManager->createOrderFromSession($session, $customerData, $paymentData);

      if ($result['status'] === 'success') {
        // 4. Update session with final order ID and status
        $session['order_id'] = $result['order_id'];
        $session['customer_id'] = $customerResult['customer_id'];
        $session['status'] = 'order_created';

        $file = $this->dirSession . '/' . $sessionId . '.json';
        file_put_contents($file, json_encode(["checkout_session" => $session], JSON_UNESCAPED_SLASHES));

        // 5. Send final webhook event
        $this->sendWebhookEvent('order.created', $session);
      }

      return $result;

    } catch (\Exception $e) {
      $this->logger->error('Failed to create order from session', [
        'event' => 'gpt_order_creation_error',
        'session_id' => $sessionId,
        'error' => $e->getMessage()
      ]);

      return [
        'status' => 'error',
        'message' => $e->getMessage(),
        'order_id' => null
      ];
    }
  }
}
