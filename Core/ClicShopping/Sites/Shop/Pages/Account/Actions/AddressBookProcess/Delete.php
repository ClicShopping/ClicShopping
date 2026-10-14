<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Sites\Shop\Pages\Account\Actions\AddressBookProcess;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;
use ClicShopping\Sites\Shop\AddressBook;

class Delete extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {

    $CLICSHOPPING_Customer = Registry::get('Customer');
    $CLICSHOPPING_MessageStack = Registry::get('MessageStack');
    $CLICSHOPPING_Hooks = Registry::get('Hooks');

    if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
      if ($_GET['delete'] == $CLICSHOPPING_Customer->getDefaultAddressID()) {
        $CLICSHOPPING_MessageStack->add(CLICSHOPPING::getDef('warning_primary_address_deletion'), 'error');

        CLICSHOPPING::redirect(null, 'Account&AddressBook');
      }

      if (AddressBook::countCustomersAddAddress() == 0) {
        $CLICSHOPPING_MessageStack->add(CLICSHOPPING::getDef('error_address_book_no_add'), 'error');

        CLICSHOPPING::redirect(null, 'Account&AddressBook');
      }
    }

    if (isset($_GET['action']) && ($_GET['action'] == 'deleteconfirm') && isset($_GET['delete']) && is_numeric($_GET['delete']) && isset($_GET['formid']) && hash_equals($_SESSION['sessiontoken'], $_GET['formid'])) {
      if ($_GET['delete'] == $CLICSHOPPING_Customer->get('default_address_id')) {
        $CLICSHOPPING_MessageStack->add(CLICSHOPPING::getDef('warning_primary_address_deletion'), 'error');
      } else {
        AddressBook::deleteEntry($_GET['delete']);
        $CLICSHOPPING_MessageStack->add(CLICSHOPPING::getDef('success_address_book_entry_deleted'), 'success');
      }

      $CLICSHOPPING_Hooks->call('AddressBookProcess', 'DeleteConfirm');

      CLICSHOPPING::redirect(null, 'Account&AddressBook');
    }
  }
}