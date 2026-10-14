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
use ClicShopping\OM\Registry;

$CLICSHOPPING_Orders = Registry::get('Orders');
$CLICSHOPPING_Address = Registry::get('Address');

$customer_id = HTML::sanitize($_GET['customer_id']);
$order_id = HTML::sanitize($_GET['order_id']);

$Qcustomers = $CLICSHOPPING_Orders->db->prepare('select customers_id,
                                                          billing_name,
                                                          billing_company,
                                                          billing_street_address,
                                                          billing_suburb,
                                                          billing_city,
                                                          billing_postcode,
                                                          billing_state,
                                                          billing_country
                                                   from :table_orders       
                                                   where orders_id = :order_id
                                                   and customers_id = :customer_id
                                                 ');
$Qcustomers->bindInt(':customer_id', $customer_id);
$Qcustomers->bindInt(':order_id', $order_id);

$Qcustomers->execute();

$billing_name = Hash::displayDecryptedDataText($Qcustomers->value('billing_name'));
$customers_company = Hash::displayDecryptedDataText($Qcustomers->value('billing_company'));
$entry_street_address = Hash::displayDecryptedDataText($Qcustomers->value('billing_street_address'));
$entry_suburb = Hash::displayDecryptedDataText($Qcustomers->value('billing_suburb'));
$entry_postcode = Hash::displayDecryptedDataText($Qcustomers->value('billing_postcode'));
$entry_city = Hash::displayDecryptedDataText($Qcustomers->value('billing_city'));
$entry_state = $Qcustomers->value('billing_state');
$entry_country = $Qcustomers->value('billing_country');

echo HTML::form('update_payment_address', $CLICSHOPPING_Orders->link('Orders&UpdatePaymentAddress'), 'post', 'role="form"');

echo HTML::hiddenField('order_id', $order_id);
echo HTML::hiddenField('customer_id', $customer_id);
?>
<div class="col-md-12">
  <class
  ="row">
  <div class="col-md-12">
    <div class="form-group row">
      <label for="<?php echo $CLICSHOPPING_Orders->getDef('customers_company'); ?>"
             class="col-5 col-form-label"><?php echo $CLICSHOPPING_Orders->getDef('customers_company'); ?></label>
      <div class="col-md-7">
        <?php echo HTML::inputField('billing_company', $customers_company, 'maxlength="32" placeholder="' . $CLICSHOPPING_Orders->getDef('customers_company') . '"', true); ?>
      </div>
    </div>
  </div>
  <div class="mt-1"></div>
  <class
  ="row">
  <div class="col-md-12">
    <div class="form-group row">
      <label for="<?php echo $CLICSHOPPING_Orders->getDef('entry_name'); ?>"
             class="col-5 col-form-label"><?php echo $CLICSHOPPING_Orders->getDef('entry_name'); ?></label>
      <div class="col-md-7">
        <?php echo HTML::inputField('billing_name', $billing_name, 'maxlength="32" required aria-required="true" placeholder="' . $CLICSHOPPING_Orders->getDef('entry_name') . '"', true); ?>
      </div>
    </div>
  </div>
  <div class="mt-1"></div>
  <div class="row">
    <div class="col-md-12">
      <div class="form-group row">
        <label for="<?php echo $CLICSHOPPING_Orders->getDef('entry_address'); ?>"
               class="col-5 col-form-label"><?php echo $CLICSHOPPING_Orders->getDef('entry_address'); ?></label>
        <div class="col-md-7">
          <?php echo HTML::inputField('billing_street_address', $entry_street_address, 'maxlength="32" required aria-required="true" placeholder="' . $CLICSHOPPING_Orders->getDef('entry_address') . '"', true); ?>
        </div>
      </div>
    </div>
  </div>
  <div class="mt-1"></div>
  <div class="row">
    <div class="col-md-12">
      <div class="form-group row">
        <label for="<?php echo $CLICSHOPPING_Orders->getDef('entry_suburb'); ?>"
               class="col-5 col-form-label"><?php echo $CLICSHOPPING_Orders->getDef('entry_suburb'); ?></label>
        <div class="col-md-7">
          <?php echo HTML::inputField('entry_suburb', $entry_suburb, 'maxlength="32" placeholder="' . $CLICSHOPPING_Orders->getDef('entry_suburb') . '"', true); ?>
        </div>
      </div>
    </div>
  </div>
  <div class="mt-1"></div>
  <div class="row">
    <div class="col-md-12">
      <div class="form-group row">
        <label for="<?php echo $CLICSHOPPING_Orders->getDef('entry_postcode'); ?>"
               class="col-5 col-form-label"><?php echo $CLICSHOPPING_Orders->getDef('entry_postcode'); ?></label>
        <div class="col-md-7">
          <?php echo HTML::inputField('entry_postcode', $entry_postcode, 'maxlength="32" required aria-required="true" placeholder="' . $CLICSHOPPING_Orders->getDef('entry_postcode') . '"', true); ?>
        </div>
      </div>
    </div>
  </div>
  <div class="mt-1"></div>
  <div class="row">
    <div class="col-md-12">
      <div class="form-group row">
        <label for="<?php echo $CLICSHOPPING_Orders->getDef('entry_city'); ?>"
               class="col-5 col-form-label"><?php echo $CLICSHOPPING_Orders->getDef('entry_city'); ?></label>
        <div class="col-md-7">
          <?php echo HTML::inputField('entry_city', $entry_city, 'maxlength="32" required aria-required="true" placeholder="' . $CLICSHOPPING_Orders->getDef('entry_city') . '"', true); ?>
        </div>
      </div>
    </div>
  </div>
  <div class="mt-1"></div>
  <div class="row">
    <div class="col-md-12">
      <div class="form-group row">
        <label for="<?php echo $CLICSHOPPING_Orders->getDef('entry_country_id'); ?>"
               class="col-5 col-form-label"><?php echo $CLICSHOPPING_Orders->getDef('entry_country_id'); ?></label>
        <div class="col-md-7">
          <?php
          $Qcountry = $CLICSHOPPING_Orders->db->get('countries', 'countries_id', ['countries_name' => $entry_country]);

          echo HTML::selectMenuCountryList('country', $Qcountry->valueInt('countries_id'), 'onchange="update_zone(this.form);"');
          ?>
        </div>
      </div>
    </div>
  </div>
  <div class="mt-1"></div>
  <div class="row">
    <div class="col-md-12">
      <div class="form-group row">
        <label for="<?php echo $CLICSHOPPING_Orders->getDef('entry_zone_id'); ?>"
               class="col-5 col-form-label"><?php echo $CLICSHOPPING_Orders->getDef('entry_zone_id'); ?></label>
        <div class="col-md-7">
          <?php
          $Qzones = $CLICSHOPPING_Orders->db->get('zones', ['zone_id'], ['zone_name' => $entry_state]);

          echo HTML::selectField('state', $CLICSHOPPING_Address->getPrepareCountryZonesPullDown($Qcountry->valueInt('countries_id')), $Qzones->valueInt('zone_id'));
          include_once(CLICSHOPPING::getConfig('dir_root', 'Shop') . 'ext/javascript/clicshopping/ClicShoppingAdmin/state_dropdown.php');
          ?>
        </div>
      </div>
    </div>
  </div>
</div>
<div class="mt-1"></div>
<div
  class="col-md-12 text-end"><?php echo HTML::button($CLICSHOPPING_Orders->getDef('button_update'), null, null, 'success'); ?></div>
<div class="mt-1"></div>
</form>