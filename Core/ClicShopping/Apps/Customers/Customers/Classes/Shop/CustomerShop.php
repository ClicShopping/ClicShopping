<?php
/**
 *
 * @copyright 2008 - https://www.clicshopping.org
 * @Brand : ClicShoppingAI(TM) at Inpi all right Reserved
 * @Licence GPL 2 & MIT
 * @Info : https://www.clicshopping.org/forum/trademark/
 *
 */

namespace ClicShopping\Apps\Customers\Customers\Classes\Shop;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Hash;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;
use function is_null;

/**
 *
 * @copyright 2008 - https://www.clicshopping.org
 * @Brand : ClicShoppingAI(TM) at Inpi all right Reserved
 * @Licence GPL 2 & MIT
 * @Info : https://www.clicshopping.org/forum/trademark/
 *
 */
class CustomerShop
{
  protected bool $_is_logged_on = false;
  protected array $_data = [];
  protected array $_data1 = [];

  private mixed $db;

  /**
   * Initializes the customer object, sets customer data and group ID from the session,
   * verifies if the customer is logged on, and retrieves the database instance.
   *
   * @return void
   */
  public function __construct()
  {
    if (isset($_SESSION['customer'])) {
      $this->_data =& $_SESSION['customer'];
    }

    if (isset($_SESSION['customer_group_id'])) {
      $this->_data1 =& $_SESSION['customer_group_id'];
    }

    if (isset($this->_data['id']) && is_numeric($this->_data['id']) && ($this->_data['id'] > 0)) {
      $this->setIsLoggedOn(true);
    }

    $this->db = Registry::get('Db');
  }

  /**
   * Sets the logged-on state for the user.
   *
   * @param bool $state The desired logged-on state. Set to true if the user is logged on, otherwise false.
   * @return void
   */
  public function setIsLoggedOn(bool $state)
  {
    if ($state === true) {
      $this->_is_logged_on = true;
    } else {
      $this->_is_logged_on = false;
    }
  }

  /**
   * Determines if the user is currently logged in.
   *
   * @return bool Returns true if the user is logged in, otherwise false.
   */
  public function isLoggedOn(): bool
  {
    if ($this->_is_logged_on === true) {
      return true;
    }

    return false;
  }

  /**
   * Retrieves a specific value from the data array if a key is provided, or the entire data array if no key is specified.
   *
   * @param string|null $key The key to retrieve the value for, or null to retrieve the entire data array.
   * @return mixed Returns the value associated with the specified key, or the entire data array if no key is provided.
   */
  public function get(?string $key = null)
  {
    if (isset($key)) {
      return $this->_data[$key];
    } else {
      return $this->_data;
    }
  }

  /**
   * Retrieves the ID of the current entity.
   *
   * @return int Returns the ID if it exists and is numeric; otherwise, returns 0.
   */
  public function getID(): int
  {
    if (isset($this->_data['id']) && is_numeric($this->_data['id'])) {
      return (int)$this->_data['id'];
    }

    return 0;
  }

  /**
   * Retrieves the first name of the customer if it is set.
   *
   * @return string|bool Returns the first name as a string if available, otherwise false.
   */
  public function getFirstName(): string|bool
  {
    if (isset($this->_data['first_name'])) {
      return Hash::displayDecryptedDataText($this->_data['first_name']);
    }

    return false;
  }

  /**
   * Retrieves the last name of the customer.
   *
   * @return string|bool Returns the last name of the customer if set, otherwise false.
   */
  public function getLastName(): string|bool
  {
    if (isset($this->_data['last_name'])) {
      return Hash::displayDecryptedDataText($this->_data['last_name']);
    }

    return false;
  }

  /**
   * Retrieves the full name of the customer by combining first and last names if available.
   *
   * @return string Returns the full name of the customer. If neither first nor last name is set, returns an empty string.
   */
  public function getName(): string
  {
    $name = '';

    if (isset($this->_data['first_name'])) {
      $name .= Hash::displayDecryptedDataText($this->_data['first_name']);
    }

    if (isset($this->_data['last_name'])) {
      if (!empty($name)) {
        $name .= ' ';
      }

      $name .= Hash::displayDecryptedDataText($this->_data['last_name']);
    }

    return $name;
  }

  /**
   * Retrieves the gender of the customer if it is set.
   *
   * @return string|bool Returns the customer's gender as a string if set, otherwise false.
   */
  public function getGender(): string|bool
  {
    if (isset($this->_data['gender'])) {
      return $this->_data['gender'];
    }

    return false;
  }

