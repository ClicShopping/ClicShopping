<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\Api\Classes\Shop;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\DateTime;
use ClicShopping\OM\HTTP;
use ClicShopping\OM\Registry;
use Exception;
use PDOException;

class ApiSecurity {

  protected static $renewSession = true; //temporary time to add a refresh token

  /**
   * Extract the session token from the request, preferring the
   * X-API-Token header but falling back to the legacy ?token=
   * URL/body parameter for backward compatibility with existing
   * clients such as api/get_product.php.
   */
  public static function extractToken(): string
  {
    $header = $_SERVER['HTTP_X_API_TOKEN'] ?? '';
    if ($header !== '') {
      return trim($header);
    }
    return (string)($_GET['token'] ?? $_POST['token'] ?? '');
  }

  /**
   * Emit the current session token + remaining TTL as response headers.
   *
   * Lets Api clients reuse the same session across requests instead of
   * re-authenticating each call. Mirror of MCP B2. Safe to call from
   * anywhere before output starts; idempotent.
   */
  public static function emitSessionHeaders(string $sessionId): void
  {
    if ($sessionId === '' || headers_sent()) {
      return;
    }

    $timeoutMinutes = \defined('CLICSHOPPING_APP_API_AI_SESSION_TIMEOUT_MINUTES')
      ? (int)CLICSHOPPING_APP_API_AI_SESSION_TIMEOUT_MINUTES
      : 30;

    header('X-API-Session-Token: ' . $sessionId);
    header('X-API-Session-Expires-In: ' . max(60, $timeoutMinutes * 60));
  }

  /**
   * Resolve the active IP check mode from the App configuration.
   * Defaults to 'subnet' when the constant is missing (fresh install or
   * version pre-dating the ip_check_mode param).
   *
   * @return string  One of: 'strict', 'subnet', 'off'.
   */
  protected static function ipCheckMode(): string
  {
    $mode = \defined('CLICSHOPPING_APP_API_AI_IP_CHECK_MODE')
      ? strtolower((string)CLICSHOPPING_APP_API_AI_IP_CHECK_MODE)
      : 'subnet';

    return in_array($mode, ['strict', 'subnet', 'off'], true) ? $mode : 'subnet';
  }

  /**
   * Decide whether $actual matches $expected under the active IP check mode.
   *
   * - strict : byte-for-byte equality.
   * - subnet : same /24 (IPv4) or /64 (IPv6) — tolerates NAT, CDN multi-PoP,
   *            carrier-grade NAT while still blocking cross-network replay.
   * - off    : skip entirely (security relies on the 128-bit token secret).
   */
  public static function isIpAllowed(string $expected, string $actual): bool
  {
    $mode = self::ipCheckMode();

    if ($mode === 'off') {
      return true;
    }

    if ($mode === 'strict') {
      return $expected === $actual;
    }

    $expectedV4 = filter_var($expected, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4);
    $actualV4   = filter_var($actual,   FILTER_VALIDATE_IP, FILTER_FLAG_IPV4);
    if ($expectedV4 !== false && $actualV4 !== false) {
      return self::ipv4Prefix($expectedV4, 24) === self::ipv4Prefix($actualV4, 24);
    }

    $expectedV6 = filter_var($expected, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6);
    $actualV6   = filter_var($actual,   FILTER_VALIDATE_IP, FILTER_FLAG_IPV6);
    if ($expectedV6 !== false && $actualV6 !== false) {
      return str_starts_with(inet_pton($actualV6), substr(inet_pton($expectedV6), 0, 8));
    }

    // Mixed family or unparseable — fall back to strict to avoid silent bypass.
    return $expected === $actual;
  }

  /**
   * Return the /$prefix network address of an IPv4 in dotted form.
   */
  private static function ipv4Prefix(string $ip, int $prefix): string
  {
    $long = ip2long($ip);
    if ($long === false) {
      return $ip;
    }
    $mask = -1 << (32 - $prefix);
    return long2ip($long & $mask);
  }

