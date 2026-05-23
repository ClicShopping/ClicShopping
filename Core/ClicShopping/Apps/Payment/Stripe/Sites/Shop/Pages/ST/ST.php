<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Payment\Stripe\Sites\Shop\Pages\ST;

use ClicShopping\Apps\Payment\Stripe\Module\Payment\ST as PaymentStripeST;
use ClicShopping\OM\Registry;

/**
 * Stripe webhook handler for payment processing.
 * 
 * This class handles Stripe webhook events for payment processing,
 * including charge succeeded, payment intent succeeded, and payment method
 * attached events. It validates webhook signatures and processes events
 * according to Stripe's webhook specifications.
 * 
 * @package ClicShopping\Apps\Payment\Stripe\Sites\Shop\Pages\ST
 * @author ClicShopping Team
 * @copyright 2008 - https://www.clicshopping.org
 * @license GPL 2 & MIT
 */
class ST extends \ClicShopping\OM\Domains\PagesAbstract
{
  protected ?string $file = null;
  protected bool $use_site_template = false;
  protected $pm;
  private mixed $lang;

  /**
   * @return false|void
   */
  protected function init()
  {
    $this->lang = Registry::get('Language');

    $this->pm = new PaymentStripeST();

    if (!\defined('CLICSHOPPING_APP_STRIPE_ST_STATUS') || CLICSHOPPING_APP_STRIPE_ST_STATUS == 'False') {
      return false;
    }

    $this->lang->loadDefinitions('Shop/checkout_process');

    $endpoint_secret = CLICSHOPPING_APP_STRIPE_ST_KEY_WEBHOOK_ENDPOINT;
    $payload = @file_get_contents('php://input');
    
    $sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

    try {
      $event = \Stripe\Webhook::constructEvent(
        $payload,
        $sig_header,
        $endpoint_secret
      );
    } catch (\UnexpectedValueException $e) {
      error_log('[Stripe Webhook] Payload invalide: ' . $e->getMessage());
      http_response_code(400);
      exit();
    } catch (\Stripe\Exception\SignatureVerificationException $e) {
      error_log('[Stripe Webhook] Signature invalide: ' . $e->getMessage());
      http_response_code(400);
      exit();
    }

    // Handle the event
    switch ($event->type) {
      case 'payment_intent.succeeded':
        $this->handlePaymentIntentSucceeded($event);
        break;
      case 'payment_intent.payment_failed':
        $this->handlePaymentIntentFailed($event);
        break;
      case 'charge.succeeded':
        $this->handleChargeSucceeded($event);
        break;
      case 'charge.refunded':
        $this->handleChargeRefunded($event);
        break;
      default:
        // Événement inconnu, on ignore proprement
        break;
    }

    http_response_code(200);
  }

  /**
   * @param \Stripe\Event $event
   * @return void
   */
  private function handlePaymentIntentSucceeded(\Stripe\Event $event): void
  {
    $intent = $event->data->object;
    $intentId = $intent->id;
    $orderId = $intent->metadata['stripe_order_id'] ?? null;

    if (empty($orderId)) {
      error_log('[Stripe Webhook] Aucun ID commande dans les métadonnées pour l\'intent ' . $intentId);
      return;
    }

    $this->updateOrderStatus((int)$orderId, CLICSHOPPING_APP_STRIPE_ST_ORDER_STATUS_ID, 'Paiement réussi via Stripe. Intent ID: ' . $intentId);
  }

  /**
   * @param \Stripe\Event $event
   * @return void
   */
  private function handlePaymentIntentFailed(\Stripe\Event $event): void
  {
    $intent = $event->data->object;
    $intentId = $intent->id;
    $orderId = $intent->metadata['stripe_order_id'] ?? null;

    if (empty($orderId)) {
      error_log('[Stripe Webhook] Aucun ID commande pour l\'intent échoué ' . $intentId);
      return;
    }

    $errorMessage = $intent->last_payment_error ? $intent->last_payment_error->message : 'Paiement échoué';
    $this->updateOrderStatus((int)$orderId, DEFAULT_ORDERS_STATUS_ID, 'Paiement échoué via Stripe. Intent ID: ' . $intentId . ' - ' . $errorMessage);
  }

  /**
   * @param \Stripe\Event $event
   * @return void
   */
  private function handleChargeSucceeded(\Stripe\Event $event): void
  {
    $charge = $event->data->object;
    $intentId = $charge->payment_intent ?? null;
    $orderId = $charge->metadata['stripe_order_id'] ?? null;

    if (empty($orderId)) return;
    $this->updateOrderStatus((int)$orderId, CLICSHOPPING_APP_STRIPE_ST_ORDER_STATUS_ID, 'Charge réussi via Stripe. Intent ID: ' . ($intentId ?? 'N/A'));
  }

  /**
   * @param \Stripe\Event $event
   * @return void
   */
  private function handleChargeRefunded(\Stripe\Event $event): void
  {
    $charge = $event->data->object;
    $orderId = $charge->metadata['stripe_order_id'] ?? null;
    if (!empty($orderId)) {
      $this->updateOrderStatus((int)$orderId, DEFAULT_ORDERS_STATUS_ID, 'Remboursement traité via Stripe. Montant: ' . ($charge->amount / 100) . ' ' . $charge->currency);
    }
  }

  /**
   * @param int $orderId
   * @param int $statusId
   * @param string $comment
   * @return void
   */
  private function updateOrderStatus(int $orderId, int $statusId, string $comment): void
  {
    $db = Registry::get('Db');
    $Qorder = $db->prepare('SELECT orders_status_id FROM :table_orders WHERE orders_id = :orders_id');
    $Qorder->bindInt(':orders_id', $orderId);
    $Qorder->execute();
    
    if (!$Qorder->hasNext()) return;
    
    $currentStatus = $Qorder->valueInt('orders_status_id');
    // Ne pas rétrograder un statut déjà traité
    if ($currentStatus >= $statusId) return;

    $sql_data_array = [
      'orders_id' => $orderId,
      'orders_status_id' => $statusId,
      'date_added' => 'now()',
      'customer_notified' => '0',
      'comments' => $comment
    ];
    $db->save('orders_status_history', $sql_data_array);
    
    $sql_data_array = ['orders_status' => $statusId];
    $sql_insert = ['orders_id' => $orderId];
    $db->save('orders', $sql_data_array, $sql_insert);
    
    error_log('[Stripe Webhook] Commande ' . $orderId . ' mise à jour au statut ' . $statusId . ': ' . $comment);
  }
}
