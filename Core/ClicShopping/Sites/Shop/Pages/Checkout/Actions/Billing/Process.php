<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Sites\Shop\Pages\Checkout\Actions\Billing;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;
use function is_array;

class Process extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {
    $CLICSHOPPING_MessageStack = Registry::get('MessageStack');
    $CLICSHOPPING_Customer = Registry::get('Customer');
    $CLICSHOPPING_NavigationHistory = Registry::get('NavigationHistory');
    $CLICSHOPPING_Hooks = Registry::get('Hooks');
    $CLICSHOPPING_Template = Registry::get('Template');

    if (isset($_POST['action']) && ($_POST['action'] == 'process') && isset($_POST['formid']) && ($_POST['formid'] === $_SESSION['sessiontoken'])) {
// if the customer is not logged on, redirect them to the login page
      if (!$CLICSHOPPING_Customer->isLoggedOn()) {
        $CLICSHOPPING_NavigationHistory->setSnapshot();
        CLICSHOPPING::redirect(null, 'Account&LogIn');
      }

      if (isset($_POST['comments']) && !\is_null($_POST['comments'])) {
        $_SESSION['comments'] = HTML::sanitize($_POST['comments']);
      }

// Confirmation des conditions des vente
      if (DISPLAY_CONDITIONS_ON_CHECKOUT == 'true') {
        if (!isset($_POST['conditions']) || HTML::sanitize($_POST['conditions']) != 1) {
          $CLICSHOPPING_MessageStack->add(CLICSHOPPING::getDef('error_conditions_not_accepted'), 'error');
          CLICSHOPPING::redirect(null, 'Checkout&Billing');
        }
      }

      $source_folder = CLICSHOPPING::getConfig('dir_root', 'Shop') . 'includes/Module/Hooks/Shop/CheckoutPayment/';

      if (is_dir($source_folder)) {
        $files_get = $CLICSHOPPING_Template->getSpecificFiles($source_folder, 'CheckoutPayment*');

        if (is_array($files_get)) {
          foreach ($files_get as $value) {
            if (!empty($value['name'])) {
              $CLICSHOPPING_Hooks->call('CheckoutPayment', $value['name']);
            }
          }
        }
      }

      CLICSHOPPING::redirect(null, 'Checkout&Confirmation');

    } else {
      $CLICSHOPPING_MessageStack->add(CLICSHOPPING::getDef('error_during_process'), 'error');

      CLICSHOPPING::redirect(null, 'Checkout&Billing');
    }
  }
}