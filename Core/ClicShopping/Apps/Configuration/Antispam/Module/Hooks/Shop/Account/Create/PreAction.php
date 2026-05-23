<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\Antispam\Module\Hooks\Shop\Account\Create;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;

use ClicShopping\Apps\Configuration\Antispam\Antispam as AntispamApp;
use ClicShopping\Apps\Configuration\Antispam\Classes\Shop\AntiSpam;

class PreAction implements \ClicShopping\OM\Modules\HooksInterface
{
  public mixed $app;
  public mixed $messageStack;

  /**
   * Constructor method for initializing the Antispam application.
   * Ensures the Antispam application is registered in the registry and retrieves required dependencies.
   *
   * @return void
   */
  public function __construct()
  {
    if (!Registry::exists('Antispam')) {
      Registry::set('Antispam', new AntispamApp());
    }

    $this->app = Registry::get('Antispam');
    $this->messageStack = Registry::get('MessageStack');
  }

  /**
   * Checks the configuration and conditions for the invisible antispam system in the context of account creation.
   *
   * @return bool Returns true if an error condition is met related to the invisible antispam system, otherwise returns false.
   */
  private static function checkInvisibleAntispam(): bool
  {
    $error = false;

    if (!\defined('CLICSHOPPING_APP_ANTISPAM_IN_CREATE_ACCOUNT') || CLICSHOPPING_APP_ANTISPAM_IN_CREATE_ACCOUNT == 'False') {
      return false;
    }

    if (!\defined('CLICSHOPPING_APP_ANTISPAM_IN_STATUS') || CLICSHOPPING_APP_ANTISPAM_IN_STATUS == 'False') {
      return false;
    }

    if (CLICSHOPPING_APP_ANTISPAM_IN_CREATE_ACCOUNT == 'True' && !isset($_POST['invisible_clicshopping'])) {
      $error = true;
    }

    return $error;
  }

  /**
   * Checks if numeric antispam validations are enabled and executes the antispam check.
   *
   * @return bool Returns true if numeric antispam validation passes, false otherwise.
   */
  private static function checkNumericAntispam(): bool
  {
    if (!\defined('CLICSHOPPING_APP_ANTISPAM_AM_CREATE_ACCOUNT') || CLICSHOPPING_APP_ANTISPAM_AM_CREATE_ACCOUNT == 'False') {
      return false;
    }

    if (!\defined('CLICSHOPPING_APP_ANTISPAM_AM_STATUS') || CLICSHOPPING_APP_ANTISPAM_AM_STATUS == 'False') {
      return false;
    }

    $error = AntiSpam::checkNumericAntiSpam();

    return $error;
  }

  /**
   * Executes the antispam validation process.
   *
   * This method checks for the status of the Antispam application and ensures
   * its requirements are met. It performs two types of antispam checks: invisible
   * and numeric. If any of these checks fail, an error is logged to the message
   * stack and the user is redirected to the account creation page.
   *
   * @return bool Returns false if the Antispam application is inactive or if
   *              the necessary parameters are not set. Otherwise, performs
   *              validation checks.
   */
  public function execute()
  {
    if (!\defined('CLICSHOPPING_APP_ANTISPAM_STATUS') || CLICSHOPPING_APP_ANTISPAM_STATUS == 'False') {
      return false;
    }

    if (isset($_GET['Account'], $_GET['Create'], $_GET['Process'])) {
      $error = false;

      $error_invisible = static::checkInvisibleAntispam();
      $error_numeric = static::checkNumericAntispam();

      if ($error_invisible === true || $error_numeric === true) {
        $error = true;
      }

      if ($error === true) {
        $this->messageStack->add(CLICSHOPPING::getDef('text_error_antispam'), 'error');
        CLICSHOPPING::redirect(null, 'Account&Create');
      }
    }
  }
}