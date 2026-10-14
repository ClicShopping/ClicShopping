<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Sites\ClicShoppingAdmin;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;
use function in_array;
/**
 * Class HTMLOverrideAdmin
 *
 * This class extends the HTML class and provides additional functionalities
 * specific to the ClicShoppingAdmin environment, such as sanitizing strings,
 * generating HTML form elements, dynamic JavaScript for zone fields, and
 * exporting data to CSV files.
 */
class HTMLOverrideAdmin extends HTML
{
  /*
   *  remplace les espaces par un +
   *
   * @param string $string
   * @return string $string,
   *
   */

  /**
   * Replaces spaces with '+' and replaces angle brackets '<' and '>' with '_'.
   *
   * @param string $string The input string to be sanitized and modified.
   * @return string The sanitized and modified string.
   */
  public static function sanitizeReplace(string $string): string
  {
    $string = preg_replace("/ /", "+", $string);
    return preg_replace("/[<>]/", '_', $string);
  }


  /**
   * Generates a dropdown menu (HTML select element) populated with product names and prices.
   *
   * @param string $name The name attribute for the select element.
   * @param mixed $parameters Optional additional parameters for the select element attributes (e.g., id or custom attributes).
   * @param mixed $exclude Optional list of products (or product-group combinations) to exclude from the dropdown.
   * @param string $class Optional CSS class for the select element, defaults to 'form-control'.
   * @return string The generated HTML for the dropdown menu.
   */
  public static function selectMenuProductsPullDown(string $name, $parameters = '', $exclude = '', string $class = 'form-control'): string
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_Language = Registry::get('Language');
    $CLICSHOPPING_Currencies = Registry::get('Currencies');

    if (empty($exclude)) {
      $exclude = [];
    }

    $select_string = '<select name="' . $name . '"';

    if ($parameters) {
      $select_string .= ' ' . $parameters;
    }

    if (!empty($class)) $select_string .= ' class="' . $class . '"';

    $select_string .= ' >';

    $all_groups = [];

