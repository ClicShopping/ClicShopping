<?php
/**
 *
 * @copyright 2008 - https://www.clicshopping.org
 * @Brand : ClicShoppingAI(TM) at Inpi all right Reserved
 * @Licence GPL 2 & MIT
 * @Info : https://www.clicshopping.org/forum/trademark/
 *
 */

namespace ClicShopping\OM\Domains;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;

/**
 * Abstract class SessionAbstract
 *
 * Provides abstraction for session management functionalities, such as creating, verifying,
 * recreating, and destroying sessions. Implements configuration for session-related settings
 * such as cookies, security parameters, and SameSite attribute.
 */
abstract class SessionAbstract
{
  protected string $name = '';
  protected bool $force_cookies = true;
  public string|null $sameSite;

  public function __construct(string|null $name = null)
  {
    if ($name === null) {
      $currentName = session_name();
      $this->name = $currentName !== false ? $currentName : '';
    } else {
      $this->name = $name;
    }

    $this->setName($this->name);
  }

  /**
   * Checks if a session exists
   *
   * @param string $session_id The ID of the session
   */

  abstract public function exists(string $session_id);

  /**
   * Starts a new session while ensuring secure and strict session handling policies.
   *
   * This method modifies session configurations, such as cookie security and strict mode,
   * and ensures proper initialization by handling potential inconsistencies with session
   * identifiers. It also invokes hooks before and after the session is started.
   *
   * @return bool Returns true if the session successfully started, or false otherwise.
   */

  public function start(): bool
  {
    $CLICSHOPPING_Cookies = Registry::get('Cookies');

// this class handles session.use_strict_mode already
    if ((int)ini_get('session.use_strict_mode') === 1) {
      ini_set('session.use_strict_mode', 0);
    }

    if (parse_url(CLICSHOPPING::getConfig('http_server'), PHP_URL_SCHEME) == 'https') {
      if ((int)ini_get('session.cookie_secure') === 0) {
        ini_set('session.cookie_secure', 1);
      }
    }

    if ((int)ini_get('session.cookie_httponly') === 0) {
      ini_set('session.cookie_httponly', 1);
    }

    if ((int)ini_get('session.use_only_cookies') !== 1) {
      ini_set('session.use_only_cookies', 1);
    }

    ini_set('session.serialize_handler', 'php_serialize');

    $session_can_start = true;

    Registry::get('Hooks')->call('Session', 'StartBefore', [
      'can_start' => &$session_can_start
    ]);

    $options = [
      'lifetime' => 0,             // The lifetime of the cookie in seconds.
      'path' => $CLICSHOPPING_Cookies->getPath(),               // The path where information is stored.
      'domain' => $CLICSHOPPING_Cookies->getDomain(),   // The domain of the cookie.
      'secure' => (bool)ini_get('session.cookie_secure'),            // The cookie should only be sent over secure connections.
      'httponly' => (bool)ini_get('session.cookie_httponly'),          // The cookie can only be accessed through the HTTP protocol.
      'samesite' => $CLICSHOPPING_Cookies->getSameSite() //"Lax/Strict"  // The cookie can only be accessed if it was initiated from the same registrable domain or LAx
    ];

    session_set_cookie_params($options);

    if (isset($_GET[$this->name]) && ($this->force_cookies || !(bool)preg_match('/^[a-zA-Z0-9,-]+$/', $_GET[$this->name]) || !$this->exists($_GET[$this->name]))) {
      unset($_GET[$this->name]);
    }

    if (isset($_POST[$this->name]) && ($this->force_cookies || !(bool)preg_match('/^[a-zA-Z0-9,-]+$/', $_POST[$this->name]) || !$this->exists($_POST[$this->name]))) {
      unset($_POST[$this->name]);
    }

    if (isset($_COOKIE[$this->name]) && (!(bool)preg_match('/^[a-zA-Z0-9,-]+$/', $_COOKIE[$this->name]) || !$this->exists($_COOKIE[$this->name]))) {
      $CLICSHOPPING_Cookies->del($this->name, $CLICSHOPPING_Cookies->getPath(), $CLICSHOPPING_Cookies->getDomain(), (bool)ini_get('session.cookie_secure'), (bool)ini_get('session.cookie_httponly'));
    }

    if ($this->force_cookies === false) {
      if (isset($_GET[$this->name]) && (!isset($_COOKIE[$this->name]) || ($_COOKIE[$this->name] != $_GET[$this->name]))) {
        session_id($_GET[$this->name]);
      } elseif (isset($_POST[$this->name]) && (!isset($_COOKIE[$this->name]) || ($_COOKIE[$this->name] != $_POST[$this->name]))) {
        session_id($_POST[$this->name]);
      }
    }

    if (($session_can_start === true) && session_start()) {
      Registry::get('Hooks')->call('Session', 'StartAfter');

      return true;
    }

    return false;
  }

