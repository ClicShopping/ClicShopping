<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\Antispam\Module\Hooks\Shop\Info\Contact;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;

use ClicShopping\Apps\Configuration\Antispam\Antispam as AntispamApp;
use ClicShopping\Apps\Configuration\Antispam\Classes\Shop\AntiSpam;

class PreAction implements \ClicShopping\OM\Modules\HooksInterface
{
  public mixed $app;
  public mixed $messageStack;

  /**
   * Initializes the Antispam application by checking and setting its registry entry.
   * Retrieves and assigns instances of the Antispam application and message stack.
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
   * Checks the configuration and validates the presence of an invisible antispam token in the request.
   *
   * @return bool Returns true if a violation is detected or conditions require it, otherwise false.
   */
  private static function checkInvisibleAntispam(): bool
  {
    $error = false;

    if (!\defined('CLICSHOPPING_APP_ANTISPAM_IN_CONTACT') || CLICSHOPPING_APP_ANTISPAM_IN_CONTACT == 'False') {
      return false;
    }

    if (!\defined('CLICSHOPPING_APP_ANTISPAM_IN_STATUS') || CLICSHOPPING_APP_ANTISPAM_IN_STATUS == 'False') {
      return false;
    }

    if (CLICSHOPPING_APP_ANTISPAM_IN_CONTACT == 'True' && !isset($_POST['invisible_clicshopping'])) {
      $error = true;
    }

    return $error;
  }

  /**
   * Checks the numeric antispam configuration and validates the numeric antispam mechanism.
   *
   * @return bool Returns true if the numeric antispam check passes and relevant configurations are enabled; otherwise, false.
   */
  private static function checkNumericAntispam(): bool
  {
    if (!\defined('CLICSHOPPING_APP_ANTISPAM_AM_CONTACT') || CLICSHOPPING_APP_ANTISPAM_AM_CONTACT == 'False') {
      return false;
    }

    if (!\defined('CLICSHOPPING_APP_ANTISPAM_AM_STATUS') || CLICSHOPPING_APP_ANTISPAM_AM_STATUS == 'False') {
      return false;
    }

    $error = AntiSpam::checkNumericAntiSpam();

    return $error;
  }

  /**
   * Executes the antispam process by validating requests against invisible and numeric antispam checks.
   * If any of the checks fail, an error is added to the message stack, and the user is redirected.
   *
   * @return bool Returns false if the "CLICSHOPPING_APP_ANTISPAM_STATUS" constant is not defined or set to 'False'.
   */
  public function execute()
  {
    if (!\defined('CLICSHOPPING_APP_ANTISPAM_STATUS') || CLICSHOPPING_APP_ANTISPAM_STATUS == 'False') {
      return false;
    }

    if (isset($_GET['Info'], $_GET['Contact'], $_GET['Process'])) {
      $error = false;

      $error_invisible = static::checkInvisibleAntispam();
      $error_numeric = static::checkNumericAntispam();

      if ($error_invisible === true || $error_numeric === true) {

        $error = true;
      }

      if ($error === true) {
        $this->messageStack->add(CLICSHOPPING::getDef('text_error_antispam'), 'error');
        CLICSHOPPING::redirect(null, 'Info&Contact');
      }
    }
  }
}