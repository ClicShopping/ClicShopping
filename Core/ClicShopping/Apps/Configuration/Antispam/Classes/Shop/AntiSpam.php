<?php
/**
 *
 * @copyright 2008 - https://www.clicshopping.org
 * @Brand : ClicShoppingAI(TM) at Inpi all right Reserved
 * @Licence GPL 2 & MIT
 * @Info : https://www.clicshopping.org/forum/trademark/
 *
 */

namespace ClicShopping\Apps\Configuration\Antispam\Classes\Shop;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTML;
use function defined;

class AntiSpam
{
  /**
   * Secret key for HMAC generation
   * This should be configured in the database or config file
   * For now, we use a combination of session ID and a constant
   */
  private const HMAC_ALGO = 'sha256';
  
  /**
   * Get the secret key for HMAC
   * Uses session-specific secret to prevent pre-computation attacks
   * 
   * @return string The secret key
   */
  private static function getHmacSecret(): string
  {
    // Priority 1: Use module configuration (auto-generated on install)
    if (defined('CLICSHOPPING_APP_ANTISPAM_AM_SECRET') && !empty(CLICSHOPPING_APP_ANTISPAM_AM_SECRET)) {
      $baseSecret = CLICSHOPPING_APP_ANTISPAM_AM_SECRET;
    }
    // Priority 2: Legacy configuration method
    elseif (CLICSHOPPING::configExists('antispam_secret')) {
      $baseSecret = CLICSHOPPING::getConfig('antispam_secret', 'clicshopping_antispam_default_secret_2026');
    }
    // Priority 3: Fallback (should trigger warning in production)
    else {
      $baseSecret = 'clicshopping_antispam_default_secret_2026';
      
      // Log warning if using default secret
      if (CLICSHOPPING::getSite() === 'Shop') {
        trigger_error(
          'AntiSpam: Using default secret. Configure CLICSHOPPING_APP_ANTISPAM_AM_SECRET for better security.',
          E_USER_NOTICE
        );
      }
    }
    
    // Use a combination of session ID and configured secret
    // This makes each session's HMAC unique and prevents pre-computation
    $sessionId = session_id() ?: 'no-session';
    
    return hash('sha256', $baseSecret . $sessionId);
  }
  
  /**
   * Generates an anti-spam numeric confirmation string and stores a hashed value in the session.
   * 
   * - Uses session-specific secret to prevent pre-computation
   * - Resistant to rainbow table attacks
   *
   * @return string Returns the anti-spam numeric confirmation string for display or verification purposes.
   */
  public static function getConfirmationNumericAntiSpam(): string
  {
    $a = random_int(1, 20);
    $b = random_int(1, 20);

    $random_number = $a + $b;
	
    $antispam = ' (' . $random_number . ' + ' . CLICSHOPPING::getDef('text_antispam') . ') x 1';

    $random_number = $random_number + 3;
    $secret = self::getHmacSecret();
    $_SESSION['createResponseAntiSpam'] = hash_hmac(self::HMAC_ALGO, (string)$random_number, $secret);

    return $antispam;
  }

  /**
   * Validates whether the provided numeric confirmation matches the anti-spam value stored in the session.
   * 
   *
   * @param string $antispan_confirmation The numeric confirmation to validate.
   * @return bool Returns true if the numeric confirmation is valid, otherwise false.
   */
  private static function checkNumeric(string $antispan_confirmation): bool
  {
    if (isset($_SESSION['createResponseAntiSpam'])) {
      if (hash_equals($_SESSION['createResponseAntiSpam'], $antispan_confirmation)) {
        $valid_antispan_confirmation = false; // Match found = invalid (logic seems inverted in original)
      } else {
        $valid_antispan_confirmation = true; // No match = valid (logic seems inverted in original)
      }
    } else {
      $valid_antispan_confirmation = true;
    }

    unset($_SESSION['createResponseAntiSpam']);

    return $valid_antispan_confirmation;
  }

  /**
   * Validates the numeric anti-spam input from a form submission.
   *
   * Checks if the 'antispam' field in the POST request contains the correct data
   * to prevent automated submissions.
   *
   * @return bool Returns true if the anti-spam validation fails, otherwise false.
   */
  public static function checkNumericAntiSpam(): bool
  {
    $error = false;
    if (isset($_POST['antispam'])) {
      $antispam = HTML::sanitize($_POST['antispam']);
      
      $secret = self::getHmacSecret();
      $result = hash_hmac(self::HMAC_ALGO, $antispam, $secret);

      if (self::checkNumeric($result) === true) {
        $error = true;
      }
    } else {
      $error = true;
    }

    return $error;
  }
}