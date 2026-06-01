<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\Antispam\Module\Hooks\Shop\Customers\ReviewsWrite;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

use ClicShopping\Apps\Configuration\Antispam\Antispam as AntispamApp;
use ClicShopping\Apps\Configuration\Antispam\Classes\Shop\AntiSpam;

class PreAction implements \ClicShopping\OM\Modules\HooksInterface
{
  public mixed $app;
  public mixed $messageStack;

  /**
   * Constructor method for initializing the class.
   * Ensures the 'Antispam' object is registered in the application registry
   * and assigns necessary dependencies for the class.
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
   * Checks the status of the invisible antispam feature in the application
   * for reviews submission and verifies required conditions.
   *
   * @return bool Returns true if there is an error (e.g., missing or invalid
   *              antispam field); otherwise, returns false.
   */
  private static function checkInvisibleAntispam(): bool
  {
    $error = false;

    if (!\defined('CLICSHOPPING_APP_ANTISPAM_IN_REVIEWS_WRITE') || CLICSHOPPING_APP_ANTISPAM_IN_REVIEWS_WRITE == 'False') {
      return false;
    }

    if (!\defined('CLICSHOPPING_APP_ANTISPAM_IN_STATUS') || CLICSHOPPING_APP_ANTISPAM_IN_STATUS == 'False') {
      return false;
    }

    if (CLICSHOPPING_APP_ANTISPAM_IN_REVIEWS_WRITE == 'True' && AntiSpam::checkInvisibleAntiSpam() === true) {
      $error = true;
    }

    return $error;
  }

  /**
   * Checks the numeric antispam status and validates related configurations.
   *
   * @return bool True if the numeric antispam check passes and the relevant configurations are enabled; false otherwise.
   */
  private static function checkNumericAntispam(): bool
  {
    if (!\defined('CLICSHOPPING_APP_ANTISPAM_AM_REVIEWS_WRITE') || CLICSHOPPING_APP_ANTISPAM_AM_REVIEWS_WRITE == 'False') {
      return false;
    }

    if (!\defined('CLICSHOPPING_APP_ANTISPAM_AM_STATUS') || CLICSHOPPING_APP_ANTISPAM_AM_STATUS == 'False') {
      return false;
    }

    $error = AntiSpam::checkNumericAntiSpam();

    return $error;
  }
/**
   * Server-side Google reCAPTCHA validation (v2/v3) for this form.
   *
   * @return bool Returns true if the reCAPTCHA verification fails, otherwise false.
   */
  private static function checkGoogleRecaptcha(): bool
  {
    if (!\defined('CLICSHOPPING_APP_ANTISPAM_GG_STATUS') || CLICSHOPPING_APP_ANTISPAM_GG_STATUS == 'False') {
      return false;
    }

    if (!\defined('CLICSHOPPING_APP_ANTISPAM_GG_REVIEWS_WRITE') || CLICSHOPPING_APP_ANTISPAM_GG_REVIEWS_WRITE == 'False') {
      return false;
    }

    return AntiSpam::checkRecaptcha('reviews_write');
  }

  /**
   * Executes the antispam checks for the current process. Checks both invisible
   * and numeric antispam mechanisms when specific GET parameters are present.
   * If an error is detected, an error message is added to the message stack and
   * the user is redirected to the appropriate URL.
   *
   * @return bool Returns false if the antispam application is not enabled.
   *              Otherwise, performs the necessary checks and may redirect based on the result.
   */
  public function execute()
  {
    if (!\defined('CLICSHOPPING_APP_ANTISPAM_STATUS') || CLICSHOPPING_APP_ANTISPAM_STATUS == 'False') {
      return false;
    }

    if (isset($_GET['Products'], $_GET['ReviewsWrite'], $_GET['Process'])) {
      $error = false;

      $error_invisible = static::checkInvisibleAntispam();
      $error_numeric = static::checkNumericAntispam();
      $error_recaptcha = static::checkGoogleRecaptcha();

      if ($error_invisible === true || $error_numeric === true || $error_recaptcha === true) {
        $error = true;
      }

      if ($error === true) {
        $id = HTML::sanitize($_GET['products_id']);
        $this->messageStack->add(CLICSHOPPING::getDef('text_error_antispam'), 'error');
        CLICSHOPPING::redirect(null, 'Products&ReviewsWrite&products_id=' . $id);
      }
    }
  }
}