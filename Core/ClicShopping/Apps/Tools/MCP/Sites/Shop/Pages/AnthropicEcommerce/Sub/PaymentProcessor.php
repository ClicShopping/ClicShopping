<?php
/**
 *
 * @copyright 2008 - https://www.clicshopping.org
 * @Brand : ClicShoppingAI(TM) at Inpi all right Reserved
 * @Licence GPL 2 & MIT
 * @Info : https://www.clicshopping.org/forum/trademark/
 *
 */

namespace ClicShopping\Apps\Tools\MCP\Sites\Shop\Pages\AnthropicEcommerce\Sub;

/**
 * PaymentProcessor — AnthropicEcommerce MCP
 *
 * Handles payment validation, Stripe charge confirmation and delegated
 * payment vaulting for the AnthropicEcommerce MCP endpoint.
 *
 * Logic ported from GptRetailers (ACP) and PaymentProcessor (UCP) and
 * adapted for direct use by Sessions.php without any ACP/UCP dependency.
 *
 * Supported providers : stripe | adyen | braintree
 *
 * Usage (from Sessions::completeSession):
 * ----------------------------------------
 *   $processor = new PaymentProcessor();
 *
 *   // 1. Validate the incoming payment payload
 *   $errors = $processor->validate($paymentData, '$.payment');
 *   if (!empty($errors)) { ... }
 *
 *   // 2. Confirm / charge
 *   $result = $processor->process($session, $paymentData);
 *   if ($result['status'] !== 'succeeded') { ... }
 *
 *   // 3. Delegated payment vaulting (optional — ACP flow)
 *   $vault = $processor->handleDelegatedPayment($input, $headers);
 */
class PaymentProcessor
{
  /** Supported payment providers */
  private const PROVIDERS = ['stripe', 'adyen', 'braintree'];

  // =========================================================================
  // Public API
  // =========================================================================

  /**
   * Validate a payment payload.
   *
   * @param array  $paymentData  The payment array from the request body
   * @param string $path         JSON path prefix for error messages
   * @return array               List of error arrays (empty = valid)
   */
  public function validate(array $paymentData, string $path = '$.payment'): array
  {
    $errors = [];

    // Provider
    if (empty($paymentData['provider']) || !in_array(strtolower($paymentData['provider']), self::PROVIDERS, true)) {
      $errors[] = $this->error('invalid', $path . '.provider',
        'Payment provider is invalid. Supported: ' . implode(', ', self::PROVIDERS) . '.');
    }

    // Token / intent / method id — at least one required
    if (empty($paymentData['token'])
      && empty($paymentData['payment_intent_id'])
      && empty($paymentData['payment_method_id'])
    ) {
      $errors[] = $this->error('missing', $path . '.token',
        'One of token, payment_intent_id or payment_method_id is required.');
    }

    // Optional billing address
    if (!empty($paymentData['billing_address']) && is_array($paymentData['billing_address'])) {
      $errors = array_merge($errors, $this->validateAddress($paymentData['billing_address'], $path . '.billing_address'));
    }

    return $errors;
  }

  /**
   * Process (confirm) a payment.
   *
   * For Stripe: confirms the PaymentIntent via the Stripe API if a secret key
   * is configured; falls back to a test stub if running in test mode.
   *
   * @param array $session     The current checkout session data
   * @param array $paymentData Payment payload from the request body
   * @return array             ['status' => 'succeeded'|'failed', 'transaction_id' => '...', ...]
   */
  public function process(array $session, array $paymentData): array
  {
    $provider      = strtolower($paymentData['provider'] ?? '');
    $transactionId = $paymentData['payment_intent_id']
      ?? $paymentData['token']
      ?? $paymentData['payment_method_id']
      ?? null;

    return match ($provider) {
      'stripe'    => $this->processStripe($session, $paymentData, $transactionId),
      'adyen'     => $this->processAdyen($session, $paymentData, $transactionId),
      'braintree' => $this->processBraintree($session, $paymentData, $transactionId),
      default     => [
        'status'         => 'failed',
        'provider'       => $provider,
        'transaction_id' => $transactionId,
        'error'          => 'Unsupported payment provider.',
      ],
    };
  }

