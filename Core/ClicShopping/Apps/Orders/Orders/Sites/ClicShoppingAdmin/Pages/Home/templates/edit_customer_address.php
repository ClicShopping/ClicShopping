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

$Qcustomers = $CLICSHOPPING_Orders->db->prepare('select c.customers_id,
                                                          c.customers_firstname,
                                                          c.customers_lastname,
                                                          c.customers_company,
                                                          a.address_book_id,
                                                          a.entry_street_address,
                                                          a.entry_suburb,
                                                          a.entry_postcode,
                                                          a.entry_city,
                                                          a.entry_state,
                                                          a.entry_country_id,
                                                          a.entry_zone_id
                                                   from :table_customers c left join :table_address_book a on c.customers_default_address_id = a.address_book_id
                                                   where a.customers_id = c.customers_id
                                                   and c.customers_id = :customers_id
                                                 ');
$Qcustomers->bindInt(':customers_id', $customer_id);
$Qcustomers->execute();

$customers_firstname = Hash::displayDecryptedDataText($Qcustomers->value('customers_firstname'));
$customers_lastname = Hash::displayDecryptedDataText($Qcustomers->value('customers_lastname'));
$customers_company = Hash::displayDecryptedDataText($Qcustomers->value('customers_company'));

$address_book_id = $Qcustomers->valueInt('address_book_id');
$entry_street_address = Hash::displayDecryptedDataText($Qcustomers->value('entry_street_address'));
$entry_suburb = Hash::displayDecryptedDataText($Qcustomers->value('entry_suburb'));
$entry_postcode = Hash::displayDecryptedDataText($Qcustomers->value('entry_postcode'));
$entry_city = Hash::displayDecryptedDataText($Qcustomers->value('entry_city'));
$entry_state = $Qcustomers->value('entry_state');
$entry_country_id = $Qcustomers->valueInt('entry_country_id');
$entry_zone_id = $Qcustomers->valueInt('entry_zone_id');

echo HTML::form('update_address', $CLICSHOPPING_Orders->link('Orders&UpdateCustomerAddress'), 'post', 'role="form"');

//  echo HTML::form('pop_up',         $CLICSHOPPING_Orders->link('CreateOrder&ProductsPopUpSave'), 'post', 'role="form"');

echo HTML::hiddenField('address_book_id', $address_book_id);
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
        <?php echo HTML::inputField('customers_company', $customers_company, 'maxlength="32" placeholder="' . $CLICSHOPPING_Orders->getDef('customers_company') . '"', true); ?>
      </div>
    </div>
  </div>
  <div class="mt-1"></div>
  <class
  ="row">
  <div class="col-md-12">
    <div class="form-group row">
      <label for="<?php echo $CLICSHOPPING_Orders->getDef('entry_first_name'); ?>"
             class="col-5 col-form-label"><?php echo $CLICSHOPPING_Orders->getDef('entry_first_name'); ?></label>
      <div class="col-md-7">
        <?php echo HTML::inputField('customers_firstname', $customers_firstname, 'maxlength="32" required aria-required="true" placeholder="' . $CLICSHOPPING_Orders->getDef('entry_first_name') . '"', true); ?>
      </div>
    </div>
  </div>
  <div class="mt-1"></div>
  <div class="row">
    <div class="col-md-12">
      <div class="form-group row">
        <label for="<?php echo $CLICSHOPPING_Orders->getDef('entry_last_name'); ?>"
               class="col-5 col-form-label"><?php echo $CLICSHOPPING_Orders->getDef('entry_last_name'); ?></label>
        <div class="col-md-7">
          <?php echo HTML::inputField('customers_lastname', $customers_lastname, 'maxleh="32" required aria-required="true" placeholder="' . $CLICSHOPPING_Orders->getDef('entry_last_name') . '"', true); ?>
        </div>
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
          <?php echo HTML::inputField('customers_street_address', $entry_street_address, 'maxlength="32" required aria-required="true" placeholder="' . $CLICSHOPPING_Orders->getDef('entry_address') . '"', true); ?>
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
          <?php echo HTML::selectMenuCountryList('country', $entry_country_id, 'onchange="update_zone(this.form);"'); ?>
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
          echo HTML::selectField('state', $CLICSHOPPING_Address->getPrepareCountryZonesPullDown($entry_country_id), $entry_zone_id);
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