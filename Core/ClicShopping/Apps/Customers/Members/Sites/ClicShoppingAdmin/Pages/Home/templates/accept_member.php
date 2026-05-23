<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Hash;
use ClicShopping\OM\HTML;
use ClicShopping\OM\ObjectInfo;
use ClicShopping\OM\Registry;

use ClicShopping\Apps\Customers\Groups\Classes\ClicShoppingAdmin\GroupsB2BAdmin;


$CLICSHOPPING_Members = Registry::get('Members');
$CLICSHOPPING_Template = Registry::get('TemplateAdmin');

$page = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int)$_GET['page'] : 1;

// Permettre l'utilisation de l'approbation des comptes en mode B2B
if (MODE_B2B_B2C == 'False') CLICSHOPPING::redirect();

if (isset($_GET['cID'])) $cID = HTML::sanitize($_GET['cID']);

$Qcustomers = $CLICSHOPPING_Members->db->prepare('select customers_id,
                                                          customers_lastname,
                                                          customers_firstname,
                                                          customers_group_id
                                                    from :table_customers
                                                    where customers_id = :customers_id
                                                   ');

$Qcustomers->bindInt(':customers_id', $cID);
$Qcustomers->execute();

$Qreviews = $CLICSHOPPING_Members->db->prepare('select count(*) as number_of_reviews
                                                   from :table_reviews
                                                   where customers_id = :customers_id
                                                  ');
$Qreviews->bindInt(':customers_id', $cID);
$Qreviews->execute();

$cInfo_array = array_merge($Qcustomers->toArray(), $Qreviews->toArray());
$cInfo = new ObjectInfo($cInfo_array);
?>
<div class="contentBody">

  <div class="row">
    <div class="col-md-12">
      <div class="card card-block headerCard">
        <div class="row">
          <span
            class="col-md-1 logoHeading"><?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'categories/client_attente.gif', $CLICSHOPPING_Members->getDef('heading_title'), '40', '40'); ?></span>
          <span
            class="col-md-8 pageHeading"><?php echo '&nbsp;' . $CLICSHOPPING_Members->getDef('heading_title'); ?></span>
        </div>
      </div>
    </div>
  </div>

  <div class="col-md-12 mainTitle">
    <strong><?php echo Hash::displayDecryptedDataText($cInfo->customers_firstname) . ' ' . Hash::displayDecryptedDataText($cInfo->customers_lastname); ?></strong></div>
  <?php echo HTML::form('customers', $CLICSHOPPING_Members->link('Members&ConfirmMembers&cID=' . $cInfo->customers_id)); ?>
  <div class="adminformTitle">
    <div class="row">
      <div class="mt-1"></div>
      <div class="col-md-12"><?php echo $CLICSHOPPING_Members->getDef('text_accept_intro'); ?><br/><br/></div>
      <div class="mt-1"></div>
      <div
        class="col-md-5"><?php echo HTML::selectMenu('customers_group_id', GroupsB2BAdmin::getCustomersGroup(), $cInfo->customers_group_id ?? null); ?>
        <br/><br/></div>
      <div class="mt-1"></div>
      <div
        class="col-md-12"><?php echo HTML::checkboxField('delete_reviews', 'on', true) . ' ' . $CLICSHOPPING_Members->getDef('text_delete_reviews', ['delete_number' => $cInfo->number_of_reviews ?? null]); ?>
        <br/><br/></div>
      <div class="mt-1"></div>
      <div class="col-md-12 text-center">
        <span><br/><?php echo HTML::button($CLICSHOPPING_Members->getDef('button_activate'), null, null, 'primary', null, 'sm') . ' </span><span>' . HTML::button($CLICSHOPPING_Members->getDef('button_cancel'), null, $CLICSHOPPING_Members->link('Members&page=' . $page . '&cID=' . $cInfo->customers_id), 'warning', null, 'sm'); ?></span>
      </div>
    </div>
  </div>
  </form>
</div>
