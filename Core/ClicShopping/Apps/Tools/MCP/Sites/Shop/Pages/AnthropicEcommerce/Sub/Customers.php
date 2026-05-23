<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Tools\MCP\Sites\Shop\Pages\AnthropicEcommerce\Sub;

use ClicShopping\Apps\AI\Ecommerce\Classes\Shop\ACP\GptCustomerManager;
use ClicShopping\Apps\Tools\MCP\Classes\Shop\Security\Message;
use ClicShopping\OM\HTML;

/**
 * Customers sub-handler for the AnthropicEcommerce MCP endpoint.
 *
 * Delegates to GptCustomerManager (ACP) for all customer operations.
 *
 * Supported actions:
 *   - customer         GET   Customer detail by ?id=
 *   - customer_create  POST  Create a new customer account
 *   - addresses        GET   Saved addresses for ?customer_id=
 *   - countries        GET   All available countries (+ zones)
 */
class Customers
{
  private mixed              $db;
  private Message            $message;
  private GptCustomerManager $customerManager;

  public function __construct(mixed $db, Message $message)
  {
    $this->db              = $db;
    $this->message         = $message;
    $this->customerManager = new GptCustomerManager();
  }

  // =========================================================================
  // Dispatcher
  // =========================================================================

  public function dispatch(string $action): void
  {
    match ($action) {
      'customer'        => $this->getCustomer(),
      'customer_create' => $this->createCustomer(),
      'addresses'       => $this->getAddresses(),
      'countries'       => $this->getCountries(),
      default           => $this->message->sendError('Unknown customer action: ' . $action, 400),
    };
  }

  // =========================================================================
  // Actions
  // =========================================================================

  /**
   * Return customer detail.
   * GET params: id (required)
   */
  private function getCustomer(): void
  {
    $customerId = (int)($_GET['id'] ?? 0);
    if ($customerId <= 0) {
      $this->message->sendError('Missing or invalid customer id', 400);
      return;
    }

    try {
      $customer = $this->customerManager->getCustomerById($customerId);

      if ($customer === null) {
        $this->message->sendError('Customer not found: ' . $customerId, 404);
        return;
      }

      $this->message->sendSuccess(['customer' => $customer]);
    } catch (\Exception $e) {
      error_log('[AnthropicEcommerce][Customers] getCustomer: ' . $e->getMessage());
      $this->message->sendError('Failed to retrieve customer: ' . $e->getMessage(), 500);
    }
  }

  /**
   * Create a new customer account.
   * POST body:
   * {
   *   "firstname": "John",
   *   "lastname": "Doe",
   *   "email": "john@example.com",
   *   "telephone": "0600000000",
   *   "street_address": "12 Rue de la Paix",
   *   "city": "Paris",
   *   "postcode": "75001",
   *   "country_id": 73,
   *   "zone_id": 0
   * }
   */
  private function createCustomer(): void
  {
    $raw = file_get_contents('php://input');
    if (empty($raw)) {
      $this->message->sendError('Empty request body', 400);
      return;
    }

    $input = json_decode($raw, true);
    if (!is_array($input)) {
      $this->message->sendError('Invalid JSON body', 400);
      return;
    }

    try {
      $result = $this->customerManager->createCustomerAccount($input);
      $this->message->sendSuccess($result);
    } catch (\Exception $e) {
      error_log('[AnthropicEcommerce][Customers] createCustomer: ' . $e->getMessage());
      $this->message->sendError('Failed to create customer: ' . $e->getMessage(), 500);
    }
  }

  /**
   * Return saved addresses for a customer.
   * GET params: customer_id (required)
   */
  private function getAddresses(): void
  {
    $customerId = (int)($_GET['customer_id'] ?? 0);
    if ($customerId <= 0) {
      $this->message->sendError('Missing or invalid customer_id', 400);
      return;
    }

    try {
      $Qaddr = $this->db->prepare('
        SELECT ab.address_book_id,
               ab.entry_firstname,
               ab.entry_lastname,
               ab.entry_company,
               ab.entry_street_address,
               ab.entry_suburb,
               ab.entry_postcode,
               ab.entry_city,
               ab.entry_state,
               co.countries_name,
               co.countries_iso_code_2,
               z.zone_name
          FROM :table_address_book ab
     LEFT JOIN :table_countries co  ON co.countries_id = ab.entry_country_id
     LEFT JOIN :table_zones     z   ON z.zone_id       = ab.entry_zone_id
         WHERE ab.customers_id = :customer_id
      ORDER BY ab.address_book_id ASC
      ');
      $Qaddr->bindInt(':customer_id', $customerId);
      $Qaddr->execute();

      $addresses = [];
      while ($Qaddr->fetch()) {
        $addresses[] = [
          'id'             => $Qaddr->valueInt('address_book_id'),
          'firstname'      => $Qaddr->value('entry_firstname'),
          'lastname'       => $Qaddr->value('entry_lastname'),
          'company'        => $Qaddr->value('entry_company'),
          'street_address' => $Qaddr->value('entry_street_address'),
          'suburb'         => $Qaddr->value('entry_suburb'),
          'postcode'       => $Qaddr->value('entry_postcode'),
          'city'           => $Qaddr->value('entry_city'),
          'state'          => $Qaddr->value('entry_state') ?: $Qaddr->value('zone_name'),
          'country'        => $Qaddr->value('countries_name'),
          'country_iso2'   => $Qaddr->value('countries_iso_code_2'),
        ];
      }

      $this->message->sendSuccess([
        'customer_id' => $customerId,
        'addresses'   => $addresses,
        'count'       => count($addresses),
      ]);
    } catch (\Exception $e) {
      error_log('[AnthropicEcommerce][Customers] getAddresses: ' . $e->getMessage());
      $this->message->sendError('Failed to retrieve addresses: ' . $e->getMessage(), 500);
    }
  }

  /**
   * Return all available countries with optional zone list.
   * GET params: zones (bool, default false) — include zones per country
   */
  private function getCountries(): void
  {
    $includeZones = filter_var($_GET['zones'] ?? false, FILTER_VALIDATE_BOOLEAN);

    try {
      $countries = $this->customerManager->getAvailableCountries();

      if ($includeZones && !empty($countries)) {
        foreach ($countries as &$country) {
          $country['zones'] = $this->customerManager->getZonesForCountry((int)$country['countries_id']);
        }
        unset($country);
      }

      $this->message->sendSuccess([
        'countries' => $countries,
        'count'     => count($countries),
      ]);
    } catch (\Exception $e) {
      error_log('[AnthropicEcommerce][Customers] getCountries: ' . $e->getMessage());
      $this->message->sendError('Failed to retrieve countries: ' . $e->getMessage(), 500);
    }
  }
}
