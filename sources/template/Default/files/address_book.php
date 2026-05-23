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

$CLICSHOPPING_Address = Registry::get('Address');

if ($CLICSHOPPING_MessageStack->exists('main')) {
  echo $CLICSHOPPING_MessageStack->get('main');
}

require_once($CLICSHOPPING_Template->getTemplateFiles('breadcrumb'));

// error checking when updating or adding an entry
$process = false;

// ----------------------
// --- Primary Address---
// ----------------------
?>
<section class="address_book" id="address_book">
  <div class="contentContainer col-md-12">
    <div class="contentText">
      <div class="mt-1"></div>
      <h3><?php echo CLICSHOPPING::getDef('primary_address_title'); ?></h3>
      <div class="col-md-9">
        <div><?php echo CLICSHOPPING::getDef('primary_address_description', ['store_name' => STORE_NAME]); ?></div>
      </div>
      <div class="mt-1"></div>
      <div class="col-md-3">
        <div class="boxHeading"><strong><?php echo CLICSHOPPING::getDef('primary_address_title'); ?></strong></div>
        <div class="boxContents">
          <?php echo AddressBook::addressLabel($CLICSHOPPING_Customer->getID(), $CLICSHOPPING_Customer->getDefaultAddressID(), true, ' ', '<br />'); ?>
        </div>
      </div>
      <?php
      // ----------------------
      // --- Address bool Title
      // ----------------------
      ?>
      <div class="mt-1"></div>
      <div class="mt-1"></div>
      <h3><?php echo CLICSHOPPING::getDef('primary_book_title'); ?></h3></
    >
    <div class="d-flex flex-wrap">
      <?php
      $Qaddresses = AddressBook::getListing();

      while ($addresses = $Qaddresses->fetch()) {
        $format_id = $CLICSHOPPING_Address->getAddressFormatId($Qaddresses->valueInt('country_id'));
        ?>

        <div class="col-md-6" style="padding-left:0.3rem;">
          <div
            class="card panel-<?php echo ($Qaddresses->valueInt('address_book_id') == $CLICSHOPPING_Customer->getDefaultAddressID()) ? 'primary' : 'default'; ?>">
            <?php
            // Controle autorisation au client de modifier son adresse par defaut
            if ((AddressBook::countCustomersModifyAddressDefault() == 0) && ($Qaddresses->valueInt('address_book_id') == $CLICSHOPPING_Customer->getDefaultAddressID())) {
              ?>
              <div class="card-header">
                <strong><?php echo HTML::outputProtected(Hash::displayDecryptedDataText($Qaddresses->value('firstname')) . ' ' . Hash::displayDecryptedDataText($Qaddresses->value('lastname'))); ?></strong><?php if ($Qaddresses->valueInt('address_book_id') == $CLICSHOPPING_Customer->getDefaultAddressID()) echo '&nbsp;<small><i>' . CLICSHOPPING::getDef('primary_address') . '</i></small>'; ?>
              </div>
              <div class="card-block">
                <div class="mt-1"></div>
                <?php echo $CLICSHOPPING_Address->addressFormat($format_id, $addresses, true, ' ', '<br />'); ?>
              </div>
              <?php
              // Autorisation de modifier l'adresse par defaut du client
            } else {
              ?>
              <div class="card-header">
                <strong><?php echo HTML::outputProtected(Hash::displayDecryptedDataText($Qaddresses->value('firstname')) . ' ' . Hash::displayDecryptedDataText($Qaddresses->value('lastname'))); ?></strong><?php if ($Qaddresses->valueInt('address_book_id') == $CLICSHOPPING_Customer->getDefaultAddressID()) echo '&nbsp;<small><i>' . CLICSHOPPING::getDef('primary_address') . '</i></small>'; ?>
              </div>
              <div class="card-block">
                <div class="mt-1"></div>
                <?php echo $CLICSHOPPING_Address->addressFormat($format_id, $addresses, true, ' ', '<br />'); ?>
              </div>
              <div class="card-footer text-center">
                <?php
                echo '<label for="buttonEdit">' . HTML::button(CLICSHOPPING::getDef('button_edit'), null, CLICSHOPPING::link(null, 'Account&AddressBookProcess&Edit&edit=' . $Qaddresses->valueInt('address_book_id')), 'success', null, 'sm') . '</label> ';

                if ($Qaddresses->valueInt('address_book_id') != $CLICSHOPPING_Customer->getDefaultAddressID()) {
                  echo '<label for="buttonDelete">' . HTML::button(CLICSHOPPING::getDef('button_delete'), null, CLICSHOPPING::link(null, 'Account&AddressBookProcess&Delete&delete=' . $Qaddresses->valueInt('address_book_id')), 'danger', null, 'sm') . '</label>';
                }
                ?>
              </div>
              <?php
            }
            ?>
          </div>
        </div>
        <?php
      }
      ?>
    </div>
    <?php
    // ----------------------
    // --- Max Address   -----
    // ----------------------
    if (AddressBook::countCustomersAddAddress() == 1) {
      ?>
      <div class="mt-1"></div>
      <div>
        <div><?php echo CLICSHOPPING::getDef('text_maximum_entries', ['max_entries' => (int)MAX_ADDRESS_BOOK_ENTRIES]); ?></div>
      </div>
      <?php
    }
    // ----------------------
    // --- Button   -----
    // ----------------------
    ?>
    <div class="mt-1"></div>
    <div class="control-group">
      <div>
          <span class="buttonSet">
            <span class="col-md-6"><label
                for="buttonBack"><?php echo HTML::button(CLICSHOPPING::getDef('button_back'), null, CLICSHOPPING::link(null, 'Account&Main'), 'primary'); ?></label></span>
<?php
// Controle autorisation du client a ajouter des adresse dans son carnet selon la quantite ou sa fiche client
if (AddressBook::countCustomerAddressBookEntries() < (int)MAX_ADDRESS_BOOK_ENTRIES && AddressBook::countCustomersAddAddress() == 1) {
?>
            <span class="col-md-6 text-end"><span class="buttonAction">
              <?php echo '<label for="buttonAddAddress">' . HTML::button(CLICSHOPPING::getDef('button_add_address'), null, CLICSHOPPING::link(null, 'Account&AddressBookProcess&Create'), 'success') . '</label>'; ?>
            </span>
<?php
}
?>
          </span>
      </div>
    </div>
  </div>
  </div>
</section>