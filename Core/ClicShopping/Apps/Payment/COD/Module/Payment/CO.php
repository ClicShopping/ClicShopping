<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Payment\COD\Module\Payment;

use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

use ClicShopping\Apps\Payment\COD\COD as CodApp;
use ClicShopping\Sites\Common\B2BCommon;
use ClicShopping\OM\Interfaces\PaymentInterface;

/**
 * This class implements the PaymentInterface for the Cash on Delivery (COD) payment module.
 * It provides the functionality to manage the COD payment option within a ClicShopping application.
 *
 * The CO class includes methods to handle payment-specific operations such as validation,
 * module activation and deactivation, order status updates, and more.
 */
class CO implements PaymentInterface
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

    if (!Registry::exists('COD')) {
      Registry::set('COD', new CodApp());
    }

    $this->app = Registry::get('COD');
    $this->app->loadDefinitions('Module/Shop/CO/CO');

    $this->signature = 'cod|' . $this->app->getVersion() . '|1.0';
    $this->api_version = $this->app->getApiVersion();

    $this->code = 'CO';
    $this->title = $this->app->getDef('module_cod_title');
    $this->public_title = $this->app->getDef('module_cod_public_title');
    $this->description = $this->app->getDef('module_cod_public_description');

// Activation module du paiement selon les groupes B2B
    if (\defined('CLICSHOPPING_APP_COD_CO_STATUS')) {
      if ($CLICSHOPPING_Customer->getCustomersGroupID() != 0) {
        if (B2BCommon::getPaymentUnallowed($this->code)) {
          if (CLICSHOPPING_APP_COD_CO_STATUS == 'True') {
            $this->enabled = true;
          } else {
            $this->enabled = false;
          }
        }
      } else {
        if (CLICSHOPPING_APP_COD_CO_NO_AUTHORIZE == 'True' && $CLICSHOPPING_Customer->getCustomersGroupID() == 0) {
          if ($CLICSHOPPING_Customer->getCustomersGroupID() == 0) {
            if (CLICSHOPPING_APP_COD_CO_STATUS == 'True') {
              $this->enabled = true;
            } else {
              $this->enabled = false;
            }
          }
        }
      }

      if ((int)CLICSHOPPING_APP_COD_CO_PREPARE_ORDER_STATUS_ID > 0) {
        $this->order_status = CLICSHOPPING_APP_COD_CO_PREPARE_ORDER_STATUS_ID;
      }

      if ($this->enabled === true) {
        if (isset($CLICSHOPPING_Order) && \is_object($CLICSHOPPING_Order)) {
          $this->update_status();
        }
      }

      $this->sort_order = \defined('CLICSHOPPING_APP_COD_CO_SORT_ORDER') ? CLICSHOPPING_APP_COD_CO_SORT_ORDER : 0;
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

    if (($this->enabled === true) && ((int)CLICSHOPPING_APP_COD_CO_ZONE > 0)) {
      $check_flag = false;

      $Qcheck = $this->app->db->get('zones_to_geo_zones', 'zone_id', [
        'geo_zone_id' => CLICSHOPPING_APP_COD_CO_ZONE,
        'zone_country_id' => $CLICSHOPPING_Order->billing['country']['id']
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

// disable the module if the order only contains virtual products
    if ($this->enabled === true) {
      if ($CLICSHOPPING_Order->content_type == 'virtual') {
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
   * Retrieves the selection details for the module, including the module ID and title.
   *
   * @return array An associative array containing the module's ID and title.
   */
  public function selection()
  {
    $CLICSHOPPING_Template = Registry::get('Template');

    if (CLICSHOPPING_APP_COD_CO_LOGO) {
      if (!empty(CLICSHOPPING_APP_COD_CO_LOGO) && is_file($CLICSHOPPING_Template->getDirectoryTemplateImages() . 'logos/payment/' . CLICSHOPPING_APP_COD_CO_LOGO)) {
        $this->public_title = $this->public_title . '&nbsp;&nbsp;&nbsp;' . HTML::image($CLICSHOPPING_Template->getDirectoryTemplateImages() . 'logos/payment/' . CLICSHOPPING_APP_COD_CO_LOGO);
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
   * Performs necessary checks or operations before order confirmation.
   *
   * @return bool Always returns false.
   */
  public function pre_confirmation_check()
  {
    return false;
  }

  /**
   * Handles the confirmation process logic.
   *
   * @return bool Returns false to indicate the confirmation process did not succeed.
   */
  public function confirmation()
  {
    return false;
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
   * Checks if the constant 'CLICSHOPPING_APP_COD_CO_STATUS' is defined and if its value is not an empty string after trimming.
   *
   * @return bool Returns true if the constant is defined and has a non-empty value, otherwise false.
   */
  public function check()
  {
    return \defined('CLICSHOPPING_APP_COD_CO_STATUS') && (trim(CLICSHOPPING_APP_COD_CO_STATUS) != '');
  }

  /**
   * Initiates the installation process and redirects to the configuration page for the specified module.
   *
   * @return void This method does not return a value.
   */
  public function install()
  {
    $this->app->redirect('Configure&Install&module=COD');
  }

  /**
   * Redirects to the uninstall configuration page for the specified module.
   *
   * @return void
   */
  public function remove()
  {
    $this->app->redirect('Configure&Uninstall&module=COD');
  }

  /**
   * Retrieves an array of configuration keys.
   *
   * @return array An array containing configuration key strings.
   */
  public function keys()
  {
    return array('CLICSHOPPING_APP_COD_CO_SORT_ORDER');
  }
}