  /**
   * Handle delegated payment vaulting (ACP / Anthropic native wallet flow).
   *
   * Validates the incoming payload then returns a vault token that the agent
   * can use in subsequent session_complete calls.
   *
   * @param array $input   Raw request body
   * @param array $headers HTTP headers (idempotency_key, request_id)
   * @return array         ['status' => int, 'body' => array]
   */
  public function handleDelegatedPayment(array $input, array $headers = []): array
  {
    $error = $this->validateDelegatedPaymentInput($input);
    if (!empty($error)) {
      return $error;
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
      'body'   => [
        'id'       => uniqid('vt_'),
        'created'  => gmdate('c'),
        'metadata' => $metadata,
      ],
    ];
  }

  /**
   * Build the list of available payment providers for a session response.
   * Mirrors ACP buildPaymentProviders().
   *
   * @return array
   */
  public function buildPaymentProviders(): array
  {
    if ($this->isStripeEnabled()) {
      return [
        [
          'provider'                  => 'stripe',
          'supported_payment_methods' => ['card'],
        ],
      ];
    }

    return [];
  }

  // =========================================================================
  // Provider implementations
  // =========================================================================

  /**
   * Stripe — confirm a PaymentIntent.
   *
   * If a live/test secret key is configured the intent is confirmed via the
   * Stripe API. Without a key the call succeeds as a stub (useful for testing
   * without an account).
   */
  private function processStripe(array $session, array $paymentData, ?string $transactionId): array
  {
    $secretKey = $this->stripeSecretKey();

    // ---- stub / test mode (no key configured) ----
    if (empty($secretKey)) {
      return [
        'status'         => 'succeeded',
        'provider'       => 'stripe',
        'transaction_id' => $transactionId ?? ('pi_test_' . uniqid()),
        'mode'           => 'test_stub',
      ];
    }

    // ---- live / real Stripe call ----
    $intentId = $paymentData['payment_intent_id'] ?? null;

    // If a payment_method_id was passed instead, create a PaymentIntent first
    if (empty($intentId) && !empty($paymentData['payment_method_id'])) {
      $totalMinor = $this->sessionTotalMinor($session);
      $currency   = strtolower($session['currency'] ?? 'eur');

      $createResult = $this->stripeRequest('POST', '/v1/payment_intents', [
        'amount'               => $totalMinor,
        'currency'             => $currency,
        'payment_method'       => $paymentData['payment_method_id'],
        'confirm'              => 'true',
        'return_url'           => $this->shopUrl(),
        'metadata[session_id]' => $session['id'] ?? '',
      ], $secretKey);

      if (!empty($createResult['error'])) {
        return [
          'status'         => 'failed',
          'provider'       => 'stripe',
          'transaction_id' => null,
          'error'          => $createResult['error']['message'] ?? 'Stripe create intent failed.',
        ];
      }

      $intentId      = $createResult['id'] ?? null;
      $transactionId = $intentId;
    }

    // Confirm the intent
    if (!empty($intentId)) {
      $confirmResult = $this->stripeRequest('POST', '/v1/payment_intents/' . $intentId . '/confirm', [], $secretKey);

      $stripeStatus = $confirmResult['status'] ?? 'failed';

      return [
        'status'         => $stripeStatus === 'succeeded' ? 'succeeded' : 'failed',
        'provider'       => 'stripe',
        'transaction_id' => $intentId,
        'stripe_status'  => $stripeStatus,
        'error'          => $confirmResult['error']['message'] ?? null,
      ];
    }

    return [
      'status'         => 'failed',
      'provider'       => 'stripe',
      'transaction_id' => null,
      'error'          => 'No payment_intent_id or payment_method_id provided.',
    ];
  }

  /**
   * Adyen — stub implementation.
   * Replace with real Adyen Checkout API call when ready.
   */
  private function processAdyen(array $session, array $paymentData, ?string $transactionId): array
  {
    return [
      'status'         => 'succeeded',
      'provider'       => 'adyen',
      'transaction_id' => $transactionId ?? ('adyen_' . uniqid()),
      'mode'           => 'stub',
    ];
  }

  /**
   * Braintree — stub implementation.
   * Replace with real Braintree SDK call when ready.
   */
  private function processBraintree(array $session, array $paymentData, ?string $transactionId): array
  {
    return [
      'status'         => 'succeeded',
      'provider'       => 'braintree',
      'transaction_id' => $transactionId ?? ('bt_' . uniqid()),
      'mode'           => 'stub',
    ];
  }

  // =========================================================================
  // Delegated payment validation (ACP wallet flow)
  // =========================================================================

