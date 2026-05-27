<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Tools\MCP\Classes\Shop\Security;



use ClicShopping\OM\DateTime;
use ClicShopping\OM\HTTP;
use ClicShopping\OM\Registry;
use Exception;
use PDOException;


/**
 * Provides security-related functionalities for the MCP API, including token validation,
 * session management, rate limiting, and IP address validation.
 */
class McpSecurity
{

  // Indique si le jeton doit être renouvelé (étendu) en cas d'expiration lors de checkToken
  protected static bool $renewSession = true;

  /**
   * Resolve the active IP check mode from the App configuration.
   * Defaults to 'subnet' when the constant is missing (fresh install or
   * version pre-dating the ip_check_mode param).
   *
   * @return string  One of: 'strict', 'subnet', 'off'.
   */
  protected static function ipCheckMode(): string
  {
    $mode = \defined('CLICSHOPPING_APP_MCP_MC_IP_CHECK_MODE')
      ? strtolower((string)CLICSHOPPING_APP_MCP_MC_IP_CHECK_MODE)
      : 'subnet';

    return in_array($mode, ['strict', 'subnet', 'off'], true) ? $mode : 'subnet';
  }

  /**
   * Decide whether $actual matches $expected under the active IP check mode.
   *
   * - strict : byte-for-byte equality (legacy behaviour).
   * - subnet : same /24 for IPv4, same /64 for IPv6 — tolerates NAT, CDN
   *            multi-PoP, and most carrier-grade NAT cases while still
   *            blocking trivial cross-network token replay.
   * - off    : skip entirely (security relies on the 128-bit token secret).
   *
   * Returns true when access should be allowed.
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

    // 'subnet' — compare network prefix.
    $expectedV4 = filter_var($expected, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4);
    $actualV4   = filter_var($actual,   FILTER_VALIDATE_IP, FILTER_FLAG_IPV4);
    if ($expectedV4 !== false && $actualV4 !== false) {
      // Same /24 (first three octets).
      return self::ipv4Prefix($expectedV4, 24) === self::ipv4Prefix($actualV4, 24);
    }

    $expectedV6 = filter_var($expected, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6);
    $actualV6   = filter_var($actual,   FILTER_VALIDATE_IP, FILTER_FLAG_IPV6);
    if ($expectedV6 !== false && $actualV6 !== false) {
      // Same /64 — first 8 bytes of the 16-byte packed form.
      return substr(inet_pton($expectedV6), 0, 8) === substr(inet_pton($actualV6), 0, 8);
    }

    // Mixed family or unparseable — fall back to strict to avoid silent bypass.
    return $expected === $actual;
  }

  /**
   * Return the /$prefix network address of an IPv4 in dotted form.
   * Used by isIpAllowed() in 'subnet' mode.
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
   * Emit the current session token + remaining TTL as response headers.
   *
   * Lets MCP clients reuse the same session across requests instead of
   * re-authenticating each time (which would create a new mcp_session row
   * per call). Called by every Page after a successful authentication or
   * token validation — including silent renewals, so the client can pick
   * up the new token when checkToken() rotates it.
   *
   * Idempotent. Safe to call from anywhere before output starts.
   */
  public static function emitSessionHeaders(string $sessionId): void
  {
    if ($sessionId === '' || headers_sent()) {
      return;
    }

    $timeoutMinutes = \defined('CLICSHOPPING_APP_MCP_MC_SESSION_TIMEOUT_MINUTES')
      ? (int)CLICSHOPPING_APP_MCP_MC_SESSION_TIMEOUT_MINUTES
      : 30;

    header('X-MCP-Session-Token: ' . $sessionId);
    header('X-MCP-Session-Expires-In: ' . max(60, $timeoutMinutes * 60));
  }

