<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;

define('CLICSHOPPING_BASE_DIR', realpath(__DIR__ . '/../../Core/ClicShopping/') . DIRECTORY_SEPARATOR);

require_once(CLICSHOPPING_BASE_DIR . 'OM/CLICSHOPPING.php');
spl_autoload_register('ClicShopping\OM\CLICSHOPPING::autoload');

CLICSHOPPING::initialize();

CLICSHOPPING::loadSite('ClicShoppingAdmin');

$CLICSHOPPING_Db = Registry::get('Db');
$CLICSHOPPING_Currencies = Registry::get('Currencies');

$json = [];

$Qorders = $CLICSHOPPING_Db->prepare('select count(o.orders_id) AS total, 
                                              SUM(ot.value) AS amount, 
                                              c.countries_iso_code_2 
                                        from :table_orders o,
                                             :table_countries c, 
                                             :table_orders_total ot
                                        where o.orders_status = 3
                                        and o.billing_country = c.countries_name
                                        and ot.class = :class
                                        and o.orders_id = ot.orders_id
                                        group by o.billing_country
                                     ');
$Qorders->bindValue('class', 'ST');
$Qorders->execute();

$results = $Qorders->fetchAll();

if (\is_array($results)) {
  foreach ($results as $result) {
    $json[mb_strtolower($result['countries_iso_code_2'])] = [
      'total' => $result['total'],
      'amount' => $result['amount'],
    ];
  }
}

# JSON-encode the response
$json_response = json_encode($json); //Return the JSON Array

# Return the response
echo $json_response;