  private function validateDelegatedPaymentInput(array $input): array
  {
    if (empty($input['payment_method']) || !is_array($input['payment_method'])) {
      return $this->delegatedError(400, 'Missing payment_method.', '$.payment_method');
    }

    $pm = $input['payment_method'];

    if (($pm['type'] ?? '') !== 'card') {
      return $this->delegatedError(400, 'payment_method.type must be card.', '$.payment_method.type');
    }

    if (!in_array($pm['card_number_type'] ?? null, ['fpan', 'network_token'], true)) {
      return $this->delegatedError(400, 'card_number_type must be fpan or network_token.', '$.payment_method.card_number_type');
    }

    if (empty($pm['number']) || !preg_match('/^\d{12,19}$/', (string)$pm['number'])) {
      return $this->delegatedError(422, 'payment_method.number is invalid.', '$.payment_method.number');
    }

    if (!empty($pm['exp_month']) && strlen((string)$pm['exp_month']) > 2) {
      return $this->delegatedError(400, 'exp_month is invalid.', '$.payment_method.exp_month');
    }

    if (!empty($pm['exp_year']) && strlen((string)$pm['exp_year']) > 4) {
      return $this->delegatedError(400, 'exp_year is invalid.', '$.payment_method.exp_year');
    }

    if (!empty($pm['cvc']) && strlen((string)$pm['cvc']) > 4) {
      return $this->delegatedError(400, 'cvc is invalid.', '$.payment_method.cvc');
    }

    if (!in_array($pm['display_card_funding_type'] ?? null, ['credit', 'debit', 'prepaid'], true)) {
      return $this->delegatedError(400, 'display_card_funding_type is invalid.', '$.payment_method.display_card_funding_type');
    }

    // Allowance
    $allowance = $input['allowance'] ?? null;
    if (!is_array($allowance)) {
      return $this->delegatedError(400, 'allowance is required.', '$.allowance');
    }
    if (($allowance['reason'] ?? '') !== 'one_time') {
      return $this->delegatedError(400, 'allowance.reason must be one_time.', '$.allowance.reason');
    }
    if (!isset($allowance['max_amount']) || !is_numeric($allowance['max_amount']) || (int)$allowance['max_amount'] < 0) {
      return $this->delegatedError(400, 'allowance.max_amount must be a non-negative integer.', '$.allowance.max_amount');
    }
    if (empty($allowance['currency']) || !preg_match('/^[a-z]{3}$/', (string)$allowance['currency'])) {
      return $this->delegatedError(400, 'allowance.currency must be ISO-4217 lowercase.', '$.allowance.currency');
    }
    if (empty($allowance['checkout_session_id'])) {
      return $this->delegatedError(400, 'allowance.checkout_session_id is required.', '$.allowance.checkout_session_id');
    }
    if (empty($allowance['merchant_id']) || strlen((string)$allowance['merchant_id']) > 256) {
      return $this->delegatedError(400, 'allowance.merchant_id is invalid.', '$.allowance.merchant_id');
    }
    if (empty($allowance['expires_at']) || strtotime((string)$allowance['expires_at']) === false) {
      return $this->delegatedError(400, 'allowance.expires_at must be RFC3339.', '$.allowance.expires_at');
    }

    // Risk signals
    if (empty($input['risk_signals']) || !is_array($input['risk_signals'])) {
      return $this->delegatedError(400, 'risk_signals is required.', '$.risk_signals');
    }
    foreach ($input['risk_signals'] as $i => $signal) {
      $p = '$.risk_signals[' . $i . ']';
      if (empty($signal['type'])) {
        return $this->delegatedError(400, 'risk_signals.type is required.', $p . '.type');
      }
      if (!isset($signal['score']) || !is_numeric($signal['score'])) {
        return $this->delegatedError(400, 'risk_signals.score is required.', $p . '.score');
      }
      if (!in_array($signal['action'] ?? null, ['blocked', 'manual_review', 'authorized'], true)) {
        return $this->delegatedError(400, 'risk_signals.action is invalid.', $p . '.action');
      }
    }

    if (!isset($input['metadata']) || !is_array($input['metadata'])) {
      return $this->delegatedError(400, 'metadata is required.', '$.metadata');
    }

    // Optional billing address
    if (!empty($input['billing_address']) && is_array($input['billing_address'])) {
      $addrErrors = $this->validateAddress($input['billing_address'], '$.billing_address');
      if (!empty($addrErrors)) {
        return $this->delegatedError(400, $addrErrors[0]['message'], $addrErrors[0]['field']);
      }
    }

    return [];
  }