  /**
   * Validates and regenerates the API session token if necessary
   *
   * @param string $token The current session token to check or renew.
   * @return string The valid session token, either existing or newly generated.
   * @throws Exception If token processing fails or the token is invalid/expired without renewal.
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

      $sql_data_array = ['mcp_id', 'date_modified', 'date_added', 'ip'];

      // Utilisation du token dans la clause WHERE (secure car c'est un session_id)
      $Qcheck = $CLICSHOPPING_Db->get('mcp_session', $sql_data_array, ['session_id' => $token], 1);

      if ($Qcheck->valueInt('mcp_id') > 0) {
        // Token trouvé et lié à un utilisateur
        $now = date('Y-m-d H:i:s');
        $date_diff = DateTime::getIntervalDate($Qcheck->value('date_modified'), $now);

        // IP verification — mode controlled by CLICSHOPPING_APP_MCP_MC_IP_CHECK_MODE.
        // strict = exact match (legacy), subnet = /24 IPv4 or /64 IPv6 (default),
        // off = no check (token secret only). See ip_check_mode param.
        if (!self::isIpAllowed($Qcheck->value('ip'), $clientIp)) {
          self::logSecurityEvent('Token hijacking attempt detected (IP mismatch)', [
            'mcp_id'      => $Qcheck->valueInt('mcp_id'),
            'expected_ip' => $Qcheck->value('ip'),
            'current_ip'  => $clientIp,
            'mode'        => self::ipCheckMode(),
          ]);
          throw new Exception("Token IP mismatch detected. Session terminated.");
        }


        // Check if session has expired
        if ($date_diff > (int)CLICSHOPPING_APP_MCP_MC_SESSION_TIMEOUT_MINUTES) {

          if (static::$renewSession !== true) {
            // Renewal disabled by policy — purge and reject.
            $CLICSHOPPING_Db->delete('mcp_session', ['session_id' => $token]);
            throw new Exception("Session expired");
          }

          // Atomic rotation: a single UPDATE replaces the session_id, so that
          // two concurrent requests with the same expired token cannot both
          // succeed (only one will get rowCount === 1; the loser gets 0 and
          // is forced to re-authenticate). Replaces the previous
          // DELETE+INSERT pair which was racy.
          $newSessionId = bin2hex(random_bytes(16));

          $Qrotate = $CLICSHOPPING_Db->prepare('
            UPDATE :table_mcp_session
               SET session_id   = :new_token,
                   date_modified = NOW(),
                   ip            = :ip
             WHERE session_id   = :old_token
          ');
          $Qrotate->bindValue(':new_token', $newSessionId);
          $Qrotate->bindValue(':ip',        $clientIp);
          $Qrotate->bindValue(':old_token', $token);
          $Qrotate->execute();

          if ($Qrotate->rowCount() !== 1) {
            // Lost the race: another concurrent request already rotated
            // this token (or it has been purged by the cleanup job).
            self::logSecurityEvent('Session rotation race lost', [
              'old_token' => $token,
              'mcp_id'    => $Qcheck->valueInt('mcp_id'),
            ]);
            throw new Exception("Session expired");
          }

          self::logSecurityEvent('Session regenerated', [
            'old_token' => $token,
            'new_token' => $newSessionId,
            'mcp_id'    => $Qcheck->valueInt('mcp_id'),
          ]);

          return $newSessionId;
        }

        // Session is still valid, update the last modified date
        $CLICSHOPPING_Db->save('mcp_session', ['date_modified' => 'now()'], ['session_id' => $token]);

        return $token;
      } else {
        // Token non trouvé ou expiré et déjà nettoyé
        self::logSecurityEvent('Invalid or unknown token received', [
          'token' => substr(hash('sha256', $token), 0, 12) . '...'
        ]);
        throw new Exception("Invalid or unknown token");
      }
    } catch (PDOException $e) {
      self::logSecurityEvent('Database error in checkToken', [
        'token' => substr(hash('sha256', $token), 0, 12) . '...',
        'error' => $e->getMessage()
      ]);
      throw new Exception("Token validation failed");
    }
  }

  /**
   * Retrieves the username associated with a valid session ID.
   *
   * @param string $sessionId The session ID token.
   * @return string The associated username.
   * @throws Exception If session or user is not found.
   */
  public static function getUsernameFromSession(string $sessionId): string
  {
    try {
      $CLICSHOPPING_Db = Registry::get('Db');

      // 1. Get mcp_id from session
      $Qsession = $CLICSHOPPING_Db->get('mcp_session', 'mcp_id', ['session_id' => $sessionId], 1);

      if (!$Qsession->valueInt('mcp_id')) {
        self::logSecurityEvent('Session ID not found or user not linked', ['session_id' => $sessionId]);
        throw new Exception("Session ID is not valid or is an anonymous session.");
      }

      $mcpId = $Qsession->valueInt('mcp_id');

      // 2. Get username from mcp table
      // Note: Assurez-vous que le nom de la table est 'mcp' ou le placeholder correct si vous utilisez un ORM
      $Quser = $CLICSHOPPING_Db->get('mcp', 'username', ['mcp_id' => $mcpId, 'status' => 1], 1);

      if (!$Quser->value('username')) {
        self::logSecurityEvent('User not found or inactive for session', ['mcp_id' => $mcpId]);
        throw new Exception("User linked to session not found or is inactive.");
      }

      return $Quser->value('username');

    } catch (PDOException $e) {
      self::logSecurityEvent('Database error in getUsernameFromSession', [
        'session_id' => $sessionId,
        'error' => $e->getMessage()
      ]);
      throw new Exception("Session validation failed due to database error.");
    }
  }