  /**
   * Determines if the customer has an email address set.
   *
   * @return bool Returns true if the customer has an email address, otherwise false.
   */
  public function hasEmailAddress(): bool
  {
    return isset($this->_data['email_address']);
  }

  /**
   * Retrieves the email address of the customer if available.
   *
   * @return string|bool Returns the email address as a string if set, otherwise false.
   */
  public function getEmailAddress(): string|bool
  {
    if (isset($this->_data['email_address']) && !empty($this->_data['email_address'])) {
      return Hash::displayDecryptedEmail($this->_data['email_address']);
    }

    return false;
  }

  /**
   * Sets the telephone number for the customer.
   *
   * @param string|null $telephone The customer's telephone number. Can be null if no number is provided.
   * @return void
   */
  public function setTelephone(?string $telephone): void
  {
    $this->_data['customers_telephone'] = $telephone;
  }

  /**
   * Retrieves the customer's telephone number if available.
   *
   * @return string|bool Returns the telephone number as a string if set, otherwise false.
   */
  public function getTelephone(): string|bool
  {
    if (isset($this->_data['customers_telephone'])) {
      return Hash::displayDecryptedDataText($this->_data['customers_telephone']);
    }

    return false;
  }

  /**
   * Sets the cellular phone number for the customer.
   *
   * @param string|null $telephone The cellular phone number to set. Can be null to unset the phone number.
   * @return void
   */
  public function setCellularPhone(?string $telephone): void
  {
    $this->_data['customers_cellular_phone'] = $telephone;
  }

  /**
   * Retrieves the customer's cellular phone number if it is set.
   *
   * @return string|bool Returns the cellular phone number as a string if available, otherwise false.
   */
  public function getCellularPhone(): string|bool
  {
    if (isset($this->_data['customers_cellular_phone'])) {
      return Hash::displayDecryptedDataText($this->_data['customers_cellular_phone']);
    }

    return false;
  }

  /**
   * Retrieves the country ID associated with the customer.
   *
   * @return int|null Returns the country ID if available, otherwise null.
   */
  public function getCountryID():  int|null
  {
    static $country_id = null;

    if (is_null($country_id)) {
      if (isset($this->_data['country_id'])) {
        $country_id = $this->_data['country_id'];
      }
    }

    return $country_id;
  }

  /**
   * Retrieves the zone ID associated with the current data if available.
   *
   * @return int|null Returns the zone ID if set, otherwise null.
   */
  public function getZoneID():  int|null
  {
    static $zone_id = null;

    if (is_null($zone_id)) {
      if (isset($this->_data['zone_id'])) {
        $zone_id = $this->_data['zone_id'];
      }
    }

    return $zone_id;
  }

  /**
   * Retrieves the default address ID for the customer.
   *
   * @return int|null Returns the default address ID if set, otherwise null.
   */
  public function getDefaultAddressID():  int|null
  {
    static $id = null;

    if (is_null($id)) {
      if (isset($this->_data['default_address_id'])) {
        $id = $this->_data['default_address_id'];
      }
    }

    return $id;
  }

  /**
   * Retrieves the group ID of the customer.
   *
   * @return int Returns the customer's group ID. If no group ID is found, it returns 0.
   */
  public function getCustomersGroupID(): int
  {
    $customersGroupId = 0;

    if (isset($this->_data1['customers_group_id'])) {
      $customersGroupId = $this->_data1['customers_group_id'];
    }

    return $customersGroupId;
  }

