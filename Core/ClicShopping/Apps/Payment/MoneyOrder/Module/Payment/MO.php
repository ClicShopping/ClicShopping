<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Payment\MoneyOrder\Module\Payment;

use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

use ClicShopping\Apps\Payment\MoneyOrder\MoneyOrder as MoneyOrderApp;
use ClicShopping\Sites\Common\B2BCommon;
use ClicShopping\OM\Interfaces\PaymentInterface;

/**
 * The MO class represents the Money Order payment module.
 * It implements the PaymentInterface for compatibility with the ClicShopping payment system.
 * This class contains the methods necessary to interact with Money Order payment operations such as setup, status checks, and configurations.
 */
class MO implements PaymentInterface
{
  public string $code;
  public $title;
  public $description;
  public $enabled = false;
  public mixed $app;
  public $title_selection;
  public $signature;
  public $public_title;
  public int|null $sort_order = 0;
  protected $api_version;
  public $group;

  /**
   * Constructor method initializes the Cash on Delivery (COD) module.
   * Sets up the module attributes such as code, title, description, and status.
   * Loads necessary resources and configuration options from the registry.
   * Determines module availability based on customer group and other conditions.
   * Configures order status and sort order specific to the module.
   *
   * @return void
   */
  public function __construct()
  {
    $CLICSHOPPING_Customer = Registry::get('Customer');

    if (Registry::exists('Order')) {
      $CLICSHOPPING_Order = Registry::get('Order');
    }

    if (!Registry::exists('MoneyOrder')) {
      Registry::set('MoneyOrder', new MoneyOrderApp());
    }

    $this->app = Registry::get('MoneyOrder');
    $this->app->loadDefinitions('Module/Shop/MO/MO');

    $this->signature = 'MoneyOrder|' . $this->app->getVersion() . '|1.0';
    $this->api_version = $this->app->getApiVersion();

    $this->code = 'MO';
    $this->title = $this->app->getDef('module_moneyorder_title');
    $this->public_title = $this->app->getDef('module_moneyorder_public_title');

// Activation module du paiement selon les groupes B2B
    if (\defined('CLICSHOPPING_APP_MONEYORDER_MO_STATUS')) {
      if ($CLICSHOPPING_Customer->getCustomersGroupID() != 0) {
        if (B2BCommon::getPaymentUnallowed($this->code)) {
          if (CLICSHOPPING_APP_MONEYORDER_MO_STATUS == 'True') {
            $this->enabled = true;
          } else {
            $this->enabled = false;
          }
        }
      } else {
        if (CLICSHOPPING_APP_MONEYORDER_MO_NO_AUTHORIZE == 'True' && $CLICSHOPPING_Customer->getCustomersGroupID() == 0) {
          if ($CLICSHOPPING_Customer->getCustomersGroupID() == 0) {
            if (CLICSHOPPING_APP_MONEYORDER_MO_STATUS == 'True') {
              $this->enabled = true;
            } else {
              $this->enabled = false;
            }
          }
        }
      }

      if ((int)CLICSHOPPING_APP_MONEYORDER_MO_PREPARE_ORDER_STATUS_ID > 0) {
          $this->order_status = CLICSHOPPING_APP_MONEYORDER_MO_PREPARE_ORDER_STATUS_ID;
      }

      if ($this->enabled === true) {
        if (isset($CLICSHOPPING_Order) && \is_object($CLICSHOPPING_Order)) {
          $this->update_status();
        }
      }

      $this->sort_order = \defined('CLICSHOPPING_APP_MONEYORDER_MO_SORT_ORDER') ? CLICSHOPPING_APP_MONEYORDER_MO_SORT_ORDER : 0;
    }
  }


  /**
   * Updates the status of the module based on geographic zones and order content type.
   * The method checks if the module is enabled and verifies its compatibility with
   * the specified geographic zones and whether the order contains only virtual products.
   *
   * @return void
   */
  public function update_status()
  {
    $CLICSHOPPING_Order = Registry::get('Order');

    if (($this->enabled === true) && ((int)CLICSHOPPING_APP_MONEYORDER_MO_ZONE > 0)) {
      $check_flag = false;

      $Qcheck = $this->app->db->get('zones_to_geo_zones', 'zone_id', ['geo_zone_id' => CLICSHOPPING_APP_MONEYORDER_MO_ZONE,
        'zone_country_id' => $CLICSHOPPING_Order->delivery['country']['id']
      ],
        'zone_id'
      );

      while ($Qcheck->fetch()) {
        if (($Qcheck->valueInt('zone_id') < 1) || ($Qcheck->valueInt('zone_id') === $CLICSHOPPING_Order->delivery['zone_id'])) {
          $check_flag = true;
          break;
        }
      }

      if ($check_flag === false) {
        $this->enabled = false;
      }
    }

  }