  /**
   * Validates and regenerates the API session token if necessary
   *
   * @param string $token The current session token to check or renew.
   * @return string The valid session token, either existing or newly generated.
   * @throws Exception If token processing fails
   */
  public static function checkToken(string $token): string
  {
    if (empty($token)) {
      throw new Exception("Token cannot be empty");
    }

    if (strlen($token) !== 32 || !ctype_xdigit($token)) {
      throw new Exception("Invalid token format");
    }

    $clientIp = HTTP::getIpAddress();

    if (!self::checkRateLimit($clientIp, 'token_check')) {
      throw new Exception("Rate limit exceeded for token validation");
    }

    try {
      $CLICSHOPPING_Db = Registry::get('Db');

      if (!$CLICSHOPPING_Db) {
        throw new Exception("Database connection not available");
      }

      $Qcheck = $CLICSHOPPING_Db->get(
        'api_session',
        ['api_id', 'date_modified', 'date_added', 'ip'],
        ['session_id' => $token],
        1
      );

      // Unknown token → reject. We do NOT silently create an anonymous
      // session here: the previous behaviour let any random hex spam the
      // api_session table with api_id=NULL rows nothing would ever purge.
      if (empty($Qcheck->value('api_id'))) {
        self::logSecurityEvent('Invalid or unknown token received', [
          'token' => substr(hash('sha256', $token), 0, 12) . '...',
        ]);
        throw new Exception("Invalid or unknown token");
      }

      // IP verification — mode controlled by CLICSHOPPING_APP_API_AI_IP_CHECK_MODE
      // (strict | subnet | off). Backported from MCP B4. See isIpAllowed().
      if (!self::isIpAllowed((string)$Qcheck->value('ip'), $clientIp)) {
        self::logSecurityEvent('Token hijacking attempt detected (IP mismatch)', [
          'api_id'      => $Qcheck->valueInt('api_id'),
          'expected_ip' => $Qcheck->value('ip'),
          'current_ip'  => $clientIp,
          'mode'        => self::ipCheckMode(),
        ]);
        throw new Exception("Token IP mismatch detected. Session terminated.");
      }

      $date_diff = DateTime::getIntervalDate($Qcheck->value('date_modified'), date('Y-m-d H:i:s'));

      // Session still within window — refresh date_modified and reuse the token.
      if ($date_diff <= (int)CLICSHOPPING_APP_API_AI_SESSION_TIMEOUT_MINUTES) {
        $CLICSHOPPING_Db->save('api_session', ['date_modified' => 'now()'], ['session_id' => $token]);
        return $token;
      }

      // Expired session. If renewal is disabled, purge and reject.
      if (static::$renewSession !== true) {
        $CLICSHOPPING_Db->delete('api_session', ['session_id' => $token]);
        throw new Exception("Session expired");
      }

      // Atomic rotation: a single UPDATE replaces session_id so that two
      // concurrent requests with the same expired token cannot both succeed
      // (only one will get rowCount === 1; the loser gets 0 and is forced to
      // re-authenticate). Replaces the previous DELETE+INSERT pair which was
      // racy. Backported from MCP B3.
      $newSessionId = bin2hex(random_bytes(16));

      $Qrotate = $CLICSHOPPING_Db->prepare('
        UPDATE :table_api_session
           SET session_id    = :new_token,
               date_modified = NOW(),
               ip            = :ip
         WHERE session_id    = :old_token
      ');
      $Qrotate->bindValue(':new_token', $newSessionId);
      $Qrotate->bindValue(':ip',        $clientIp);
      $Qrotate->bindValue(':old_token', $token);
      $Qrotate->execute();

      if ($Qrotate->rowCount() !== 1) {
        self::logSecurityEvent('Session rotation race lost', [
          'old_token' => $token,
          'api_id'    => $Qcheck->valueInt('api_id'),
        ]);
        throw new Exception("Session expired");
      }

      self::logSecurityEvent('Session regenerated', [
        'old_token' => $token,
        'new_token' => $newSessionId,
        'api_id'    => $Qcheck->valueInt('api_id'),
      ]);

      return $newSessionId;

    } catch (PDOException $e) {
      self::logSecurityEvent('Database error in checkToken', [
        'token' => substr(hash('sha256', $token), 0, 12) . '...',
        'error' => $e->getMessage()
      ]);
      throw new \Exception("Token validation failed");
    }
  }

  /**
   * Save the security event inside the database
   */
  public static function isAccountLocked(string $username): bool
  {
    try {
      $CLICSHOPPING_Db = Registry::get('Db');

      $key = 'login_attempts_' . hash('sha256', $username);

      $Qattempts = $CLICSHOPPING_Db->get('api_failed_attempts', [
        'attempts',
        'last_attempt'
      ], [
        'identifier' => $key
      ]);

      if ($Qattempts->rowCount() === 0) {
        return false;
      }

      $attempts = $Qattempts->valueInt('attempts');
      $lastAttempt = $Qattempts->valueInt('last_attempt');

      if ($attempts >= (int)CLICSHOPPING_APP_API_AI_MAX_LOGIN_ATTEMPTS) {
        $timeSinceLastAttempt = time() - $lastAttempt;
        return $timeSinceLastAttempt < (int)CLICSHOPPING_APP_API_AI_ACCOUNT_LOCK_DURATION;
      }

      return false;

    } catch (\Exception $e) {
      self::logSecurityEvent('Error checking account lock status', [
        'username' => $username,
        'error' => $e->getMessage()
      ]);

      return false;
    }
  }

  /* Save the security event inside the database
   * @param string $eventType Type d'événement (e.g., 'login_attempt', 'rate_limit_exceeded')
   * @param array $details Détails supplémentaires sur l'événement
   */
  public static function checkRateLimit(string $identifier, string $action): bool
  {
    try {
      $CLICSHOPPING_Db = Registry::get('Db');

      $key = $action . '_' . hash('sha256', $identifier);
      $window_start = time() - (int)CLICSHOPPING_APP_API_AI_RATE_LIMIT_WINDOW;

      $Qdelete = $CLICSHOPPING_Db->prepare('delete 
                                            from :table_api_rate_limit 
                                            where timestamp < :window_start');
      $Qdelete->bindValue(':window_start', $window_start);
      $Qdelete->execute();

      $Qcount = $CLICSHOPPING_Db->prepare('select count(id) as count 
                                          from :table_api_rate_limit
                                          where identifier = :identifier
                                          and timestamp >= :timestamp
                                          ');
      $Qcount->bindValue(':identifier', $key);
      $Qcount->bindValue(':timestamp', $window_start);
      $Qcount->execute();

      $attempts = $Qcount->valueInt('count') ?? 0;

      if ($attempts >= (int)CLICSHOPPING_APP_API_AI_MAX_REQUEST_PER_WINDOW) {
        return false;
      }

      $CLICSHOPPING_Db->save('api_rate_limit', [
        'identifier' => $key,
        'timestamp' => time(),
        'ip' => HTTP::getIpAddress()
      ]);

      return true;

    } catch (\Exception $e) {
      self::logSecurityEvent('Rate limit check failed', [
        'identifier' => $identifier,
        'action' => $action,
        'error' => $e->getMessage()
      ]);

      return true;
    }
  }

   /** Increments the number of failed login attempts for a user
   * @param string $username Nom d'utilisateur
   */
  public static function incrementFailedAttempts(string $username): void
  {
    try {
      $CLICSHOPPING_Db = Registry::get('Db');

      $key = 'login_attempts_' . hash('sha256', $username);

      $Qexisting = $CLICSHOPPING_Db->get('api_failed_attempts', ['attempts', 'last_attempt'], ['identifier' => $key]);

      if ($Qexisting->rowCount() > 0) {
        $attempts = $Qexisting->valueInt('attempts') + 1;

        $CLICSHOPPING_Db->save('api_failed_attempts', [
          'attempts' => $attempts,
          'last_attempt' => time()
        ], ['identifier' => $key]);
      } else {
        $CLICSHOPPING_Db->save('api_failed_attempts', [
          'identifier' => $key,
          'attempts' => 1,
          'last_attempt' => time()
        ]);
      }
    } catch (\Exception $e) {
      self::logSecurityEvent('Failed to increment failed attempts', [
        'username' => $username,
        'error' => $e->getMessage()
      ]);
    }
  }

  /**
   * Resets the number of failed login attempts for a user
   * @param string $username Nom d'utilisateur
   */
  public static function resetFailedAttempts(string $username)
  {
    try {
      $CLICSHOPPING_Db = Registry::get('Db');
      $key = 'login_attempts_' . hash('sha256', $username);

      $CLICSHOPPING_Db->delete('api_failed_attempts', ['identifier' => $key]);
    } catch (\Exception $e) {
      // Log mais ne pas faire échouer l'authentification
      self::logSecurityEvent('Failed to reset failed attempts', [
        'username' => $username,
        'error' => $e->getMessage()
      ]);
    }
  }

  /** Validate user credentials with enhanced security checks
   * @param string $username Nom d'utilisateur
   * @param string $key Clé API
   * @return bool Retourne true si les informations d'identification sont valides, sinon false
   */
  protected static function validateCredentials(string $username, string $key): bool
  {
    if (empty($username) || empty($key)) {
      self::logSecurityEvent('Empty credentials provided', ['username' => $username]);
      return false;
    }

    if (strlen($username) > 100 || strlen($key) > 255) {
      self::logSecurityEvent('Credentials too long', ['username' => $username]);
      return false;
    }

    return true;
  }

  /**
   * Logs security events to a file with enhanced security measures
   *
   * @param string $event The type of event being logged (e.g., 'login_attempt', 'rate_limit_exceeded').
   * @param array $data Additional data related to the event.
   */
  public static function logSecurityEvent(string $event, array $data = [])
  {
    try {
      // Neutralisation des données sensibles
      $sanitizedData = [];

      foreach ($data as $key => $value) {
        if (in_array($key, ['token', 'old_token', 'new_token', 'session_id', 'api_id'], true)) {
          $sanitizedData[$key] = substr(hash('sha256', (string)$value), 0, 12) . '...';
        } elseif ($key === 'error') {
          $sanitizedData[$key] = self::sanitizeErrorMessage($value);
        } else {
          $sanitizedData[$key] = $value;
        }
      }

      $logData = [
        'timestamp' => date('c'),
        'event' => $event,
        'ip' => self::sanitizeIp(HTTP::getIpAddress()),
        'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown', 0, 120),
        'data' => $sanitizedData
      ];

      $logDir = CLICSHOPPING::getConfig('dir_root', 'Shop') . 'Work/Log/';
      $logFile = $logDir . 'api_security.log';
      $maxSize = 10 * 1024 * 1024; // 10 Mo

      if (!is_dir($logDir)) {
        if (!mkdir($logDir, 0755, true) && !is_dir($logDir)) {
          throw new \RuntimeException(sprintf('Directory "%s" was not created', $logDir));
        }
      }

      // Rotation si taille max atteinte
      if (file_exists($logFile) && filesize($logFile) >= $maxSize) {
        $backupFile = $logFile . '.' . date('Ymd_His');
        rename($logFile, $backupFile);
      }

      $encoded = json_encode($logData, JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);
      file_put_contents($logFile, $encoded . PHP_EOL, FILE_APPEND | LOCK_EX);
    } catch (\Throwable $e) {
      error_log('[API_SECURITY_LOG_ERROR] ' . self::sanitizeErrorMessage($e->getMessage()));
    }
  }

  /**
   * Sanitizes error messages to prevent SQL injection and sensitive data exposure
   *
   * @param string $msg The error message to sanitize.
   * @return string The sanitized error message.
   */
  protected static function sanitizeErrorMessage(string $msg): string
  {
    return preg_replace('/(select|update|insert|delete|from|where)[^;]*/i', '[SQL_REDACTED]', $msg);
  }

  /**
   * Sanitizes IP addresses by masking the last segment for IPv4 and IPv6
   *
   * @param string $ip The IP address to sanitize.
   * @return string The sanitized IP address.
   */
  protected static function sanitizeIp(string $ip): string
  {
    // Masque les derniers segments pour IPv4/IPv6
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
      return preg_replace('/\.\d+$/', '.x', $ip);
    }

    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
      return preg_replace('/:[a-f0-9]*$/i', ':x', $ip);
    }

    return 'Unknown';
  }

