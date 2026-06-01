<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Customers\Customers\Sites\ClicShoppingAdmin\Pages\Home\Actions\Customers;

use ClicShopping\OM\Hash;
use ClicShopping\OM\Registry;

class ExportCustomerInfo extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  protected $use_site_template = false;

  public function execute()
  {
    $CLICSHOPPING_Customers = Registry::get('Customers');

    $customer_id = (isset($_GET['customers_id']) && is_numeric($_GET['customers_id'])) ? (int)$_GET['customers_id'] : 0;

    $Qcustomers = $CLICSHOPPING_Customers->db->prepare('select c.*,
                                                                  a.*
                                                            from :table_customers c left join :table_address_book a on c.customers_default_address_id = a.address_book_id
                                                            where c.customers_id = :customers_id
                                                          ');
    $Qcustomers->bindInt(':customers_id', $customer_id);
    $Qcustomers->execute();

    $customers = $Qcustomers->fetch();

    if ($customers === false) {
      $CLICSHOPPING_Customers->redirect('Customers');
      return;
    }

    // Encode a CSV cell: neutralise formula injection (=,+,-,@,TAB,CR for non-numeric
    // values), escape embedded double quotes (RFC 4180) and wrap the value in quotes.
    $cell = static function ($value): string {
      $value = (string)$value;

      if ($value !== '' && !is_numeric($value) && str_contains("=+-@\t\r", $value[0])) {
        $value = "'" . $value;
      }

      return '"' . str_replace('"', '""', $value) . '"';
    };

    $columns = [
      'customers_id', 'customers_company', 'customers_siret', 'customers_ape',
      'customers_tva_intracom', 'customers_tva_intracom_code_iso', 'customers_gender',
      'customers_firstname', 'customers_lastname', 'customers_dob', 'customers_email_address',
      'customers_telephone', 'customers_newsletter', 'entry_company', 'entry_street_address',
      'entry_suburb', 'entry_postcode', 'entry_city', 'entry_state', 'entry_country_id',
      'entry_zone_id', 'customers_default_address_id'
    ];

    // Fields stored AES-encrypted are decrypted for the export.
    $encrypted = ['customers_company', 'customers_firstname', 'customers_lastname',
      'customers_telephone', 'entry_company', 'entry_street_address', 'entry_suburb',
      'entry_postcode', 'entry_city'];

    $head = '"' . implode('", "', $columns) . '"' . "\r\n";

    $cells = [];
    foreach ($columns as $column) {
      $value = $customers[$column] ?? '';

      if (\in_array($column, $encrypted, true)) {
        $value = Hash::displayDecryptedDataText($value);
      }

      $cells[] = $cell($value);
    }

    $content = $head . implode(',', $cells) . "\r\n";

    header('Content-Type: application/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=customer.csv');

    echo $content;

    exit;
  }
}