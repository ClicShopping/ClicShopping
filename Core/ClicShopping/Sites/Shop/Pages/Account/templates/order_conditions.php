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

$CLICSHOPPING_Page = Registry::get('Site')->getPage();
$CLICSHOPPING_Language = Registry::get('Language');
$CLICSHOPPING_Db = Registry::get('Db');
$CLICSHOPPING_Customer = Registry::get('Customer');

$customer_id = $CLICSHOPPING_Customer->getID();

$oId = HTML::sanitize($_GET['order_id']);

if (\is_null($oId)) {
  CLICSHOPPING::redirect(null, 'Account&Main');
}

$QconditionGeneralOfSales = $CLICSHOPPING_Db->prepare('select page_manager_general_condition
                                                             from  :table_orders_pages_manager
                                                             where orders_id = :orders_id
                                                             and customers_id = :customers_id
                                                           ');

$QconditionGeneralOfSales->bindInt(':orders_id', $_GET['order_id']);
$QconditionGeneralOfSales->bindInt(':customers_id', $customer_id);

$QconditionGeneralOfSales->execute();

if ($QconditionGeneralOfSales->fetch() === false) {
  CLICSHOPPING::redirect(null, 'Account&Main');
}
?>

<html dir="ltr" lang="fr">
<head>
  <meta charset="UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <title><?php echo 'Conditions of sales'; ?></title>
</head>
<body onload="resize();">
<?php echo $QconditionGeneralOfSales->value('page_manager_general_condition'); ?>
</body>
</html>