  /**
   * Validates IP address against allowed IPs for the API with enhanced security
   *
   * @param int $api_id The API ID used to retrieve the associated IP address.
   * @return bool Returns true if IP is allowed, false otherwise.
   * @throws Exception If validation fails due to database error
   */
 public static function validateIp(int $api_id): bool
 {
   if ($api_id <= 0) {
     self::logSecurityEvent('Invalid API ID in IP check', ['api_id' => $api_id]);
     return false;
   }

   try {
     $CLICSHOPPING_Db = Registry::get('Db');

     if (!$CLICSHOPPING_Db) {
       throw new \Exception("Database connection not available");
     }

     $clientIp = HTTP::getIpAddress();

     if (empty($clientIp)) {
       self::logSecurityEvent('Unable to determine client IP', ['api_id' => $api_id]);
       return false;
     }

     $Qips = $CLICSHOPPING_Db->get('api_ip', 'ip', ['api_id' => $api_id]);

     if (empty($Qips)) {
       self::logSecurityEvent('No IP restrictions found for API - access denied', [
         'api_id' => $api_id,
         'client_ip' => $clientIp
       ]);

       return false;
     }

     foreach ($Qips as $allowedIp) {
       $ip = $allowedIp['ip'];

       if ($ip === '127.0.0.1' || $ip === 'localhost') {
         if (in_array($clientIp, ['127.0.0.1', '::1'])) {
           self::logSecurityEvent('Localhost access granted', [
             'api_id' => $api_id,
             'client_ip' => $clientIp
           ]);

           return true;
         }
       } elseif ($ip === $clientIp) {
         self::logSecurityEvent('IP match found', [
           'api_id' => $api_id,
           'client_ip' => $clientIp,
           'allowed_ip' => $ip
         ]);

         return true;
       }
       elseif (self::ipInRange($clientIp, $ip)) {
         self::logSecurityEvent('IP in allowed range', [
           'api_id' => $api_id,
           'client_ip' => $clientIp,
           'range' => $ip
         ]);

         return true;
       }
     }

     self::logSecurityEvent('IP access denied', [
       'api_id' => $api_id,
       'client_ip' => $clientIp,
       'allowed_ips' => array_column($Qips, 'ip')
     ]);

     return false;

   } catch (PDOException $e) {
     self::logSecurityEvent('Database error in IP check', [
       'api_id' => $api_id,
       'error' => $e->getMessage()
     ]);

     throw new \Exception("IP validation failed");
   }
  }

