<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\AI\Ecommerce\Classes\Shop\UCP\Sub;

use ClicShopping\OM\SimpleLogger;
use ClicShopping\OM\Registry;

class PaymentProcessor
{
  protected SimpleLogger $logger;

  public function __construct()
  {
    if (!Registry::exists('SimpleLogger')) {
      $this->logger = new SimpleLogger('UCP_Payment');
    } else {
      $this->logger = Registry::get('SimpleLogger');
    }
  }

  /**
   * Validate payment data payload.
   *
   * @param array $paymentData
   * @return array
   */
  public function validatePaymentData(array $paymentData): array
  {
    $errors = [];

    if (empty($paymentData['provider'])) {
      $errors[] = ['code' => 'missing', 'field' => '$.payment_data.provider', 'message' => 'Payment provider is required.'];
    }

    if (empty($paymentData['payment_method_id']) && empty($paymentData['payment_intent_id']) && empty($paymentData['token'])) {
      $errors[] = ['code' => 'missing', 'field' => '$.payment_data.payment_method_id', 'message' => 'Payment method id or intent id is required.'];
    }

    return $errors;
  }

  /**
   * Process payment (stubbed for now).
   *
   * @param array $session
   * @param array $paymentData
   * @return array
   */
  public function processPayment(array $session, array $paymentData): array
  {
    $provider = strtolower((string)($paymentData['provider'] ?? ''));
    $transactionId = $paymentData['payment_intent_id'] ?? $paymentData['token'] ?? $paymentData['payment_method_id'] ?? null;

    if ($provider === 'stripe') {
      return [
        'status' => 'succeeded',
        'provider' => 'stripe',
        'transaction_id' => $transactionId
      ];
    }

    return [
      'status' => 'failed',
      'provider' => $provider,
      'transaction_id' => $transactionId
    ];
  }

  /**
   * Handle webhook payload.
   *
   * @param array $payload
   * @return array
   */
  public function handleWebhook(array $payload): array
  {
    $eventType = $payload['type'] ?? '';

    return [
      'type' => $eventType,
      'payload' => $payload
    ];
  }
}