  /**
   * Validates JavaScript input or related functionality.
   *
   * @return bool Returns false indicating the validation process.
   */
  public function javascript_validation()
  {
    return false;
  }

  /**
   * Prepares and formats the payment selection module for display.
   *
   * @return array An associative array containing the payment module's ID and formatted public title.
   */
  public function selection()
  {
    $CLICSHOPPING_Template = Registry::get('Template');

    if (CLICSHOPPING_APP_MONEYORDER_MO_LOGO) {
      if (!empty(CLICSHOPPING_APP_MONEYORDER_MO_LOGO) && is_file($CLICSHOPPING_Template->getDirectoryTemplateImages() . 'logos/payment/' . CLICSHOPPING_APP_MONEYORDER_MO_LOGO)) {
        $this->public_title = $this->public_title . '&nbsp;&nbsp;&nbsp;' . HTML::image($CLICSHOPPING_Template->getDirectoryTemplateImages() . 'logos/payment/' . CLICSHOPPING_APP_MONEYORDER_MO_LOGO);
      } else {
        $this->public_title = $this->public_title;
      }
    }

    return [
      'id' => $this->app->vendor . '\\' . $this->app->code . '\\' . $this->code,
      'module' => $this->public_title
    ];
  }

  /**
   *
   * @return bool Returns false indicating the pre-confirmation check did not pass or is not required.
   */
  public function pre_confirmation_check()
  {
    return false;
  }

  /**
   * Prepares and returns the confirmation details for the money order payment module.
   *
   * @return array Contains the confirmation title including optional logo and payment details.
   */
  public function confirmation()
  {
    $CLICSHOPPING_Template = Registry::get('Template');
    $this->title_selection = '';

    if (\defined('CLICSHOPPING_APP_MONEYORDER_MO_LOGO') && !empty(CLICSHOPPING_APP_MONEYORDER_MO_LOGO)) {
      $this->title_selection .= HTML::image($CLICSHOPPING_Template->getDirectoryTemplateImages() . 'logos/payment/' . CLICSHOPPING_APP_MONEYORDER_MO_LOGO);
    }

    $this->title_selection .= '<br />' . $this->app->getDef('module_moneyorder_text', ['pay_to' => CLICSHOPPING_APP_MONEYORDER_MO_PAY_TO,
          'store_name_addres' => STORE_NAME_ADDRESS,
          'store_name' => STORE_NAME]
      );

    return array('title' => $this->title_selection);
  }

  /**
   * Processes the button and determines its action.
   *
   * @return bool Returns false indicating the button processing did not complete successfully.
   */
  public function process_button()
  {
    return false;
  }

  /**
   * Executes preliminary processing logic before the main process.
   *
   * @return bool Returns false to indicate no further processing is required.
   */
  public function before_process()
  {
    return false;
  }

  /**
   * Executes finalization logic after the main process has completed.
   *
   * @return bool Returns false to indicate the process did not complete successfully or requires no additional handling.
   */
  public function after_process()
  {
    return false;
  }

  /**
   * Retrieves the error state of the process.
   *
   * @return bool Returns false when no error is present.
   */
  public function get_error()
  {
    return false;
  }

  /**
   * Checks if the constant 'CLICSHOPPING_APP_MONEYORDER_MO_STATUS' is defined and not an empty string after trimming.
   *
   * @return bool Returns true if the constant is defined and not empty; otherwise, false.
   */
  public function check()
  {
    return \defined('CLICSHOPPING_APP_MONEYORDER_MO_STATUS') && (trim(CLICSHOPPING_APP_MONEYORDER_MO_STATUS) != '');
  }

  /**
   * Initiates the installation process and redirects to the configuration page for the specified module.
   *
   * @return void This method does not return a value.
   */
  public function install()
  {
    $this->app->redirect('Configure&Install&module=MoneyOrder');
  }

  /**
   * Redirects the application to the uninstall configuration page for the MoneyOrder module.
   *
   * @return void
   */
  public function remove()
  {
    $this->app->redirect('Configure&Uninstall&module=MoneyOrder');
  }

  /**
   * Retrieves an array of configuration keys related to the Money Order application.
   *
   * @return array An array containing configuration keys.
   */
  public function keys()
  {
    return array('CLICSHOPPING_APP_MONEYORDER_MO_SORT_ORDER');
  }
}