  /**
   * Check if an IP address is within a given range (CIDR)
   *
   * @param string $ip The IP address to check
   * @param string $range The CIDR range (e.g., '
   */
  public static function ipInRange(string $ip, string $range): bool
  {
    if (strpos($range, '/') === false) {
      return $ip === $range;
    }

    list($subnet, $bits) = explode('/', $range);
    $ip = ip2long($ip);
    $subnet = ip2long($subnet);
    $mask = -1 << (32 - $bits);
    $subnet &= $mask;

    return ($ip & $mask) === $subnet;
  }

  /**
   * Authenticates user credentials against the database
   * @param string $username Nom d'utilisateur
   * @param string $key Clé API
   * @return array|bool Retourne les informations de l'utilisateur si l'authentification réussit, sinon false
   * @throws Exception Si une erreur de base de données se produit
   */
  public static function authenticateCredentials(string $username, string $key): array|bool
  {
    try {
      $CLICSHOPPING_Db = Registry::get('Db');

      if (!$CLICSHOPPING_Db) {
        throw new Exception("Database connection not available");
      }

      // (B19) Look up by username only, then compare the api_key in PHP with
      // hash_equals(). Doing the comparison in MySQL (`AND api_key = :key`)
      // exposes a measurable timing channel on the api_key column. The user
      // row is still rejected if the key does not match.
      $Qapi = $CLICSHOPPING_Db->prepare('select api_id,
                                                username,
                                                api_key,
                                                status,
                                                date_added,
                                                date_modified
                                         from :table_api
                                         where status = 1
                                         and username = :username
                                         limit 1
                                         ');

      $Qapi->bindValue(':username', $username);
      $Qapi->execute();

      $result = $Qapi->fetch();

      if (empty($result) || !hash_equals((string)$result['api_key'], (string)$key)) {
        self::incrementFailedAttempts($username);
        self::logSecurityEvent('Failed authentication attempt', ['username' => $username]);
        return false;
      }

      self::resetFailedAttempts($username);
      self::logSecurityEvent('Successful authentication', ['username' => $username]);
      return $result;

    } catch (PDOException $e) {
      self::logSecurityEvent('Database error in authentication', [
        'username' => $username,
        'error' => $e->getMessage()
      ]);

      throw new Exception("Authentication service temporarily unavailable");
    }
  }

  /**
   * Performs the authentication process with enhanced security checks
   * @param string $username Nom d'utilisateur
   * @param string $key
   */
  protected static function performAuthentication(string $username, string $key)
  {
    if (!self::validateCredentials($username, $key)) {
      return false;
    }

    if (self::isAccountLocked($username)) {
      self::logSecurityEvent('Authentication attempted on locked account', [
        'username' => $username
      ]);

      throw new Exception("Account temporarily locked due to multiple failed attempts");
    }

    if (!self::checkRateLimit($username, 'login')) {
      self::logSecurityEvent('Rate limit exceeded for authentication', [
        'username' => $username
      ]);

      throw new Exception("Rate limit exceeded. Please try again later.");
    }
  }

  /**
   * Checks if the current environment is a local development environment
   * @return bool Returns true if the environment is local, false otherwise
   */
  public static function isLocalEnvironment(): bool
  {
    $ip = HTTP::getIpAddress();

    if (in_array($ip, ['127.0.0.1', '::1'])) {
      return true;
    }

    $serverName = $_SERVER['SERVER_NAME'] ?? '';
    $host = $_SERVER['HTTP_HOST'] ?? '';

    return str_contains($serverName, 'localhost') || str_contains($host, 'localhost');
  }

  /**
   * Validates the ID parameter for API requests
   * @param int|string $id The ID to validate
   * @throws Exception If the ID is invalid
   */
  public static function secureGetId(int| string $id):void
  {

    if ($id !== null) {
      if ($id !== 'All' && !ctype_digit($id)) {
        http_response_code(400);
        exit(json_encode(['error' => 'Invalid Id format']));
      }
    }
  }
}