  /**
   * Sets whether cookies should be forcibly enabled.
   *
   * @param bool $force_cookies Determines if cookies should be forced.
   * @return void
   */
  public function setForceCookies(bool $force_cookies)
  {
    $this->force_cookies = $force_cookies;
  }

  /**
   *
   * @return bool Returns true if cookies are forced, false otherwise.
   */
  public function isForceCookies(): bool
  {
    return $this->force_cookies;
  }

  /**
   * Checks if the current session has started.
   *
   * @return bool True if the session is active, false otherwise.
   */

  public function hasStarted(): bool
  {
    return session_status() === PHP_SESSION_ACTIVE;
  }

  /**
   * Ends the current session and deletes the associated session cookie if it exists.
   *
   * @return bool Returns true on successful session destruction, or false otherwise.
   */
  public function kill(): bool
  {
    $CLICSHOPPING_Cookies = Registry::get('Cookies');

    $result = true;

    if (isset($_COOKIE[$this->name])) {
      $CLICSHOPPING_Cookies->del($this->name, $CLICSHOPPING_Cookies->getPath(), $CLICSHOPPING_Cookies->getDomain(), (bool)ini_get('session.cookie_secure'), (bool)ini_get('session.cookie_httponly'), $CLICSHOPPING_Cookies->getSameSite('Lax'));
    }

    if ($this->hasStarted()) {
      $_SESSION = [];

      $result = session_destroy();
    }

    return $result;
  }

  /**
   * Regenerates the session ID while preserving or deleting the old session data based on its existence,
   * and triggers a hook upon successful recreation.
   *
   * @return bool Returns true if the session ID was successfully regenerated, otherwise false.
   */
  public function recreate(): bool
  {
    $delete_flag = true;

    if (!$this->exists(session_id())) {
      $delete_flag = false;
    }

    $session_old_id = session_id();

    $result = session_regenerate_id($delete_flag);

    if ($result === true) {
      Registry::get('Hooks')->call('Session', 'Recreated', [
        'old_id' => $session_old_id
      ]);

      return true;
    }

    return false;
  }

  /**
   *
   * @param string $name The new name to set for the session.
   * @return string The previous name of the session.
   */

  public function setName(string $name): string
  {
    return session_name($name);
  }

  /**
   * Sets the lifetime for the session garbage collector in seconds.
   *
   * @param float $time The session lifetime in seconds.
   * @return float The previous value of the session.gc_maxlifetime configuration option.
   */

  public function setLifeTime(float $time): float
  {
    return ini_set('session.gc_maxlifetime', $time);
  }

  /**
   *
   * @return string|null Returns the name or null if not set.
   */
  public function getName(): ?string
  {
    return $this->name;
  }

  /**
   *
   * @return string|null Returns the same-site attribute value if set, or null if not set.
   */
  public function getSameSite(): ?string
  {
    return $this->sameSite;
  }
}