  // =========================================================================
  // Address validation (shared with Sessions)
  // =========================================================================

  public function validateAddress(array $address, string $path): array
  {
    $errors   = [];
    $required = ['name', 'line_one', 'city', 'country', 'postal_code'];

    foreach ($required as $field) {
      if (empty($address[$field])) {
        $errors[] = $this->error('missing', $path . '.' . $field, 'Address field is required.');
      }
    }

    $maxLen = [
      'name'        => 256,
      'line_one'    => 60,
      'line_two'    => 60,
      'city'        => 60,
      'state'       => 60,
      'country'     => 60,
      'postal_code' => 20,
    ];

    foreach ($maxLen as $field => $max) {
      if (!empty($address[$field]) && strlen((string)$address[$field]) > $max) {
        $errors[] = $this->error('invalid', $path . '.' . $field, 'Address field exceeds max length (' . $max . ').');
      }
    }

    if (!empty($address['country']) && !preg_match('/^[A-Z]{2,3}$/', (string)$address['country'])) {
      $errors[] = $this->error('invalid', $path . '.country', 'Country must be ISO 3166-1 alpha-2 or alpha-3.');
    }

    if (!empty($address['phone_number']) && !preg_match('/^\+[1-9]\d{1,14}$/', (string)$address['phone_number'])) {
      $errors[] = $this->error('invalid', $path . '.phone_number', 'Phone number must follow E.164 format.');
    }

    return $errors;
  }

  // =========================================================================
  // Stripe helpers
  // =========================================================================

  private function isStripeEnabled(): bool
  {
    if (\defined('CLICSHOPPING_APP_STRIPE_ST_STATUS') && CLICSHOPPING_APP_STRIPE_ST_STATUS === 'False') {
      return false;
    }
    return !empty($this->stripeSecretKey());
  }

  private function stripeSecretKey(): string
  {
    if (\defined('CLICSHOPPING_APP_STRIPE_ST_SERVER_PROD') && CLICSHOPPING_APP_STRIPE_ST_SERVER_PROD === 'True') {
      return \defined('CLICSHOPPING_APP_STRIPE_ST_PRIVATE_KEY') ? CLICSHOPPING_APP_STRIPE_ST_PRIVATE_KEY : '';
    }

    return \defined('CLICSHOPPING_APP_STRIPE_ST_PRIVATE_KEY_TEST') ? CLICSHOPPING_APP_STRIPE_ST_PRIVATE_KEY_TEST : '';
  }

  /**
   * Make a Stripe REST API request.
   */
  private function stripeRequest(string $method, string $path, array $params, string $secretKey): array
  {
    $url = 'https://api.stripe.com' . $path;

    $data = [
      'url' => $url,
      'method' => strtolower($method), // 'get' ou 'post'
      'parameters' => http_build_query($params), // Stripe attend du x-www-form-urlencoded
      'header' => [
        'Authorization: Basic ' . base64_encode($secretKey . ':'),
        'Content-Type: application/x-www-form-urlencoded'
      ],
      'timeout' => 30,
      'format' => 'json' // Automatiquement décodé par la classe
    ];

    $response = HTTP::getResponse($data);
    if ($response === false) {
      return ['error' => ['message' => 'Stripe API request failed (Guzzle).']];
    }

    return is_array($response) ? $response : ['error' => ['message' => 'Invalid Stripe response.']];
  }

  /**
   * Calculate the session total in minor currency units (cents).
   */
  private function sessionTotalMinor(array $session): int
  {
    $total = 0;
    foreach ($session['items'] ?? [] as $item) {
      $total += (int)round((float)($item['unit_price'] ?? 0) * (int)($item['quantity'] ?? 1) * 100);
    }
    return $total;
  }

  private function shopUrl(): string
  {
    return \defined('HTTP_SERVER') ? HTTP_SERVER : '';
  }

  // =========================================================================
  // Error helpers
  // =========================================================================

  private function error(string $code, string $field, string $message): array
  {
    return ['code' => $code, 'field' => $field, 'message' => $message];
  }

  private function delegatedError(int $status, string $message, string $param): array
  {
    return [
      'status' => $status,
      'body'   => [
        'type'    => 'invalid_request',
        'code'    => 'invalid_request',
        'message' => $message,
        'param'   => $param,
      ],
    ];
  }
}
