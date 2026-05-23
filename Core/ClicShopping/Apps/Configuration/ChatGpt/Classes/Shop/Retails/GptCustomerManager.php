<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\ChatGpt\Classes\Shop\Retails;

use AllowDynamicProperties;
use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Hash;
use ClicShopping\OM\HTTP;
use ClicShopping\OM\Is;
use ClicShopping\OM\Registry;
use ClicShopping\Apps\Configuration\TemplateEmail\Classes\Shop\TemplateEmail;
use ClicShopping\OM\SimpleLogger;

/**
 * Class GptCustomerManager
 *
 * Manages the creation, validation, and database persistence of customer accounts
 * originating from the OpenAI Retailers Agent Controlled Purchase (ACP) checkout sessions.
 * This includes inserting records into the 'customers', 'address_book', and 'customers_info'
 * tables, along with handling temporary passwords and sending welcome emails.
 */
#[AllowDynamicProperties]
class GptCustomerManager
{
  /**
   * @var object The database connection object (from Registry).
   */
  protected object $db;

  /**
   * @var SimpleLogger The simple logger instance for debugging and error logging.
   */
  protected SimpleLogger $logger;

  /**
   * @var object The language object (from Registry).
   */
  protected object $lang;

  /**
   * @var object The mailer object (from Registry).
   */
  protected object $mail;

  /**
   * GptCustomerManager constructor.
   *
   * Initializes database, language, and mail objects, and sets up the logger.
   */
  public function __construct()
  {
    $this->db = Registry::get('Db');
    $this->lang = Registry::get('Language');
    $this->mail = Registry::get('Mail');
    if (!Registry::exists('SimpleLogger')) {
      $this->logger = new SimpleLogger();
    }
}

  /**
   * Creates a customer account from GPT session data.
   *
   * This is the main public method. It validates data, checks for existing accounts,
   * generates a password, inserts all necessary database records, and initiates
   * the welcome email process.
   *
   * @param array $customerData Customer information received from the GPT checkout session.
   * @return array Result with customer ID or error message.
   */
  public function createCustomerAccount(array $customerData): array
  {
    try {
      // Validate required fields
      $validation = $this->validateCustomerData($customerData);
      if (!$validation['valid']) {
        return [
          'status' => 'error',
          'message' => $validation['message'],
          'customer_id' => null
        ];
      }

      // Check if email already exists
      if ($this->emailExists($customerData['email_address'])) {
        return [
          'status' => 'error',
          'message' => 'Email address already exists',
          'customer_id' => null
        ];
      }

      // Generate temporary password
      $tempPassword = $this->generateTemporaryPassword();

      // Create customer account
      $customerId = $this->insertCustomer($customerData, $tempPassword);

      if (!$customerId) {
        return [
          'status' => 'error',
          'message' => 'Failed to create customer account',
          'customer_id' => null
        ];
      }

      // Create address book entry
      $this->createAddressBookEntry($customerId, $customerData);

      // Create customer info entry
      $this->createCustomerInfoEntry($customerId);

      // Send welcome email with password setup link
      $this->sendWelcomeEmail($customerData, $customerId, $tempPassword);

      $this->logger->info('Customer account created successfully', [
        'event' => 'gpt_customer_created',
        'customer_id' => $customerId,
        'email' => $customerData['email_address']
      ]);

      return [
        'status' => 'success',
        'message' => 'Customer account created successfully',
        'customer_id' => $customerId,
        'temp_password' => $tempPassword
      ];

    } catch (\Exception $e) {
      $this->logger->error('Failed to create customer account', [
        'event' => 'gpt_customer_error',
        'error' => $e->getMessage(),
        'email' => $customerData['email_address'] ?? 'unknown'
      ]);

      return [
        'status' => 'error',
        'message' => $e->getMessage(),
        'customer_id' => null
      ];
    }
}