    $QcustomersGroups = $CLICSHOPPING_Db->prepare('select customers_group_name,
                                                             customers_group_id
                                                      from :table_customers_groups
                                                      order by customers_group_id
                                                    ');
    $QcustomersGroups->execute();

    while ($existing_groups = $QcustomersGroups->fetch()) {
      $all_groups[$existing_groups['customers_group_id']] = $existing_groups['customers_group_name'];
    }

    $Qproducts = $CLICSHOPPING_Db->prepare('select p.products_id,
                                                     pd.products_name,
                                                     p.products_price
                                              from :table_products p,
                                                   :table_products_description pd
                                              where p.products_id = pd.products_id
                                              and pd.language_id = :language_id
                                              and p.products_archive = 0
                                              order by products_name
                                             ');
    $Qproducts->bindInt(':language_id', (int)$CLICSHOPPING_Language->getId());
    $Qproducts->execute();

    while ($Qproducts->fetch()) {
      if (MODE_B2B_B2C == 'True') {
        if (!in_array($Qproducts->valueInt('products_id'), $exclude)) {

          $Qprice = $CLICSHOPPING_Db->prepare('select customers_group_price,
                                                  customers_group_id
                                          from :table_products_groups
                                          where products_id = :products_id
                                         ');
          $Qprice->bindInt(':products_id', $Qproducts->valueInt('products_id'));
          $Qprice->execute();

          $product_prices = [];

          while ($prices_array = $Qprice->fetch()) {
            $product_prices[$prices_array['customers_group_id']] = $prices_array['customers_group_price'];
          }

          $price_string = '';
          $sde = 0;
//while(list($sdek,$sdev)=each($all_groups)){
          foreach ($all_groups as $sdek => $sdev) {
            if (!in_array($Qproducts->valueInt('products_id') . ":" . (int)$sdek, $exclude)) {
              if ($sde)
                $price_string .= ' - ';
              $price_string .= $sdev . ' : ' . $CLICSHOPPING_Currencies->format(isset($product_prices[$sdek]) ? $product_prices[$sdek] : $Qproducts->valueDecimal('products_price'));
              $sde = 1;
            }
          }

// Ajouter VISITOR_NAME . ': ' . $CLICSHOPPING_Currencies->format($Qproducts->valueDecimal('products_price')) pour permettre d'afficher le prix des clients qui ne font pas partie d'un groupe B2B(
          $select_string .= '<option value="' . $Qproducts->valueInt('products_id') . '">' . HTML::outputProtected($Qproducts->value('products_name')) . ' (' . CLICSHOPPING::getDef('visitor_name') . ': ' . $CLICSHOPPING_Currencies->format($Qproducts->valueDecimal('products_price')) . ' - ' . $price_string . ')</option>';
        }
      } else {
        if (!in_array($Qproducts->valueInt('products_id'), $exclude)) {
          $select_string .= '<option value="' . $Qproducts->valueInt('products_id') . '">' . HTML::outputProtected($Qproducts->value('products_name')) . ' (' . $CLICSHOPPING_Currencies->format($Qproducts->valueDecimal('products_price')) . ')</option>';
        }
      }

// ####### END  #######
    }

    $select_string .= '</select>';

    return $select_string;
  }


  /**
   * Generates a JavaScript string to populate zone selection options based on the provided country.
   *
   * @param string $country The variable representing the selected country identifier.
   * @param string $form The name of the form containing the zone field to be updated.
   * @param string $field The name of the form field to populate with zones.
   *
   * @return string A JavaScript string containing the conditional logic to update zone options.
   */
  public static function getJsZoneList(string $country, string $form, string $field): string
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $Qcountries = $CLICSHOPPING_Db->prepare('select distinct zone_country_id
                                               from :table_zones
                                               where  zone_status = 0
                                               order by zone_country_id
                                              ');
    $Qcountries->execute();

    $num_country = 1;
    $output_string = '';

    while ($countries = $Qcountries->fetch()) {
      if ($num_country == 1) {
        $output_string .= '  if (' . $country . ' == "' . (int)$countries['zone_country_id'] . '") {' . "\n";
      } else {
        $output_string .= '  if (' . $country . ' == "' . (int)$countries['zone_country_id'] . '") {' . "\n";
      }

      $Qzone = $CLICSHOPPING_Db->prepare('select zone_name,
                                                   zone_id
                                            from :table_zones
                                            where  zone_country_id = :zone_country_id
                                            and zone_status = 0
                                            order by zone_name
                                          ');
      $Qzone->bindInt(':zone_country_id', $countries['zone_country_id']);

      $Qzone->execute();

      $num_state = 1;

      while ($states = $Qzone->fetch()) {
        if ($num_state == '1') $output_string .= '    ' . $form . '.' . $field . '.options[0] = new Option("' . CLICSHOPPING::getDef('text_selected') . '", ""); ' . "\n";
        $output_string .= '    ' . $form . '.' . $field . '.options[' . $num_state . '] = new Option("' . $states['zone_name'] . '", "' . $states['zone_id'] . '");' . "\n";
        $num_state++;
      }
      $output_string .= ' } ';
      $num_country++;
    }

    $output_string .= '   else {' . "\n" .
      '    ' . $form . '.' . $field . '.options[0] = new Option("' . CLICSHOPPING::getDef('text_select') . '", "");' . "\n" .
      '  }' . "\n";

    return $output_string;
  }

  /**
   * Exports an array of data to a CSV file and triggers a download in the user's browser.
   *
   * @param array $data The array of data to be exported. Each element represents a row, and each row is an associative array with column-value pairs.
   * @param string $filename The name of the file to be downloaded, without the extension. Default is 'export'.
   * @param string $delimiter The delimiter to be used in the CSV file. Default is ';'.
   * @param string $extension The file extension for the exported file. Default is 'csv'.
   * @param string $enclosure The character used to enclose fields in the CSV file. Default is '"'.
   *
   * @return void
   */
  public function exportDataToCsv(array $data, string $filename = 'export', string $delimiter = ';', string $extension = 'csv', string $enclosure = '"')
  {
    header("Content-disposition: attachment; filename=$filename.$extension");
    header("Content-Type: text/csv");

    $fp = fopen('php://output', 'w');

    // Insert the UTF-8 BOM in the file
    fwrite($fp, $bom = (chr(0xEF) . chr(0xBB) . chr(0xBF)));

    // I add the array keys as CSV headers
    fputcsv($fp, array_keys($data[0]), $delimiter, $enclosure);

    // Add all the data in the file
    foreach ($data as $fields) {
      fputcsv($fp, $fields, $delimiter, $enclosure);
    }

    fclose($fp);

    die();
  }
}