  /**
   * Sets the customer data by fetching information from the database for the provided customer ID.
   *
   * @param int $id The unique identifier of the customer to retrieve data for. Must be a positive numeric value.
   * @return bool Returns true if data is successfully fetched and set, otherwise false.
   */
  public function setData(int $id): bool
  {
    $this->_data = [];

    if (is_numeric($id) && ($id > 0)) {
      $Qcustomer = $this->db->prepare('select customers_gender,
                                                 customers_firstname,
                                                 customers_lastname,
                                                 customers_email_address,
                                                 customers_telephone,
                                                 customers_default_address_id
                                          from :table_customers
                                          where customers_id = :customers_id
                                        ');
      $Qcustomer->bindInt(':customers_id', $id);
      $Qcustomer->execute();

// B2B
      $QcustomerGroup = $this->db->prepare('select customers_group_id
                                               from :table_customers
                                               where customers_id = :customers_id
                                              ');
      $QcustomerGroup->bindInt(':customers_id', $id);
      $QcustomerGroup->execute();

      $customer_group_data = $QcustomerGroup->fetch();
      
      if ($customer_group_data !== false) {
        $this->setCustomersGroupID($customer_group_data['customers_group_id']);
        $_SESSION['customer_group_id'] = $this->_data1;
      }

      if ($Qcustomer->fetch() !== false) {
        $this->setIsLoggedOn(true);
        $this->setID($id);
        $this->setGender($Qcustomer->value('customers_gender'));
        $this->setFirstName(Hash::displayDecryptedDataText($Qcustomer->value('customers_firstname')));
        $this->setLastName(Hash::displayDecryptedDataText($Qcustomer->value('customers_lastname')));
        $this->setEmailAddress($Qcustomer->value('customers_email_address'));
        $this->setTelephone(Hash::displayDecryptedDataText($Qcustomer->value('customers_telephone')));

        if ($Qcustomer->valueInt('customers_default_address_id') > 0) {
          $Qab = $this->db->prepare('select entry_country_id,
                                               entry_zone_id
                                        from :table_address_book
                                        where address_book_id = :address_book_id
                                        and customers_id = :customers_id
                                      ');
          $Qab->bindInt(':address_book_id', $Qcustomer->valueInt('customers_default_address_id'));
          $Qab->bindInt(':customers_id', $id);
          $Qab->execute();

          if ($Qab->fetch() !== false) {
            $this->setCountryID($Qab->valueInt('entry_country_id'));
            $this->setZoneID($Qab->valueInt('entry_zone_id'));
            $this->setDefaultAddressID($Qcustomer->valueInt('customers_default_address_id'));
          }
        }

        $_SESSION['customer'] = $this->_data;
      }
    }

    return !empty($this->_data);
  }

  /**
   * Sets the ID for the current instance if the provided value is a positive integer.
   *
   * @param int $id The ID value to set.
   * @return void
   */
  public function setID(int $id): void
  {
    if (is_numeric($id) && ($id > 0)) {
      $this->_data['id'] = $id;
    } else {
      $this->_data['id'] = false;
    }
  }

  /**
   * Sets the default address ID for the customer. Updates the database if the provided ID differs
   * from the current default address ID. If the given ID is not valid, the default address ID is set to false.
   *
   * @param int $id The new default address ID to be set.
   * @return void
   */
  public function setDefaultAddressID(int $id): void
  {
    if (is_numeric($id) && ($id > 0)) {
      if (!isset($this->_data['default_address_id']) || ($this->_data['default_address_id'] != $id)) {
        $update_sql = [
          'customers_default_address_id' => $id
        ];

        $this->db->save('customers', $update_sql, ['customers_id' => $this->getID()]);
      }

      $this->_data['default_address_id'] = $id;
    } else {
      $this->_data['default_address_id'] = false;
    }
  }

  /**
   * Determines if the customer has a default address set.
   *
   * @return bool Returns true if the customer has a default address, otherwise false.
   */
  public function hasDefaultAddress(): bool
  {
    if (isset($this->_data['default_address_id']) && is_numeric($this->_data['default_address_id'])) {
      return true;
    }

    return false;
  }

  /**
   * Sets the gender of the user.
   *
   * @param string|null $gender The gender to set, expected to be 'm' or 'f' (case insensitive).
   * @return void
   */
  public function setGender(?string $gender): void
  {
    if ((mb_strtolower($gender) == 'm') || (mb_strtolower($gender) == 'f')) {
      $this->_data['gender'] = mb_strtolower($gender);
    } else {
      $this->_data['gender'] = false;
    }
  }

  /**
   * Sets the first name for the current instance.
   *
   * @param string|null $first_name The first name to be set. Can be null.
   * @return void
   */
  public function setFirstName(?string $first_name): void
  {
    $this->_data['first_name'] = Hash::displayDecryptedDataText($first_name);
  }

  /**
   * Sets the last name for the current object.
   *
   * @param string|null $last_name The last name to be set. Can be null.
   * @return void
   */
  public function setLastName(?string $last_name): void
  {
    $this->_data['last_name'] = Hash::displayDecryptedDataText($last_name);
  }

  /**
   * Sets the email address for the customer.
   *
   * @param string $email_address The email address to be set.
   * @return void
   */
  public function setEmailAddress(string $email_address): void
  {
    $this->_data['email_address'] = $email_address;
  }

  /**
   * Retrieves the guest account status of a customer based on their ID.
   *
   * @param int $id The unique identifier of the customer.
   * @return int|null The guest account status of the customer or null if not found.
   */
  public function getCustomerGuestAccount(int $id):  int|null
  {
    $Qresult = $this->db->get('customers', 'customer_guest_account', ['customers_id' => (int)$id]);

    return $Qresult->valueInt('customer_guest_account');
  }

  /**
   * Retrieves the IP address of the customer based on their ID.
   *
   * @return string The IP address of the customer.
   */
  public function getCustomerIp(): string
  {
    $Qresult = $this->db->get('customers', 'client_computer_ip', ['customers_id' => $this->getID()], null, 1);

    return $Qresult->value('client_computer_ip');
  }

  /**
   * Sets the country ID for the current object.
   *
   * @param int $id The ID of the country to be set.
   * @return void
   */
  public function setCountryID(int $id): void
  {
    $this->_data['country_id'] = $id;
  }

  /**
   * Sets the zone ID for the current instance.
   *
   * @param int $id The zone ID to be set.
   * @return void
   */
  public function setZoneID(int $id): void
  {
    $this->_data['zone_id'] = $id;
  }

  /**
   * Sets the customer group ID.
   *
   * @param int $id The ID of the customer group to set.
   * @return void
   */
  public function setCustomersgroupID(int $id): void
  {
    $this->_data1['customers_group_id'] = $id;
  }

  /**
   * Resets the customer's session and internal data to the default state.
   *
   * @return void
   */
  public function reset(): void
  {
    $this->_is_logged_on = false;
    $this->_data = [];

    if (isset($_SESSION['customer'])) {
      unset($_SESSION['customer']);
    }

// B2B
    if (isset($_SESSION['customer_group_id'])) {
      unset($_SESSION['customer_group_id']);
    }
  }


  /**
   * Generates a personalized or generic greeting message for the customer based on their login status and account type.
   *
   * @return string Returns the generated greeting message depending on whether the customer is logged in or not,
   *                and the type of account management mode (e.g., B2C, B2B, or both).
   */
  public function customerGreeting(): string
  {
    if ($this->isLoggedOn()) {
      $text_array = [
        'first_name' => HTML::outputProtected($this->getFirstName()),
        'url_products_new' => CLICSHOPPING::link(null, 'Products&ProductsNew'),
        'url_logoff' => CLICSHOPPING::link(null, 'Account&LogOff')
      ];

      $greeting_string = CLICSHOPPING::getDef('text_greeting_personal', $text_array);
    } else {
      if (\defined('MODE_MANAGEMENT_B2C_B2B') && (MODE_MANAGEMENT_B2C_B2B == 'B2C_B2B' || MODE_MANAGEMENT_B2C_B2B == 'B2B')) {
        $text_array = [
          'url_login' => CLICSHOPPING::redirect(null, 'Account&LogIn'),
          'url_create_account' => CLICSHOPPING::link(null, 'Account&Create'),
          'url_create_account_pro' => CLICSHOPPING::link('Account.php', 'Account&CreatePro')
        ];

        $greeting_string = CLICSHOPPING::getDef('text_greeting_guest', $text_array);
      } else {
        $text_array = [
          'url_login' => CLICSHOPPING::redirect(null, 'Account&LogIn'),
          'url_products_new' => CLICSHOPPING::link(null, 'Products&ProductsNew')
        ];

        $greeting_string = CLICSHOPPING::getDef('text_greeting_guest', $text_array);
      }
    }

    return $greeting_string;
  }

  /**
   * Checks if the customer has any product notifications enabled.
   *
   * @return bool True if the customer has product notifications, false otherwise.
   */
  public function hasProductNotifications(): bool
  {
    $Qcheck = $this->db->prepare('select products_id
                                    from :table_products_notifications
                                    where customers_id = :customers_id
                                    limit 1
                                    ');
    $Qcheck->bindInt(':customers_id', $this->_data['id']);
    $Qcheck->execute();

    return ($Qcheck->fetch() !== false);
  }

  /**
   * Retrieves the product notifications for the current customer, including product IDs and names,
   * filtered by the current language.
   *
   * @return mixed Returns the database result set containing product notifications data for the customer.
   */
  public function getProductNotifications(): mixed
  {
    $CLICSHOPPING_Language = Registry::get('Language');

    $Qproducts = $this->db->prepare('select pd.products_id,
                                             pd.products_name
                                      from :table_products_description pd,
                                           :table_products_notifications pn
                                      where pn.customers_id = :customers_id
                                        and pn.products_id = pd.products_id
                                        and pd.language_id = :language_id
                                      order by pd.products_name
                                      ');
    $Qproducts->bindInt(':customers_id', $this->_data['id']);
    $Qproducts->bindInt(':language_id', $CLICSHOPPING_Language->getID());
    $Qproducts->execute();

    return $Qproducts;
  }
}