  /**
   * Checks if the account is locked due to too many failed login attempts
   *
   * @param string $username The username to check.
   * @return bool Returns true if the account is locked, otherwise false.
   */
  public static function isAccountLocked(string $username): bool
  {
    try {
      $CLICSHOPPING_Db = Registry::get('Db');

      $key = 'login_attempts_' . hash('sha256', $username);

      $Qattempts = $CLICSHOPPING_Db->get('mcp_failed_attempts', [
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

      if ($attempts >= (int)CLICSHOPPING_APP_MCP_MC_MAX_LOGIN_ATTEMPTS) {
        // Check if lock duration has passed
        $timeSinceLastAttempt = time() - $lastAttempt;
        return $timeSinceLastAttempt < (int)CLICSHOPPING_APP_MCP_MC_ACCOUNT_LOCK_DURATION;
      }

      return false;

    } catch (Exception $e) {
      self::logSecurityEvent('Error checking account lock status', [
        'username' => $username,
        'error' => $e->getMessage()
      ]);

      return false;
    }
  }

  /**
   * Checks the rate limit for a given identifier (IP or username) and action
   *
   * @param string $identifier IP address or username
   * @param string $action Action type (e.g., 'login', 'token_check')
   * @return bool True if the request is within the limit, false otherwise.
   */
  public static function checkRateLimit(string $identifier, string $action): bool
  {
    try {
      $CLICSHOPPING_Db = Registry::get('Db');

      $key = $action . '_' . hash('sha256', $identifier);
      $window_start = time() - (int)CLICSHOPPING_APP_MCP_MC_RATE_LIMIT_WINDOW;

      // Nettoyer les anciennes tentatives
      $CLICSHOPPING_Db->delete('mcp_rate_limit', ['timestamp < :timestamp'], [':timestamp' => $window_start]);

      // Compter les tentatives dans la fenêtre
      $Qcount = $CLICSHOPPING_Db->prepare('SELECT count(id) AS count
                                           FROM :table_mcp_rate_limit
                                           WHERE identifier = :identifier AND timestamp >= :timestamp
                                        ');
      $Qcount->bindValue(':identifier', $key);
      $Qcount->bindValue(':timestamp', $window_start);
      $Qcount->execute();

      $attempts = $Qcount->valueInt('count') ?? 0;

      if ($attempts >= (int)CLICSHOPPING_APP_MCP_MC_MAX_REQUEST_PER_WINDOW) {
        return false;
      }

      // Enregistrer la tentative actuelle
      $CLICSHOPPING_Db->save('mcp_rate_limit', [
        'identifier' => $key,
        'timestamp' => time(),
        'ip' => HTTP::getIpAddress()
      ]);

      return true;

    } catch (Exception $e) {
      self::logSecurityEvent('Rate limit check failed (DB error)', [
        'identifier' => $identifier,
        'action' => $action,
        'error' => $e->getMessage()
      ]);

      // Par défaut, permettre l'accès en cas d'erreur DB sur le rate limit pour ne pas bloquer le service
      return true;
    }
  }

  /**
   * Increments the number of failed login attempts for a user
   * @param string $username Nom d'utilisateur
   */
  public static function incrementFailedAttempts(string $username): void
  {
    try {
      $CLICSHOPPING_Db = Registry::get('Db');

      $key = 'login_attempts_' . hash('sha256', $username);

      $Qexisting = $CLICSHOPPING_Db->get('mcp_failed_attempts', ['attempts', 'last_attempt'], ['identifier' => $key]);

      if ($Qexisting->rowCount() > 0) {
        $attempts = $Qexisting->valueInt('attempts') + 1;

        $CLICSHOPPING_Db->save('mcp_failed_attempts', [
          'attempts' => $attempts,
          'last_attempt' => time()
        ], ['identifier' => $key]);
      } else {
        $CLICSHOPPING_Db->save('mcp_failed_attempts', [
          'identifier' => $key,
          'attempts' => 1,
          'last_attempt' => time()
        ]);
      }
    } catch (Exception $e) {
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
  public static function resetFailedAttempts(string $username): void
  {
    try {
      $CLICSHOPPING_Db = Registry::get('Db');
      $key = 'login_attempts_' . hash('sha256', $username);

      $CLICSHOPPING_Db->delete('mcp_failed_attempts', ['identifier' => $key]);
    } catch (Exception $e) {
      // Log mais ne pas faire échouer l'authentification
      self::logSecurityEvent('Failed to reset failed attempts', [
        'username' => $username,
        'error' => $e->getMessage()
      ]);
    }
  }

  /**
   * Validates user credentials with enhanced security checks
   *
   * @param string $username Nom d'utilisateur
   * @param string $key Clé API
   * @return bool Retourne true si les informations d'identification sont valides, sinon false
   */
  protected static function validateCredentials(string $username, string $key): bool
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    // Assurez-vous d'avoir accès à la table `mcp` (clic_mcp ou autre)
    $Qcheck = $CLICSHOPPING_Db->prepare('SELECT mcp_key,
                                                status
                                          FROM :table_mcp
                                          WHERE username = :mcp_username
                                      ');

    $Qcheck->bindValue(':mcp_username', $username);
    $Qcheck->execute();

    if ($Qcheck->fetch() && $Qcheck->valueInt('status') === 1) {
      // **CRITIQUE**: Utiliser hash_equals pour une comparaison sécurisée de la clé/token.
      if (hash_equals($Qcheck->value('mcp_key'), $key)) {
        // Identifiants valides
        self::resetFailedAttempts($username);
        self::logSecurityEvent('Successful authentication', ['username' => $username]);
        return true;
      }
    }

    // Échec de l'authentification (utilisateur non trouvé, inactif, ou clé incorrecte)
    return false;
  }

  /**
   * Vérification complète des identifiants (incluant Lockout et Rate Limit)
   *
   * @param string $username Nom d'utilisateur
   * @param string $key Clé API
   * @throws Exception Si l'authentification échoue (mauvais identifiants, compte bloqué, ou Rate Limit)
   */
  public static function checkCredentials(string $username, string $key): void
  {
    if (empty($username) || empty($key)) {
      self::logSecurityEvent('Empty credentials provided', ['username' => $username]);
      throw new Exception("Username and key are required.");
    }

    // 1. Vérification Lockout (avant toute chose)
    if (self::isAccountLocked($username)) {
      self::logSecurityEvent('Authentication attempted on locked account', ['username' => $username]);
      throw new Exception("Account temporarily locked due to multiple failed attempts.");
    }

    // 2. Vérification Rate Limit
    if (!self::checkRateLimit($username, 'login')) {
      self::logSecurityEvent('Rate limit exceeded for authentication', ['username' => $username]);
      throw new Exception("Rate limit exceeded. Please try again later.");
    }

    // 3. Validation des identifiants (sécurisée)
    if (!self::validateCredentials($username, $key)) {
      // 4. Gestion de l'échec
      self::incrementFailedAttempts($username);
      self::logSecurityEvent('Authentication failed', ['username' => $username]);
      throw new Exception("Invalid username or key.");
    }

    // Si on arrive ici, l'authentification est réussie et les tentatives échouées ont été réinitialisées.
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
        if (in_array($key, ['token', 'old_token', 'new_token', 'session_id', 'mcp_id'], true)) {
          // Hashage du token pour le log (stocker seulement les 12 premiers chars du hash)
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

      $logDir = CLICSHOPPING_BASE_DIR . 'Work/Log/';
      $logFile = $logDir . 'mcp_security.log';
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
      // Dernier recours si le log échoue
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
   * @param int $mcp_id The API ID used to retrieve the associated IP address.
   * @return bool Returns true if IP is allowed, false otherwise.
   * @throws Exception If validation fails due to database error
   */
  public static function validateIp(int $mcp_id): bool
  {
    if ($mcp_id <= 0) {
      self::logSecurityEvent('Invalid API ID in IP check', ['mcp_id' => $mcp_id]);
      return false;
    }

    try {
      $CLICSHOPPING_Db = Registry::get('Db');

      if (!$CLICSHOPPING_Db) {
        throw new Exception("Database connection not available");
      }

      $clientIp = HTTP::getIpAddress();

      if (empty($clientIp)) {
        self::logSecurityEvent('Unable to determine client IP', ['mcp_id' => $mcp_id]);
        return false;
      }

      $Qips = $CLICSHOPPING_Db->get('mcp_ip', 'ip', ['mcp_id' => $mcp_id]);

      // Si aucune restriction IP n'est trouvée, l'accès doit être refusé par défaut pour la sécurité
      if ($Qips->rowCount() === 0) {
        self::logSecurityEvent('No IP restrictions found for API - access denied', [
          'mcp_id' => $mcp_id,
          'client_ip' => $clientIp
        ]);

        return false;
      }

      foreach ($Qips as $allowedIp) {
        $ip = $allowedIp['ip'];

        if ($ip === '127.0.0.1' || $ip === 'localhost') {
          if (in_array($clientIp, ['127.0.0.1', '::1'])) {
            self::logSecurityEvent('Localhost access granted', [
              'mcp_id' => $mcp_id,
              'client_ip' => $clientIp
            ]);

            return true;
          }
        } elseif ($ip === $clientIp) {
          self::logSecurityEvent('IP match found', [
            'mcp_id' => $mcp_id,
            'client_ip' => $clientIp,
            'allowed_ip' => $ip
          ]);

          return true;
        } elseif (self::ipInRange($clientIp, $ip)) {
          self::logSecurityEvent('IP in allowed range', [
            'mcp_id' => $mcp_id,
            'client_ip' => $clientIp,
            'range' => $ip
          ]);

          return true;
        }
      }

      self::logSecurityEvent('IP access denied', [
        'mcp_id' => $mcp_id,
        'client_ip' => $clientIp,
        'allowed_ips' => array_column($Qips->toArray(), 'ip')
      ]);

      return false;

    } catch (PDOException $e) {
      self::logSecurityEvent('Database error in IP check', [
        'mcp_id' => $mcp_id,
        'error' => $e->getMessage()
      ]);

      throw new Exception("IP validation failed");
    }
  }

  /**
   * Check if an IP address is within a given range (CIDR)
   *
   * @param string $ip The IP address to check
   * @param string $range The CIDR range (e.g., '192.168.1.0/24')
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
  public static function secureGetId(int|string $id): void
  {
    if ($id !== null) {
      if ($id !== 'All' && !ctype_digit($id)) {
        // En mode API, il est préférable de ne pas utiliser exit(json_encode) dans une méthode de sécurité statique
        // qui pourrait être appelée avant que la réponse soit structurée.
        // Mieux vaut lancer une exception pour la gestion des erreurs du contrôleur.
        throw new Exception('Invalid Id format provided.');
      }
    }
  }
}