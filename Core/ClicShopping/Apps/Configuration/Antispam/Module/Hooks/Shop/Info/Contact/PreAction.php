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
   * Honeypot check: the CSS-hidden field "invisible_clicshopping" must stay empty.
   * A real user never sees it (display:none) and leaves it blank; a bot that fills
   * every field populates it, which flags the submission as spam.
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

    // Layered invisible check: bot User-Agent, honeypot filled, or submitted too fast.
    if (CLICSHOPPING_APP_ANTISPAM_IN_CONTACT == 'True' && AntiSpam::checkInvisibleAntiSpam() === true) {
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
   * Server-side Google reCAPTCHA validation (v2/v3) for this form.
   *
   * @return bool Returns true if the reCAPTCHA verification fails, otherwise false.
   */
  private static function checkGoogleRecaptcha(): bool
  {
    if (!\defined('CLICSHOPPING_APP_ANTISPAM_GG_STATUS') || CLICSHOPPING_APP_ANTISPAM_GG_STATUS == 'False') {
      return false;
    }

    if (!\defined('CLICSHOPPING_APP_ANTISPAM_GG_CONTACT') || CLICSHOPPING_APP_ANTISPAM_GG_CONTACT == 'False') {
      return false;
    }

    return AntiSpam::checkRecaptcha('contact');
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
      $error_recaptcha = static::checkGoogleRecaptcha();

      if ($error_invisible === true || $error_numeric === true || $error_recaptcha === true) {

        $error = true;
      }

      if ($error === true) {
        $this->messageStack->add(CLICSHOPPING::getDef('text_error_antispam'), 'error');
        CLICSHOPPING::redirect(null, 'Info&Contact');
      }
    }
  }
}