  /**
   * Validates customer data for required fields and email format.
   *
   * @param array $customerData The array of customer details.
   * @return array An array with a boolean 'valid' status and a 'message'.
   */
  private function validateCustomerData(array $customerData): array
  {
    $requiredFields = [
      'firstname' => 'First name',
      'lastname' => 'Last name',
      'street_address' => 'Street address',
      'city' => 'City',
      'postcode' => 'Postal code',
      'country' => 'Country',
      'telephone' => 'Telephone',
      'email_address' => 'Email address'
    ];

    foreach ($requiredFields as $field => $label) {
      if (empty($customerData[$field])) {
        return [
          'valid' => false,
          'message' => "Required field missing: $label"
        ];
      }
}

    // Validate email format
    if (!Is::EmailAddress($customerData['email_address'])) {
      return [
        'valid' => false,
        'message' => 'Invalid email address format'
      ];
    }

    return ['valid' => true];
  }

  /**
   * Checks if a customer account with the given email address already exists.
   *
   * @param string $email The email address to check.
   * @return bool True if the email exists, false otherwise.
   */
  public function emailExists(string $email): bool
  {
    $Qcheckemail = $this->db->prepare('
      SELECT customers_id 
      FROM :table_customers 
      WHERE customers_email_address = :customers_email_address
    ');
    $Qcheckemail->bindValue(':customers_email_address', $email);
    $Qcheckemail->execute();

    return $Qcheckemail->fetch() !== false;
  }

  /**
   * Generates a simple, temporary 8-character password.
   *
   * @return string The temporary password.
   */
  private function generateTemporaryPassword(): string
  {
    return bin2hex(random_bytes(4));
  }

  /**
   * Inserts the primary customer record into the :table_customers table.
   *
   * Encrypts sensitive data (first name, last name, phone numbers) and hashes the password.
   *
   * @param array $customerData The customer details.
   * @param string $tempPassword The generated temporary password.
   * @return int|false The newly inserted customer ID on success, or false on failure.
   */
  private function insertCustomer(array $customerData, string $tempPassword)
  {
    $sql_data_array = [
      'customers_firstname' => Hash::encryptDatatext($customerData['firstname']),
      'customers_lastname' => Hash::encryptDatatext($customerData['lastname']),
      'customers_email_address' => $customerData['email_address'],
      'customers_newsletter' => 0,
      'languages_id' => (int)$this->lang->getId(),
      'customers_password' => Hash::encrypt($tempPassword),
      'customers_telephone' => Hash::encryptDatatext($customerData['telephone']),
      'customers_cellular_phone' => Hash::encryptDatatext($customerData['cellular_phone'] ?? ''),
      'member_level' => 1,
      'client_computer_ip' => HTTP::getIPAddress(),
      'provider_name_client' => HTTP::getProviderNameCustomer(),
    ];

    $this->db->save('customers', $sql_data_array);
    return $this->db->lastInsertId();
  }

  /**
   * Creates the default address book entry for the new customer.
   *
   * Retrieves the country ID via `getCountryIdFromAddress()` and sets the new address
   * as the customer's default address. Encrypts address details.
   *
   * @param int $customerId The ID of the newly created customer.
   * @param array $customerData The customer and address details.
   */
  private function createAddressBookEntry(int $customerId, array $customerData): void
  {
    // Get dynamic country ID from OpenAI address data
    $countryId = $this->getCountryIdFromAddress($customerData);

    $sql_data_array_book = [
      'customers_id' => (int)$customerId,
      'entry_firstname' => Hash::encryptDatatext($customerData['firstname']),
      'entry_lastname' => Hash::encryptDatatext($customerData['lastname']),
      'entry_company' => Hash::encryptDatatext($customerData['company'] ?? ''),
      'entry_street_address' => Hash::encryptDatatext($customerData['street_address']),
      'entry_suburb' => Hash::encryptDatatext($customerData['suburb'] ?? ''),
      'entry_city' => Hash::encryptDatatext($customerData['city']),
      'entry_postcode' => Hash::encryptDatatext($customerData['postcode']),
      'entry_state' => Hash::encryptDatatext($customerData['state'] ?? ''),
      'entry_country_id' => $countryId,
      'entry_telephone' => Hash::encryptDatatext($customerData['telephone'])
    ];

    $this->db->save('address_book', $sql_data_array_book);
    $addressId = $this->db->lastInsertId();

    // Set as default address
    $sql_data_array = ['customers_default_address_id' => (int)$addressId];
    $insert_array = ['customers_id' => (int)$customerId];
    $this->db->save('customers', $sql_data_array, $insert_array);
  }

  /**
   * Creates the customer info entry in the :table_customers_info table.
   *
   * Initializes logons to 0 and sets the account creation date.
   *
   * @param int $customerId The ID of the new customer.
   */
  private function createCustomerInfoEntry(int $customerId): void
  {
    $sql_array = [
      'customers_info_id' => (int)$customerId,
      'customers_info_number_of_logons' => 0,
      'customers_info_date_account_created' => 'now()'
    ];

    $this->db->save('customers_info', $sql_array);
  }

  /**
   * Sends a welcome email to the new customer, including a secure password setup link.
   *
   * @param array $customerData The customer details for email personalization.
   * @param int $customerId The ID of the new customer.
   * @param string $tempPassword The generated temporary password (used in the token).
   */
  private function sendWelcomeEmail(array $customerData, int $customerId, string $tempPassword): void
  {
    try {
      $name = $customerData['firstname'] . ' ' . $customerData['lastname'];

      // Generate password setup link
      $passwordSetupLink = $this->generatePasswordSetupLink($customerId, $tempPassword);

      // Get email templates (welcome message, signature, footer)
      $template_email_welcome_catalog = TemplateEmail::getTemplateEmailWelcomeCatalog();
      $template_email_signature = TemplateEmail::getTemplateEmailSignature();
      $template_email_footer = TemplateEmail::getTemplateEmailTextFooter();

      // Build email content (subject and body)
      $email_subject = CLICSHOPPING::getDef('gpt_welcome_email_subject', ['store_name' => STORE_NAME]);
      if (empty($email_subject)) {
        $email_subject = 'Bienvenue sur ' . (defined('STORE_NAME') ? STORE_NAME : 'notre boutique');
      }
      $email_gender = CLICSHOPPING::getDef('email_greet_ms', ['last_name' => $customerData['lastname']]) . ', ' . CLICSHOPPING::getDef('email_greet_mr', ['last_name' => $customerData['lastname']]) . ' ' . $customerData['lastname'];

      $password_setup_text = CLICSHOPPING::getDef('gpt_password_setup_text', [
        'password_setup_link' => $passwordSetupLink,
        'temp_password' => $tempPassword
      ]);

      $email_text = $email_gender . ',<br /><br />' .
        $template_email_welcome_catalog . '<br /><br />' .
        $password_setup_text . '<br /><br />' .
        $template_email_signature . '<br /><br />' .
        $template_email_footer;

      // Send email
      $message = str_replace('src="/', 'src="' . HTTP::typeUrlDomain() . '/', $email_text);
      $this->mail->addHtmlCkeditor($message);

      $from = defined('STORE_OWNER_EMAIL_ADDRESS') ? STORE_OWNER_EMAIL_ADDRESS : 'noreply@clicshopping.org';
      $this->mail->send($customerData['email_address'], $name, null, $from, $email_subject);

      $this->logger->info('Welcome email sent successfully', [
        'event' => 'gpt_welcome_email_sent',
        'customer_id' => $customerId,
        'email' => $customerData['email_address']
      ]);

    } catch (\Exception $e) {
      $this->logger->error('Failed to send welcome email', [
        'event' => 'gpt_email_error',
        'customer_id' => $customerId,
        'error' => $e->getMessage()
      ]);
    }
}

  /**
   * Generates a tokenized URL for one-time password setup.
   *
   * The token is Base64-encoded and contains the customer ID, the temporary password, and a timestamp.
   *
   * @param int $customerId The ID of the customer.
   * @param string $tempPassword The temporary password.
   * @return string The full password setup URL.
   */
  private function generatePasswordSetupLink(int $customerId, string $tempPassword): string
  {
    $token = base64_encode($customerId . '|' . $tempPassword . '|' . time());
    return HTTP::typeUrlDomain() . '/clicshopping_test/index.php?Account&Password&token=' . $token;
  }

  /**
   * Retrieves customer and customer info data by ID.
   *
   * @param int $customerId The ID of the customer.
   * @return array|null The customer data array, or null if not found.
   */
  public function getCustomerById(int $customerId): ?array
  {
    $Qcustomer = $this->db->prepare(' SELECT c.*, 
                                        ci.* FROM :table_customers c
                                      LEFT JOIN :table_customers_info ci ON c.customers_id = ci.customers_info_id
                                      WHERE c.customers_id = :customers_id
                                    ');
    $Qcustomer->bindInt(':customers_id', $customerId);
    $Qcustomer->execute();

    if ($Qcustomer->fetch()) {
      return [
        'customers_id' => $Qcustomer->valueInt('customers_id'),
        'customers_firstname' => $Qcustomer->value('customers_firstname'),
        'customers_lastname' => $Qcustomer->value('customers_lastname'),
        'customers_email_address' => $Qcustomer->value('customers_email_address'),
        'customers_telephone' => $Qcustomer->value('customers_telephone'),
        'customers_cellular_phone' => $Qcustomer->value('customers_cellular_phone'),
        'customers_info_date_account_created' => $Qcustomer->value('customers_info_date_account_created')
      ];
    }

    return null;
  }

  /**
   * Updates a customer's password in the database.
   *
   * Hashes the new password before saving.
   *
   * @param int $customerId The ID of the customer.
   * @param string $newPassword The new password (plain text).
   * @return bool True on success, false on failure.
   */
  public function updateCustomerPassword(int $customerId, string $newPassword): bool
  {
    try {
      $sql_data_array = [
        'customers_password' => Hash::encrypt($newPassword)
      ];
      $insert_array = ['customers_id' => (int)$customerId];

      $this->db->save('customers', $sql_data_array, $insert_array);

      $this->logger->info('Customer password updated', [
        'event' => 'gpt_password_updated',
        'customer_id' => $customerId
      ]);

      return true;
    } catch (\Exception $e) {
      $this->logger->error('Failed to update password', [
        'event' => 'gpt_password_error',
        'customer_id' => $customerId,
        'error' => $e->getMessage()
      ]);
      return false;
    }
}

  /**
   * Gets the internal country ID from the external OpenAI address data.
   *
   * It attempts resolution using:
   * 1. ISO 2-letter code (`addressData['country']`).
   * 2. Country name (`addressData['country_name']`).
   * 3. Falls back to a hardcoded default (France ID 73).
   *
   * @param array $addressData Address data from OpenAI checkout.
   * @return int The validated internal ClicShopping Country ID.
   */
  public function getCountryIdFromAddress(array $addressData): int
  {
    try {
      // First try to get country from ISO 2-letter code (OpenAI standard)
      if (!empty($addressData['country'])) {
        $countryCode = strtoupper($addressData['country']);

        // Validate country code format (ISO 3166-1 alpha-2)
        if (strlen($countryCode) === 2) {
          $Qcountry = $this->db->prepare('
            SELECT countries_id 
            FROM :table_countries 
            WHERE countries_iso_code_2 = :country_code 
            AND status = 1
          ');
          $Qcountry->bindValue(':country_code', $countryCode);
          $Qcountry->execute();

          if ($Qcountry->fetch()) {
            $countryId = $Qcountry->valueInt('countries_id');

            // Validate zone if provided
            if (!empty($addressData['state'])) {
              $this->validateZoneForCountry($countryId, $addressData['state']);
            }

            $this->logger->info('Country ID resolved from OpenAI address', [
              'country_code' => $countryCode,
              'country_id' => $countryId,
              'state' => $addressData['state'] ?? null
            ]);

            return $countryId;
          }
}
      }

      // Try country name fallback
      if (!empty($addressData['country_name'])) {
        $Qcountry = $this->db->prepare('
          SELECT countries_id 
          FROM :table_countries 
          WHERE countries_name = :country_name 
          AND status = 1
        ');
        $Qcountry->bindValue(':country_name', $addressData['country_name']);
        $Qcountry->execute();

        if ($Qcountry->fetch()) {
          return $Qcountry->valueInt('countries_id');
        }
}

      // Default fallback to France (ID 73) as per business requirements
      $this->logger->warning('Country not found, using default France', [
        'provided_country' => $addressData['country'] ?? 'not_provided',
        'provided_country_name' => $addressData['country_name'] ?? 'not_provided'
      ]);

      return 73; // France ID from database

    } catch (\Exception $e) {
      $this->logger->error('Error resolving country ID', [
        'error' => $e->getMessage(),
        'address_data' => $addressData
      ]);

      return 73; // France fallback
    }
}

  /**
   * Validates if a provided state/zone code exists for the given country.
   *
   * Checks against the :table_zones table using either the zone code or zone name.
   *
   * @param int $countryId The internal country ID.
   * @param string $stateCode The state/zone code or name provided in the address data.
   * @return bool True if the zone is valid/found, false otherwise.
   */
  public function validateZoneForCountry(int $countryId, string $stateCode): bool
  {
    try {
      $Qzone = $this->db->prepare('
        SELECT zone_id 
        FROM :table_zones 
        WHERE zone_country_id = :country_id 
        AND (zone_code = :state_code OR zone_name = :state_code)
        AND zone_status = 1
      ');
      $Qzone->bindInt(':country_id', $countryId);
      $Qzone->bindValue(':state_code', $stateCode);
      $Qzone->execute();

      $isValid = $Qzone->fetch() !== false;

      if (!$isValid) {
        $this->logger->warning('Zone validation failed', [
          'country_id' => $countryId,
          'state_code' => $stateCode
        ]);
      }

      return $isValid;

    } catch (\Exception $e) {
      $this->logger->error('Zone validation error', [
        'error' => $e->getMessage(),
        'country_id' => $countryId,
        'state_code' => $stateCode
      ]);

      return false;
    }
}

  /**
   * Gets a list of all active countries from the database.
   *
   * @return array An array of active countries (ID, name, ISO codes).
   */
  public function getAvailableCountries(): array
  {
    try {
      $Qcountries = $this->db->prepare('
        SELECT countries_id, countries_name, countries_iso_code_2, countries_iso_code_3
        FROM :table_countries 
        WHERE status = 1
        ORDER BY countries_name
      ');
      $Qcountries->execute();

      return $Qcountries->fetchAll();

    } catch (\Exception $e) {
      $this->logger->error('Error fetching countries', [
        'error' => $e->getMessage()
      ]);

      return [];
    }
}

  /**
   * Gets a list of all active zones (states/provinces) for a specific country.
   *
   * @param int $countryId The ID of the country.
   * @return array An array of active zones (ID, code, name).
   */
  public function getZonesForCountry(int $countryId): array
  {
    try {
      $Qzones = $this->db->prepare('
        SELECT zone_id, zone_code, zone_name
        FROM :table_zones 
        WHERE zone_country_id = :country_id
        AND zone_status = 1
        ORDER BY zone_name
      ');
      $Qzones->bindInt(':country_id', $countryId);
      $Qzones->execute();

      return $Qzones->fetchAll();

    } catch (\Exception $e) {
      $this->logger->error('Error fetching zones', [
        'error' => $e->getMessage(),
        'country_id' => $countryId
      ]);

      return [];
    }
}
}