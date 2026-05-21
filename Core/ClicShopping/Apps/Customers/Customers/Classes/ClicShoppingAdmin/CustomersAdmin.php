<?php
/**
 *
 * @copyright 2008 - https://www.clicshopping.org
 * @Brand : ClicShoppingAI(TM) at Inpi all right Reserved
 * @Licence GPL 2 & MIT
 * @Info : https://www.clicshopping.org/forum/trademark/
 *
 */

namespace ClicShopping\Apps\Customers\Customers\Classes\ClicShoppingAdmin;

use ClicShopping\OM\Hash;
use ClicShopping\OM\Registry;
/**
 * Class CustomersAdmin
 *
 * Provides methods to manage customer data in the administration module.
 */
class CustomersAdmin
{
  private mixed $db;
  public function __construct()
  {
    $this->db = Registry::get('Db');
  }

  /**
   * Retrieves customer data based on the provided customer ID.
   *
   * @param int $id The ID of the customer to fetch the data for.
   * @return array|null An array containing customer data if found, or null if no data is found.
   */
  public function getData(int $id): ?array
  {
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

    return $Qcustomer->fetch();
  }

  /**
   * Retrieves the email address of a customer based on their unique ID.
   *
   * @param int $id The unique identifier of the customer.
   * @return string The email address of the customer.
   */
  protected function getCustomerEmail(int $id): string
  {
    $result = $this->getData($id);

    return Hash::displayDecryptedEmail($result['customers_email_address']);
  }

  /**
   * Retrieves the customer ID associated with the given email address.
   *
   * @param string $email The email address of the customer.
   * @return int The ID of the customer if found, otherwise 0 or an exception if the email does not exist.
   */
  public function getCustomerIdByEmail(string $email): int
  {
    $email = Hash::displayDecryptedDataText($email);
    $Qcustomer = $this->db->prepare('select customers_id
                                        from :table_customers
                                        where customers_email_address = :customers_email_address
                                      ');
    $Qcustomer->bindValue(':customers_email_address', $email);
    $Qcustomer->execute();

    return $Qcustomer->valueInt('customers_id');
  }
}