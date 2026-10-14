<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;
use ClicShopping\Apps\Configuration\Administrators\Classes\ClicShoppingAdmin\AdministratorAdmin;

define('CLICSHOPPING_BASE_DIR', dirname(__DIR__, 3) . '/Core/ClicShopping/');

require_once(CLICSHOPPING_BASE_DIR . 'OM/CLICSHOPPING.php');
spl_autoload_register('ClicShopping\OM\CLICSHOPPING::autoload');

CLICSHOPPING::initialize();

CLICSHOPPING::loadSite('ClicShoppingAdmin');

AdministratorAdmin::hasUserAccess();

header('Content-Type: application/json');

if (!isset($_GET['options_id']) || !is_numeric($_GET['options_id'])) {
  echo json_encode(['type' => '', 'values' => []]);
  exit;
}

$CLICSHOPPING_Db = Registry::get('Db');
$CLICSHOPPING_Language = Registry::get('Language');

$options_id = (int)HTML::sanitize($_GET['options_id']);
$language_id = (int)$CLICSHOPPING_Language->getId();

// Resolve the option type so the client can render color_picker swatches.
$Qtype = $CLICSHOPPING_Db->prepare('select products_options_type
                                      from :table_products_options
                                      where products_options_id = :options_id
                                        and language_id = :language_id
                                      limit 1
                                    ');
$Qtype->bindInt(':options_id', $options_id);
$Qtype->bindInt(':language_id', $language_id);
$Qtype->execute();
$options_type = (string)$Qtype->value('products_options_type');

// Filter values via the bridge table so only values associated with the
// requested option are returned (e.g. only Red/Blue/Green for Color).
$Qvalues = $CLICSHOPPING_Db->prepare('select distinct pov.products_options_values_id as id,
                                                      pov.products_options_values_name as name
                                       from :table_products_options_values pov
                                       inner join :table_products_options_values_to_products_options pov2po
                                              on pov.products_options_values_id = pov2po.products_options_values_id
                                       where pov.language_id = :language_id
                                         and pov2po.products_options_id = :options_id
                                       order by pov.products_options_values_name
                                     ');
$Qvalues->bindInt(':language_id', $language_id);
$Qvalues->bindInt(':options_id', $options_id);
$Qvalues->execute();

$values = [];

while ($Qvalues->fetch()) {
  $values[] = [
    'id' => $Qvalues->valueInt('id'),
    'name' => $Qvalues->value('name'),
  ];
}

echo json_encode([
  'type' => $options_type,
  'values' => $values,
]);
exit;
