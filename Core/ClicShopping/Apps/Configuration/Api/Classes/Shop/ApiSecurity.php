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

      $sql_data_array = ['api_id', 'date_modified', 'date_added'];

      $Qcheck = $CLICSHOPPING_Db->get('api_session', $sql_data_array, ['session_id' => $token], 1);

      if (static::$renewSession === true) {
        if (!empty($Qcheck->value('api_id'))) {
          $now = date('Y-m-d H:i:s');
          $date_diff = DateTime::getIntervalDate($Qcheck->value('date_modified'), $now);

          if ($date_diff > (int)CLICSHOPPING_APP_API_AI_SESSION_TIMEOUT_MINUTES) {
            $CLICSHOPPING_Db->delete('api_session', ['api_id' => (int)$Qcheck->valueInt('api_id')]);

            throw new Exception("Session expired");
          }

          return $token;
        }
      } else {
        if (!empty($Qcheck->value('api_id'))) {
          $now = date('Y-m-d H:i:s');
          $date_diff = DateTime::getIntervalDate($Qcheck->value('date_modified'), $now);

          if ($date_diff > (int)CLICSHOPPING_APP_API_AI_SESSION_TIMEOUT_MINUTES) {
            $CLICSHOPPING_Db->delete('api_session', ['api_id' => (int)$Qcheck->valueInt('api_id')]);

            $session_id = bin2hex(random_bytes(16));
            $Ip = HTTP::getIpAddress();

            $sql_data_array = [
              'api_id' => $Qcheck->valueInt('api_id'),
              'session_id' => $session_id,
              'date_modified' => 'now()',
              'date_added' => $Qcheck->value('date_added'),
              'ip' => $Ip
            ];

            $CLICSHOPPING_Db->save('api_session', $sql_data_array);

            self::logSecurityEvent('Session regenerated', [
              'old_token' => $token,
              'new_token' => $session_id,
              'api_id' => $Qcheck->valueInt('api_id')
            ]);

            return $session_id;
          }

          return $token;
        } else {
          $session_id = bin2hex(random_bytes(16));
          $Ip = HTTP::getIpAddress();

          $sql_data_array = [
            'api_id' => null,
            'session_id' => $session_id,
            'date_modified' => 'now()',
            'date_added' => 'now()',
            'ip' => $Ip
          ];

          $CLICSHOPPING_Db->save('api_session', $sql_data_array);

          self::logSecurityEvent('New session created for invalid token', [
            'old_token' => $token,
            'new_token' => $session_id
          ]);

          return $session_id;
        }
      }

      throw new Exception("Invalid or unknown token");

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

      $Qapi = $CLICSHOPPING_Db->prepare('select api_id,
                                                username,
                                                api_key,
                                                status,
                                                date_added,
                                                date_modified
                                         from :table_api
                                         where status = 1
                                         and username = :username
                                         and api_key = :api_key
                                         ');

      $Qapi->bindValue(':username', $username);
      $Qapi->bindValue(':api_key', $key);
      $Qapi->execute();

      $result = $Qapi->fetch();

      $isValid = !empty($result);

      if (!$isValid) {
        self::incrementFailedAttempts($username);
        self::logSecurityEvent('Failed authentication attempt', ['username' => $username]);

        return false;
      } else {
        self::resetFailedAttempts($username);
        self::logSecurityEvent('Successful authentication', ['username' => $username]);

        return $result;
      }

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