<?php
/**
 *
 * @copyright 2008 - https://www.clicshopping.org
 * @Brand : ClicShoppingAI(TM) at Inpi all right Reserved
 * @Licence GPL 2 & MIT
 * @Info : https://www.clicshopping.org/forum/trademark/
 *
 */

namespace ClicShopping\Apps\Customers\Customers\Sites\ClicShoppingAdmin\Pages\Home\Actions\Customers;

use ClicShopping\OM\Hash;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

class ExportCustomerInfo extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  protected $use_site_template = false;

  public function execute()
  {
    $CLICSHOPPING_Customers = Registry::get('Customers');

    $customer_id = HTML::sanitize($_GET['customers_id']);

    $Qcustomers = $CLICSHOPPING_Customers->db->prepare('select c.*,
                                                                  a.*
                                                            from :table_customers c left join :table_address_book a on c.customers_default_address_id = a.address_book_id
                                                            where c.customers_id = :customers_id
                                                          ');
    $Qcustomers->bindInt(':customers_id', $customer_id);
    $Qcustomers->execute();

    $customers = $Qcustomers->fetch();

    $head = '"customers_id", "customers_company", "customers_siret", "customers_ape", "customers_tva_intracom", "customers_tva_intracom_code_iso", "customers_gender", "customers_firstname", "customers_lastname", "customers_dob", "customers_email_address", "customers_telephone", "customers_newsletter",  "entry_company", "entry_street_address", "entry_suburb", "entry_postcode", "entry_city", "entry_state", "entry_country_id", "entry_zone_id", "customers_default_address_id"' . "\r\n";

    $output = '"' . $customers['customers_id'] . '",';
    $output .= '"' . Hash::displayDecryptedDataText($customers['customers_company']) . '",';
    $output .= '"' . $customers['customers_siret'] . '",';
    $output .= '"' . $customers['customers_ape'] . '",';
    $output .= '"' . $customers['customers_tva_intracom'] . '",';
    $output .= '"' . $customers['customers_tva_intracom_code_iso'] . '",';
    $output .= '"' . $customers['customers_gender'] . '",';
    $output .= '"' . Hash::displayDecryptedDataText($customers['customers_firstname']) . '",';
    $output .= '"' . Hash::displayDecryptedDataText($customers['customers_lastname']) . '",';
    $output .= '"' . $customers['customers_dob'] . '",';
    $output .= '"' . $customers['customers_email_address'] . '",';
    $output .= '"' . Hash::displayDecryptedDataText($customers['customers_telephone']) . '",';
    $output .= '"' . $customers['customers_newsletter'] . '",';

    $output .= '"' . Hash::displayDecryptedDataText($customers['entry_company']) . '",';
    $output .= '"' . Hash::displayDecryptedDataText($customers['entry_street_address']) . '",';
    $output .= '"' . Hash::displayDecryptedDataText($customers['entry_suburb']) . '",';
    $output .= '"' . Hash::displayDecryptedDataText($customers['entry_postcode']) . '",';
    $output .= '"' . Hash::displayDecryptedDataText($customers['entry_city']) . '",';
    $output .= '"' . $customers['entry_state'] . '",';
    $output .= '"' . $customers['entry_country_id'] . '",';
    $output .= '"' . $customers['entry_zone_id'] . '",';
    $output .= '"' . $customers['customers_default_address_id'] . "\n";

    $foot = '' . "\r\n";

    $content = $head . $output . $foot;

    header('Content-Type: application/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=customer.csv');

    echo $content;

    exit;
  }
}