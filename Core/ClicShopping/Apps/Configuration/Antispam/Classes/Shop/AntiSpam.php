<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\Antispam\Classes\Shop;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTML;
use ClicShopping\OM\HTTP;
use ClicShopping\Sites\Shop\BotDetector;
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
   * Honeypot field name. It looks like a plausible field so naive bots fill it,
   * while real users never see it (hidden via the .hiddenRecaptcha CSS rule).
   */
  private const HONEYPOT_FIELD = 'homepage';

  /**
   * Session key holding the form render time (server-side time-trap, unforgeable).
   */
  private const TIMETRAP_SESSION_KEY = 'invisibleAntiSpamStartTime';

  /**
   * Minimum number of seconds a human realistically needs to fill a form.
   * A faster submission is treated as a bot. Overridable via configuration.
   */
  private const MIN_FILL_SECONDS = 3;
  
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

  /**
   * Renders the invisible anti-spam field to embed in a form and arms the time-trap.
   *  - Outputs a CSS-hidden honeypot input that must stay empty;
   *  - Records the render time server-side in the session (unforgeable time-trap).
   *
   * @return string The HTML for the hidden honeypot field.
   */
  public static function getInvisibleAntiSpamFields(): string
  {
    // Server-side time-trap: remember when the form was rendered. Nothing exposed to the client.
    $_SESSION[self::TIMETRAP_SESSION_KEY] = time();

    // Honeypot: hidden from humans, plausible name so naive bots fill it. Autofill disabled.
    return '<input type="text" name="' . self::HONEYPOT_FIELD . '" value=""'
      . ' tabindex="-1" autocomplete="off" aria-hidden="true" class="hiddenRecaptcha" />' . "\n";
  }

  /**
   * Layered invisible anti-spam validation for a form submission. Returns true when the
   * request looks like a bot, combining three independent signals:
   *  1. BotDetector  — empty or known-crawler User-Agent;
   *  2. Honeypot     — the hidden field was filled in;
   *  3. Time-trap    — no recorded render time, or submitted faster than a human could.
   *
   * @return bool Returns true if the submission is considered spam, otherwise false.
   */
  public static function checkInvisibleAntiSpam(): bool
  {
    // Layer 1 — request level: empty or known-crawler User-Agent.
    if ((new BotDetector())->isBot()) {
      return true;
    }

    // Layer 2 — honeypot: a real user never fills the hidden field.
    if (!empty($_POST[self::HONEYPOT_FIELD])) {
      return true;
    }

    // Layer 3 — server-side time-trap: reject forms submitted faster than a human could.
    $started = $_SESSION[self::TIMETRAP_SESSION_KEY] ?? null;
    unset($_SESSION[self::TIMETRAP_SESSION_KEY]);

    if ($started === null) {
      return true; // form was not rendered through the expected flow
    }

    $min = \defined('CLICSHOPPING_APP_ANTISPAM_AM_MIN_FILL_SECONDS')
      ? (int)CLICSHOPPING_APP_ANTISPAM_AM_MIN_FILL_SECONDS
      : self::MIN_FILL_SECONDS;

    return (time() - (int)$started) < $min;
  }

  /**
   * Default Google reCAPTCHA v3 score threshold. Submissions scoring below this are
   * treated as bots (Google returns a score from 0.0 = bot to 1.0 = human).
   */
  private const RECAPTCHA_SCORE_THRESHOLD = 0.5;

  /**
   * Renders the Google reCAPTCHA fields for a form, honouring the configured version.
   *  - v3: loads the API with the site key, adds a hidden token field and refreshes
   *        the token periodically (tokens expire after ~2 minutes);
   *  - v2: loads the classic checkbox widget (the token field is injected by Google).
   *
   * @param string $action The reCAPTCHA v3 action name for this form (e.g. "contact").
   * @return string The HTML/JS to embed, or an empty string when no site key is configured.
   */
  public static function getRecaptchaFields(string $action): string
  {
    if (!\defined('CLICSHOPPING_APP_ANTISPAM_GG_SITE_KEY') || empty(CLICSHOPPING_APP_ANTISPAM_GG_SITE_KEY)) {
      return '';
    }

    $site_key = CLICSHOPPING_APP_ANTISPAM_GG_SITE_KEY;
    $version = \defined('CLICSHOPPING_APP_ANTISPAM_GG_VERSION') ? CLICSHOPPING_APP_ANTISPAM_GG_VERSION : 'v3';

    if ($version === 'v2') {
      return '<script src="https://www.google.com/recaptcha/api.js" async defer></script>' . "\n"
        . '<div class="g-recaptcha" data-sitekey="' . HTML::outputProtected($site_key) . '"></div>' . "\n";
    }

    // reCAPTCHA v3 — invisible and score-based. Sanitize the action (allowed charset only).
    $action = preg_replace('/[^A-Za-z0-9_\/]/', '', $action) ?: 'submit';
    $field_id = 'gRecaptchaResponse_' . preg_replace('/[^A-Za-z0-9_]/', '_', $action);
    $key_js = HTML::outputProtected($site_key);

    $html = '<script src="https://www.google.com/recaptcha/api.js?render=' . rawurlencode($site_key) . '"></script>' . "\n";
    $html .= '<input type="hidden" name="g-recaptcha-response" id="' . $field_id . '" />' . "\n";
    $html .= '<script>(function(){function r(){grecaptcha.execute("' . $key_js . '",{action:"' . $action . '"})'
      . '.then(function(t){var e=document.getElementById("' . $field_id . '");if(e){e.value=t;}});}'
      . 'grecaptcha.ready(function(){r();setInterval(r,100000);});})();</script>' . "\n";

    return $html;
  }

  /**
   * Server-side validation of a Google reCAPTCHA response via the siteverify endpoint.
   * Returns true when the request should be treated as spam.
   *
   * Behaviour:
   *  - no secret key configured  -> cannot verify, fail-open (returns false);
   *  - no token submitted        -> bot (returns true);
   *  - network/Google failure    -> fail-open (returns false), other layers still apply;
   *  - token rejected by Google  -> bot (returns true);
   *  - v3: enforces action match (when provided) and the configured score threshold.
   *
   * @param string $action The expected v3 action name, or '' to skip the action check.
   * @return bool Returns true if the submission is considered spam, otherwise false.
   */
  public static function checkRecaptcha(string $action = ''): bool
  {
    if (!\defined('CLICSHOPPING_APP_ANTISPAM_GG_SECRET_KEY') || empty(CLICSHOPPING_APP_ANTISPAM_GG_SECRET_KEY)) {
      return false;
    }

    $token = isset($_POST['g-recaptcha-response']) ? (string)$_POST['g-recaptcha-response'] : '';

    if ($token === '') {
      return true;
    }

    $url = 'https://www.google.com/recaptcha/api/siteverify'
      . '?secret=' . rawurlencode(CLICSHOPPING_APP_ANTISPAM_GG_SECRET_KEY)
      . '&response=' . rawurlencode($token)
      . '&remoteip=' . rawurlencode(HTTP::getIpAddress());

    $result = HTTP::getResponse(['url' => $url, 'method' => 'get', 'format' => 'json', 'timeout' => 5], ['www.google.com']);

    if (!\is_array($result)) {
      return false; // verification unreachable -> fail-open
    }

    if (empty($result['success'])) {
      return true; // token rejected / forged
    }

    $version = \defined('CLICSHOPPING_APP_ANTISPAM_GG_VERSION') ? CLICSHOPPING_APP_ANTISPAM_GG_VERSION : 'v3';

    if ($version === 'v3') {
      if ($action !== '' && isset($result['action']) && $result['action'] !== $action) {
        return true;
      }

      $threshold = \defined('CLICSHOPPING_APP_ANTISPAM_GG_SCORE_THRESHOLD') && CLICSHOPPING_APP_ANTISPAM_GG_SCORE_THRESHOLD !== ''
        ? (float)CLICSHOPPING_APP_ANTISPAM_GG_SCORE_THRESHOLD
        : self::RECAPTCHA_SCORE_THRESHOLD;

      return (float)($result['score'] ?? 0) < $threshold;
    }

    return false; // v2 success
  }
}