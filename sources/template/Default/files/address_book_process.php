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
use ClicShopping\Sites\Shop\AddressBook;

$CLICSHOPPING_Customer = Registry::get('Customer');
$CLICSHOPPING_NavigationHistory = Registry::get('NavigationHistory');
$CLICSHOPPING_MessageStack = Registry::get('MessageStack');
$CLICSHOPPING_Template = Registry::get('Template');

if ($CLICSHOPPING_MessageStack->exists('main')) {
  echo $CLICSHOPPING_MessageStack->get('main');
}

require_once($CLICSHOPPING_Template->getTemplateFiles('breadcrumb'));

if (isset($_GET['newcustomer'])) {
  $newcustomer = '&newcustomer=' . 1;
} else {
  $newcustomer = '';
}

if (!isset($_GET['delete']) && isset($_GET['edit'])) {
  $entry = AddressBook::getEntry((int)$_GET['edit']);

  echo HTML::form('addressbook', CLICSHOPPING::link(null, 'Account&AddressBookProcess' . (isset($_GET['edit']) ? '&Edit&edit=' . HTML::sanitize($_GET['edit']) : '') . $newcustomer), 'post', 'id="addressbook"', ['tokenize' => true]);
} elseif (!isset($_GET['delete']) && !isset($_POST['edit'])) {
  echo HTML::form('addressbook', CLICSHOPPING::link(null, 'Account&AddressBookProcess&Create&action=process'), 'post', 'id="addressbook"', ['tokenize' => true]);
}
?>
<section class="address_book_process" id="address_book_process">
  <div class="contentContainer">
    <div class="contentText">
      <h2><?php if (isset($_GET['edit'])) {
          echo CLICSHOPPING::getDef('heading_title_modify_entry');
        } elseif (isset($_GET['delete'])) {
          echo CLICSHOPPING::getDef('heading_title_delete_entry');
        } else {
          echo CLICSHOPPING::getDef('heading_title_add_entry');
        } ?></h2>
      <?php
      // -------------------------------
      // --- Delete Adresse Title   -----
      // ------------------------------

      if (isset($_GET['delete'])) {
        ?>
        <div class="mt-1"></div>
        <span class="col-md-9"><strong><?php echo CLICSHOPPING::getDef('delete_address_description'); ?></strong></span>
        <div class="col-md-3">
          <div class="mt-1"></div>
          <div>
            <strong><?php echo CLICSHOPPING::getDef('selected_address'); ?></strong>
          </div>
          <div class="mt-1"></div>
          <div>
            <?php echo AddressBook::addressLabel($CLICSHOPPING_Customer->getID(), HTML::sanitize($_GET['delete']), true, ' ', '<br />'); ?>
          </div>
        </div>
        <?php
// ----------------------
// --- Button   -----
// ----------------------
        ?>
        <div class="mt-1"></div>
        <div class="control-group">
          <div class="buttonSet">
            <div class="col-md-6 float-start"><label
                for="buttonBack"><?php echo HTML::button(CLICSHOPPING::getDef('button_back'), null, CLICSHOPPING::link(null, 'Account&AddressBook'), 'primary'); ?></label>
            </div>
            <div class="col-md-6 float-end"><span class="buttonAction"><label
                  for="buttonDelete"><?php echo HTML::button(CLICSHOPPING::getDef('button_delete'), null, CLICSHOPPING::link(null, 'Account&AddressBookProcess&Delete&delete=' . HTML::sanitize($_GET['delete']) . '&action=deleteconfirm&formid=' . $_SESSION['sessiontoken']), 'danger'); ?></label></span>
            </div>
          </div>
        </div>
        <?php
      } else {
// ----------------------
//  Address Detail
// ----------------------
        require_once($CLICSHOPPING_Template->getTemplateModules('customers_address/address_book_details'));

        if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
          ?>
          <div class="contentText">
            <div class="control-group">
              <div>
                <div class="buttonSet">
                  <?php
                  if (empty($newcustomer)) {
                    ?>
                    <div class="col-md-6 float-start"><label
                        for="buttonBack"><?php echo HTML::button(CLICSHOPPING::getDef('button_back'), null, CLICSHOPPING::link(null, 'Account&AddressBook'), 'primary'); ?></label>
                    </div>
                    <?php
                  }
                  ?>
                  <div
                    class="col-md-6 float-end text-end"><?php echo HTML::hiddenField('action', 'update') . HTML::hiddenField('edit', (int)$_GET['edit']) . HTML::hiddenField('shopping', isset($_GET['shopping']) ?? null) . '<label for="buttonBack">' . HTML::button(CLICSHOPPING::getDef('button_update'), 'refresh', null, 'success') . '</label>'; ?></div>
                </div>
              </div>
            </div>
          </div>
          <div class="clearfix"></div>
          <?php
        } else {
          if (\count($CLICSHOPPING_NavigationHistory->snapshot) > 0 && !empty($newcustomer)) {
            $back_link = CLICSHOPPING::link($CLICSHOPPING_NavigationHistory->snapshot['application'], CLICSHOPPING::arrayToString($CLICSHOPPING_NavigationHistory->snapshot['get'], session_name()), $CLICSHOPPING_NavigationHistory->snapshot['mode']);
          } else {
            $back_link = CLICSHOPPING::link(null, 'Account&AddressBook');
          }
// ----------------------
// --- Button   -----
// ----------------------
          ?>
          <div class="control-group">
            <div>
              <div class="buttonSet">
                <span class="col-md-6"><label
                    for="buttonBack"><?php echo HTML::button(CLICSHOPPING::getDef('button_back'), null, $back_link, 'primary'); ?></label></span>
                <span class="col-md-6 text-end">
              <span class="buttonAction">
                <?php echo HTML::hiddenField('action', 'process') . '<label for="buttonContinue">' . HTML::button(CLICSHOPPING::getDef('button_continue'), null, null, 'success') . '</label>'; ?>
              </span>
            </span>
              </div>
            </div>
          </div>
          <?php
        }
        ?>
        </form>
        <?php
      }
      ?>
    </div>
  </div>
</section>