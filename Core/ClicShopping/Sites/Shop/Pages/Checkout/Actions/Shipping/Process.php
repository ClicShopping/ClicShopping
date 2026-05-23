<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Sites\Shop\Pages\Checkout\Actions\Shipping;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;
use ClicShopping\Sites\Shop\Shipping;
use function count;
use function defined;
use function is_array;
use function is_null;
use function is_object;

class Process extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {
    $CLICSHOPPING_MessageStack = Registry::get('MessageStack');
    $CLICSHOPPING_Customer = Registry::get('Customer');
    $CLICSHOPPING_NavigationHistory = Registry::get('NavigationHistory');
    $CLICSHOPPING_Hooks = Registry::get('Hooks');
    $CLICSHOPPING_Template = Registry::get('Template');

// if the customer is not logged on, redirect them to the login page
    if (!$CLICSHOPPING_Customer->isLoggedOn()) {
      $CLICSHOPPING_NavigationHistory->setSnapshot();
      CLICSHOPPING::redirect(null, 'Account&LogIn');
    }

// process the selected shipping method
    if (isset($_POST['action']) && ($_POST['action'] == 'process') && isset($_POST['formid']) && ($_POST['formid'] === $_SESSION['sessiontoken'])) {
      if (!is_null($_POST['comments'])) {
        $_SESSION['comments'] = HTML::sanitize($_POST['comments']);
      }

// load the selected shipping module
      if (!Registry::exists('Shipping')) {
        Registry::set('Shipping', new Shipping());
      }

      $CLICSHOPPING_Shipping = Registry::get('Shipping');

      if (($CLICSHOPPING_Shipping->geCountShippingModules() > 0) || ($_SESSION['free_shipping'] === true)) {
        if ((isset($_POST['shipping'])) && (strpos($_POST['shipping'], '_'))) {
          $_SESSION['shipping'] = $_POST['shipping'];

          $CLICSHOPPING_SM = null;

          if (str_contains($_SESSION['shipping'], '\\')) {
            list($vendor, $app, $module) = explode('\\', $_SESSION['shipping']);
            list($module, $method) = explode('_', $module);

            $module = $vendor . '\\' . $app . '\\' . $module;

            $code = 'Shipping_' . str_replace('\\', '_', $module);

            if (Registry::exists($code)) {
              $CLICSHOPPING_SM = Registry::get($code);
            }
          } else {
            list($module, $method) = explode('_', $_SESSION['shipping']);

            if (is_object($GLOBALS[$module])) {
              $CLICSHOPPING_SM = $GLOBALS[$module];
            }
          }

          if (isset($CLICSHOPPING_SM) || ($_SESSION['shipping'] == 'free_free')) {
            $quote = [];

            if ($_SESSION['shipping'] == 'free_free') {
              $quote[0]['methods'][0]['title'] = CLICSHOPPING::getDef('free_shipping_title');
              $quote[0]['methods'][0]['cost'] = '0';
            } else {
              $quote = $CLICSHOPPING_Shipping->getQuote($method, $module);
            }

            if (isset($quote['error'])) {
              unset($_SESSION['shipping']);
            } else {
              for ($i = 0, $n = count($quote[0]['methods']); $i < $n; $i++) {
                if (isset($quote[0]['methods'][$i]['title'], $quote[0]['methods'][$i]['cost']) && ($quote[0]['methods'][$i]['id'] == $method || $_SESSION['shipping'] == 'free_free')) {
                  $_SESSION['shipping'] = [
                    'id' => $_SESSION['shipping'],
                    'title' => (($_SESSION['free_shipping'] === true) ? $quote[0]['methods'][$i]['title'] : $quote[0]['module'] . (isset($quote[0]['methods'][$i]['title']) && !empty($quote[0]['methods'][0]['title']) ? ' ' . $quote[0]['methods'][$i]['title'] . '' : '')),
                    'cost' => $quote[0]['methods'][$i]['cost']
                  ];

                  $CLICSHOPPING_Hooks->call('Shipping', 'Process');

                  $source_folder = CLICSHOPPING::getConfig('dir_root', 'Shop') . 'Core/Module/Hooks/Shop/CheckoutShipping/';

                  if (is_dir($source_folder)) {
                    $files_get = $CLICSHOPPING_Template->getSpecificFiles($source_folder, 'CheckoutShipping*');

                    if (is_array($files_get)) {
                      foreach ($files_get as $value) {
                        if (!empty($value['name'])) {
                          $CLICSHOPPING_Hooks->call('CheckoutShippingProcess', $value['name']);
                        }
                      }
                    }
                  }

                  CLICSHOPPING::redirect(null, 'Checkout&Billing');
                }
              }
            }
          } else {
            unset($_SESSION['shipping']);
          }
        }
      } else {
        if (defined('SHIPPING_ALLOW_UNDEFINED_ZONES') && (SHIPPING_ALLOW_UNDEFINED_ZONES == 'False')) {
          unset($_SESSION['shipping']);
        } else {
          $_SESSION['shipping'] = false;

          $source_folder = CLICSHOPPING::getConfig('dir_root', 'Shop') . 'Core/Module/Hooks/Shop/CheckoutShipping/';

          if (is_dir($source_folder)) {
            $files_get = $CLICSHOPPING_Template->getSpecificFiles($source_folder, 'CheckoutShipping*');

            if (is_array($files_get)) {
              foreach ($files_get as $value) {
                if (!empty($value['name'])) {
                  $CLICSHOPPING_Hooks->call('CheckoutShippingProcess', $value['name']);
                }
              }
            }
          }

          CLICSHOPPING::redirect(null, 'Checkout&Billing');
        }
      }
    } else {
      $CLICSHOPPING_MessageStack->add(CLICSHOPPING::getDef('error_during_process'), 'danger', 'checkout_shipping');

      CLICSHOPPING::redirect(null, 'Checkout&Shipping');
    }
  }
}