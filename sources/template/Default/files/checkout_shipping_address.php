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
use ClicShopping\Sites\Shop\AddressBook;

$CLICSHOPPING_MessageStack = Registry::get('MessageStack');
$CLICSHOPPING_Template = Registry::get('Template');
$CLICSHOPPING_Customer = Registry::get('Customer');
$CLICSHOPPING_Address = Registry::get('Address');

if ($CLICSHOPPING_MessageStack->exists('main')) {
  echo $CLICSHOPPING_MessageStack->get('main');
}

$process = false;

require_once($CLICSHOPPING_Template->getTemplateFiles('breadcrumb'));

echo HTML::form('checkout_shipping_address', CLICSHOPPING::link(null, 'Checkout&ShippingAddress&Process'), 'post', 'role="form" id="usrForm"', ['tokenize' => true, 'action' => 'process']);

// ------------------------------
// --- Shipping address   -----
// ------------------------------
?>
<section class="checkout_shipping_address" id="checkout_shipping_address">
  <div class="contentContainer">
    <div class="contentText">
      <?php
      if ($CLICSHOPPING_Customer->hasDefaultAddress()) {
        ?>
        <div class="mt-1"></div>
        <h3><?php echo CLICSHOPPING::getDef('table_heading_shipping_address'); ?></h3>

        <span class="col-md-6 float-start">
         <div><?php echo CLICSHOPPING::getDef('text_selected_shipping_destination'); ?></div>
      </span>

        <span class="col-md-6 float-end">
        <div class="card card-default">
          <div class="card-header"><?php echo CLICSHOPPING::getDef('title_shipping_address'); ?></div>
          <div class="card-block">
            <div class="mt-1"></div>
            <?php echo AddressBook::addressLabel($CLICSHOPPING_Customer->getID(), $_SESSION['sendto'], true, ' ', '<br />'); ?>
          </div>
        </div>
      </span>
        <div class="clearfix"></div>
        <?php
        $addresses_count = AddressBook::countCustomerAddressBookEntries();
        if ($addresses_count > 1) {
          ?>
          <h3><?php echo CLICSHOPPING::getDef('table_heading_address_book_entries'); ?></h3>
          <?php
// -----------------------------------
// --- Select other destination   -----
// ------------------------------- ----
          ?>
          <div><?php echo CLICSHOPPING::getDef('text_select_other_shipping_destination'); ?></div>
          <div class="mt-1"></div>
          <div class="d-flex flex-wrap ">
            <?php
            $radio_buttons = 0;

            $Qaddresses = AddressBook::getListing();

            while ($addresses = $Qaddresses->fetch()) {
              $format_id = $CLICSHOPPING_Address->getAddressFormatId($Qaddresses->valueInt('country_id'));
              ?>
              <div class="col-md-4 m-1">
                <div
                  class="card panel-<?php echo ($Qaddresses->valueInt('address_book_id') == $CLICSHOPPING_Customer->hasDefaultAddress()) ? 'primary' : 'default'; ?>">
                  <div class="card-header">
                    <strong><?php echo HTML::outputProtected(Hash::displayDecryptedDataText($Qaddresses->value('firstname')) . ' ' . Hash::displayDecryptedDataText($Qaddresses->value('lastname'))); ?></strong>
                  </div>
                  <div class="card-block">
                    <div class="mt-1"></div>
                    <?php echo $CLICSHOPPING_Address->addressFormat($format_id, $addresses, true, ' ', '<br />'); ?>
                  </div>
                  <div class="card-footer text-center">
                    <div class="custom-control custom-radio custom-control-inline">
                      <?php echo HTML::radioField('address', $Qaddresses->valueInt('address_book_id'), ($Qaddresses->valueInt('address_book_id') == $_SESSION['sendto']), 'class="custom-control-input" id="address_book_id' . $radio_buttons . '" name="address_book_id' . $radio_buttons . '"'); ?>
                      <label class="custom-control-label" for="address_book_id<?php echo $radio_buttons; ?>"</label>
                    </div>
                  </div>
                </div>
              </div>
              <?php
              $radio_buttons++;
            } // end while
            ?>
          </div>
          <?php
        } // $addresses_count
      } else {
        $process = true;
      } // has_default

      // ------------------------------
      // --- new address -------------
      // -------------------------------
      if ($addresses_count < MAX_ADDRESS_BOOK_ENTRIES) {
        ?>

        <div class="mt-1"></div>
        <div class="col-md-12">
          <div class="card">
            <div class="card-header">
              <span class="alert-warning float-end"
                    role="alert"><?php echo CLICSHOPPING::getDef('form_required'); ?></span>
              <h3><span><?php echo CLICSHOPPING::getDef('table_heading_new_shipping_address'); ?></span></h3>
            </div>

            <div class="card-block">
              <div class="mt-1"></div>
              <div class="card-text">
                <span><?php echo CLICSHOPPING::getDef('text_create_new_shipping_address'); ?></span>
                <?php
                if ($CLICSHOPPING_Customer->getCustomersGroupID() == 0 || ACCOUNT_ADRESS_BOOK_PRO == 'true') {
                  require_once($CLICSHOPPING_Template->getTemplateModules('customers_address/checkout_new_address'));
                }
                ?>
              </div>
            </div>
          </div>
        </div>
        <?php
      }
      ?>
      <div class="mt-1"></div>
      <div class="control-group">
        <div>
          <div class="buttonSet">
            <span class="float-end"><label
                for="buttonContinue"><?php echo HTML::button(CLICSHOPPING::getDef('button_continue'), null, null, 'success'); ?></label></span>
          </div>
        </div>
      </div>
      <?php
      if ($process === true) {
        ?>
        <div class="control-group">
          <div>
            <div class="buttonSet">
              <span class="float-start"><label
                  for="buttonBack"><?php echo HTML::button(CLICSHOPPING::getDef('button_back'), '', CLICSHOPPING::link(null, 'Checkout&ShippingAddress'), 'info'); ?></label></span>
            </div>
          </div>
        </div>
        <?php
      }
      ?>
    </div>
    <div class="mt-1"></div>
  </div>
  <div class="mt-1"></div>
</section>
</form>