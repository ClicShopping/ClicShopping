<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\OM;

use PasswordHash;

use Exception;
use InvalidArgumentException;
use function count;
use function in_array;
use function strlen;

/**
 * Encrypts a plaintext string using the specified algorithm.
 * Supported algorithms are 'default', 'bcrypt', 'argon2id', 'phpass', and 'salt'.
 * If no algorithm is specified, 'PASSWORD_DEFAULT' is used by default.
 * Triggers a warning if an unknown algorithm is specified and returns false.
 *
 * @param string $plain The plaintext string to encrypt.
 * @param null|string $algo The encryption algorithm to use.
 * @return bool|string The encrypted string or false on failure.
 * @throws Exception If the 'phpass' class cannot load, or in case of failure during random number generation for 'salt'.
 */
class Hash
{
  private static $key; // 32 caractères pour AES-256
  private static $cipher = 'aes-256-cbc'; // Algorithme de chiffremen
  private const EMAIL_PREFIX = 'ENC::'; // Préfixe pour identifier un email chiffré

  /**
   * Hash constructor.
   * @access public
   *
   * Initializes a new Hash object for generating and verifying secure hashes.
   * This class provides functionality for password hashing and verification
   * using industry-standard cryptographic algorithms.
   */
  public function __construct()
  {
    if (CLICSHOPPING::getConfig('encryption_key') && CLICSHOPPING::configExists('encryption_key')) {
      self::$key = CLICSHOPPING::getConfig('encryption_key'); // Clé hexadécimale (32 caractères)
    } else {
      self::$key = '1c5f37542a2056c76dc2cfe98fecb514'; // Valeur par défaut (32 caractères hex)
    }

    self::$key = hex2bin(self::$key);

    if (self::$key === false || strlen(self::$key) !== 32) {
      throw new Exception('Clé d’encryption invalide');
    }
  }

  /**
   * Encrypts a plain text string using the specified algorithm.
   *
   * Supported algorithms:
   * - 'default' or null: Uses the default PHP password hashing algorithm.
   * - 'bcrypt': Uses the Bcrypt hashing algorithm.
   * - 'argon2id': Uses the Argon2id hashing algorithm.
   * - 'phpass': Uses the Phpass library for password hashing.
   * - 'salt': Applies a custom MD5-based hashing with a salt.
   *
   * @param string $plain The plain text string to be encrypted
   */
  public static function encrypt(string $plain, string|null $algo = null)
  {
    if (!isset($algo) || $algo == 'default' || $algo == 'bcrypt' || $algo == 'argon2id') {
      if (!isset($algo) || ($algo == 'default')) {
        $algo = PASSWORD_DEFAULT;
      } elseif ($algo == 'bcrypt') {
        $algo = PASSWORD_BCRYPT;
      } elseif ($algo == 'argon2id') {
        $algo = PASSWORD_ARGON2ID;
      }

      return password_hash($plain, $algo);
    }

    if ($algo == 'phpass') {
      if (!class_exists('PasswordHash', false)) {
        include_once(CLICSHOPPING::BASE_DIR . 'External/PasswordHash.php');
      }

      $hasher = new PasswordHash(10, true);

      return $hasher->HashPassword($plain);
    }

    if ($algo == 'salt') {
      // SECURITY WARNING: The 'salt' algorithm is DEPRECATED and INSECURE
      // - Uses MD5 (cryptographically broken since 2004)
      // - Uses only 2-character salt (256 possible values)
      // - No key stretching or iteration
      // - Vulnerable to rainbow table and brute force attacks
      trigger_error(
        'ClicShopping\\OM\\Hash::encrypt() Algorithm "salt" is DEPRECATED and INSECURE. ' .
        'Use "bcrypt" or "argon2id" instead. Creating new "salt" hashes is not allowed. ' .
        'This algorithm will be removed in a future version.',
        E_USER_DEPRECATED
      );
      
      // Refuse to create new insecure hashes - force upgrade to secure algorithm
      return false;
    }

    trigger_error('ClicShopping\\OM\\Hash::encrypt() Algorithm "' . $algo . '" unknown.');

    return false;
  }

  /**
   * Verifies if the given plain text matches the provided hashed value using various hashing mechanisms.
   *
   * @param string $plain The plain text string to verify.
   * @param string $hash The hashed value to compare against.
   * @return bool Returns true if the plain text matches the hashed value, otherwise false.
   */
  public static function verify(string $plain, string $hash): bool
  {
    $result = false;

    if ((strlen($plain) > 0) && (strlen($hash) > 0)) {
      $type = static::getType($hash);
      
      // Log security warning for deprecated algorithms
      if (in_array($type, ['salt', 'phpass'], true)) {
        trigger_error(
          "ClicShopping\\OM\\Hash::verify() Verifying password with DEPRECATED algorithm '{$type}'. " .
          "This algorithm is insecure and should be migrated. Consider forcing password reset for this user.",
          E_USER_WARNING
        );
      }
      
      switch ($type) {
        case 'phpass':
          if (!class_exists('PasswordHash', false)) {
            include_once(BASE_DIR . 'external/PasswordHash.php');
          }

          $hasher = new PasswordHash(10, true);

          $result = $hasher->checkPassword($plain, $hash);

          break;

        case 'salt':
          // SECURITY WARNING: Still verify for backward compatibility, but this is INSECURE
          // split apart the hash / salt
          $stack = explode(':', $hash, 2);

          if (count($stack) === 2) {
            $result = (md5($stack[1] . $plain) === $stack[0]);
          } else {
            $result = false;
          }

          break;

        default:
          $result = password_verify($plain, $hash);

          break;
      }
    }

    return $result;
  }

  /**
   * Determines if a hashed password needs to be rehashed with a different algorithm or cost.
   *
   * @param string $hash The hashed password to verify.
   * @param string|null $algo The algorithm to use for verifying the hash. Can be 'default', 'bcrypt', 'argon2id', or null for the default algorithm. Defaults to null.
   * @return bool Returns true if the hash needs to be rehashed, false otherwise.
   */
  public static function needsRehash(string $hash, ?string $algo = null)
  {
    if (!isset($algo) || $algo == 'default') {
      $algo = PASSWORD_DEFAULT;
    } elseif ($algo == 'bcrypt') {
      $algo = PASSWORD_BCRYPT;
    } elseif ($algo == 'argon2id') {
      $algo = PASSWORD_ARGON2ID;
    }

    if (!is_int($algo)) {
      trigger_error('ClicShopping\OM\Hash::needsRehash() Algorithm "' . $algo . '" not supported.');
    }

    return password_needs_rehash($hash, $algo);
  }

  /**
   * Checks if a password hash uses a deprecated/insecure algorithm that requires migration.
   * 
   * @param string $hash The password hash to check
   * @return bool Returns true if the hash uses 'salt' or 'phpass' (deprecated algorithms)
   */
  public static function needsMigration(string $hash): bool
  {
    $type = static::getType($hash);
    return in_array($type, ['salt', 'phpass'], true);
  }

  /**
   * Migrates a password from deprecated algorithm to secure algorithm.
   * This method verifies the old password first, then creates a new secure hash.
   * 
   * @param string $plain The plaintext password
   * @param string $oldHash The old hash to verify
   * @param string $newAlgo The new algorithm to use (default: 'bcrypt')
   * @return string|false The new hash or false if verification failed
   */
  public static function migratePassword(string $plain, string $oldHash, string $newAlgo = 'bcrypt')
  {
    // Verify the old password first
    if (!static::verify($plain, $oldHash)) {
      return false;
    }
    
    // Create new secure hash
    return static::encrypt($plain, $newAlgo);
  }

  /**
   * Determines the type of a given hash based on its characteristics.
   *
   * @param string $hash The hash string to analyze and determine its type.
   * @return string|null Returns the hash type as a string if recognized, or null if not found.
   */
  public static function getType(string $hash): ?string
  {
    $info = password_get_info($hash);

    if ($info['algo'] > 0) {
      return $info['algoName'];
    }

    if (substr($hash, 0, 3) == '$P$') {
      return 'phpass';
    }

    if (preg_match('/^[A-Z0-9]{32}\:[A-Z0-9]{2}$/i', $hash) === 1) {
      return 'salt';
    }

    trigger_error('ClicShopping\OM\Hash::getType() hash type not found for "' . substr($hash, 0, 5) . '"');

    return '';
  }

  /**
   * Generates a random integer within the specified range.
   * If a secure random number cannot be generated, a less secure fallback is used if allowed.
   *
   * @param int|null $min The minimum value of the range. Defaults to 0 if not specified.
   * @param int|null $max The maximum value of the range. Defaults to PHP_INT_MAX if not specified.
   * @param bool $secure Whether to strictly use cryptographically secure random numbers. Defaults to true.
   * @return int A random integer
   */
  public static function getRandomInt($min = null, $max = null, bool $secure = true)
  {
    if (!isset($min)) {
      $min = 0;
    }

    if (!isset($max)) {
      $max = PHP_INT_MAX;
    }

    try {
      $result = random_int($min, $max);
    } catch (Exception $e) {
      if ($secure === true) {
        throw $e;
      }

      $result = mt_rand($min, $max);
    }

    return $result;
  }

  /**
   * Generates a random string based on the specified type and length.
   *
   * @param int $length The length of the random string to be generated.
   * @param string $type The type of characters to include in the random string.
   *                      Accepted values are:
   *                      - 'mixed': Includes both characters and digits.
   *                      - 'chars': Includes only alphabetical characters.
   *                      - 'digits': Includes only numerical digits.
   *                      Defaults to 'mixed'.
   * @return string The generated random string of the specified length and type.
   * @throws InvalidArgumentException If
   */
  public static function getRandomString(int $length, string $type = 'mixed'): string
  {
    $types = ['mixed', 'chars', 'digits'];
    if (!in_array($type, $types, true)) {
      throw new InvalidArgumentException("Hash::getRandomString() invalid type: {$type}");
    }

    $sets = [
      'chars' => 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ',
      'digits' => '0123456789',
    ];

    $base = match ($type) {
      'chars' => $sets['chars'],
      'digits' => $sets['digits'],
      default => $sets['chars'] . $sets['digits'],
    };

    $result = '';
    $baseLength = strlen($base);

    while (strlen($result) < $length) {
      $bytes = static::getRandomBytes($length);
      for ($i = 0, $n = strlen($bytes); $i < $n && strlen($result) < $length; $i++) {
        $index = ord($bytes[$i]) % $baseLength;
        $result .= $base[$index];
      }
    }

    return $result;
  }

  /**
   * Generates a string of random bytes with a specified length.
   * If the `secure` parameter is true and a cryptographically secure source of randomness
   * is unavailable, an exception will be thrown.
   *
   * @param int $length The number of random bytes to generate.
   * @param bool $secure Whether to enforce the use of a cryptographically secure method. Defaults to true.
   * @return string A string containing the generated random bytes.
   * @throws Exception If a secure random byte generation method is required but unavailable.
   */
  public static function getRandomBytes(int $length, bool $secure = true)
  {
    try {
      $result = random_bytes($length);
    } catch (Exception $e) {
      // If random_bytes() fails, the system's CSPRNG is broken and we should NOT
      // provide an insecure fallback. This is a critical system failure.
      
      if ($secure === false) {
        trigger_error(
          'ClicShopping\\OM\\Hash::getRandomBytes() The $secure=false parameter is deprecated. ' .
          'Insecure random byte generation is not allowed. ' .
          'If random_bytes() fails, the system CSPRNG is broken and must be fixed.',
          E_USER_DEPRECATED
        );
      }
      
      // Always throw exception - no insecure fallback allowed
      throw new Exception(
        'Cannot generate cryptographically secure random bytes. ' .
        'random_bytes() failed and insecure fallback is not allowed. ' .
        'This indicates a critical system issue that must be resolved. ' .
        'Original error: ' . $e->getMessage()
      );
    }

    return $result;
  }

  /**
   * Encrypts the provided data using a specified cipher and key.
   *
   * @param string|null $data The plaintext data to be encrypted.
   * @return string The encrypted data in Base64-encoded format, including the encryption initialization vector.
   * @throws Exception If the encryption process fails.
   */
  public static function encryptDatatext(string|null $data): string
  {
    if (!empty($data) || is_null($data)) {
      $strong = false;
      $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length(self::$cipher), $strong);

      if ($iv === false || $strong === false) {
        throw new Exception('IV generation failed or is not cryptographically strong');
      }

    $encrypted = openssl_encrypt($data, self::$cipher, self::$key, 0, $iv);
    if ($encrypted === false) {
      throw new Exception('Erreur lors du chiffrement des données');
    }

    return base64_encode($encrypted . '::' . $iv);
    } else {
      return '';
    }
  }

  /**
   * Decrypts the provided encrypted data string.
   *
   * @param string $encryptedData The encrypted data in base64 format, containing the encrypted text and IV concatenated with '::'.
   * @return string Returns the decrypted data as a plain text string.
   * @throws Exception If the data format is invalid or decryption fails.
   */
  private static function decryptDatatext(string $encryptedData): string
  {
    $decodedData = base64_decode($encryptedData);
    $parts = explode('::', $decodedData, 2);

    if (count($parts) !== 2) {
      throw new Exception('Données de chiffrement invalides');
    }

    [$encrypted, $iv] = $parts;

    $decrypted = openssl_decrypt($encrypted, self::$cipher, self::$key, 0, $iv);
    if ($decrypted === false) {
      throw new Exception('Erreur lors du déchiffrement des données');
    }

    return $decrypted;
  }

  /**
   * Checks if the given data is an encrypted text.
   *
   * @param string $data The input string to verify if it represents encrypted text.
   * @return bool Returns true if the data appears to be encrypted (valid base64 encoding
   *              containing a separator "::" for the encrypted text and IV), false otherwise.
   */
  private static function isEncryptedDatatext(string $data): bool
  {
    // Vérifie si la donnée est encodée en base64
    if (base64_decode($data, true) === false) {
      return false;
    }

    return count(explode('::', base64_decode($data), 2)) === 2;
  }

  /**
   * Displays the decrypted data text if the input is encrypted,
   * or returns the original string if it is not encrypted.
   *
   * @param string|null $dataString The input string to be handled, which may be encrypted or null.
   * @return string The decrypted string if the input was encrypted, otherwise the original string or an empty string if null was provided.
   */
  public static function displayDecryptedDataText(string|null $dataString): string
  {
    if (!is_null($dataString)) {
      if (self::isEncryptedDatatext($dataString)) {
        $data = self::decryptDatatext($dataString);
      } else {
        $data = $dataString;
      }
    } else {
      $data = '';
    }

    return $data;
  }

/**
   * Hashes an email address using a secure one-way hashing algorithm.
   *
   * @param string $email The email address to be hashed.
   * @return string The hashed email address.
   */
  public static function encryptEmail(string $email): string {
    $strong = false;
    $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length(self::$cipher), $strong);

    if ($iv === false || $strong === false) {
      throw new Exception('IV generation failed or is not cryptographically strong');
    }

    $encrypted = openssl_encrypt($email, self::$cipher, self::$key, 0, $iv);
    return base64_encode($iv . $encrypted);
  }

  /**
   * Decrypts an email address if encrypted, or returns it as-is if already plain text.
   * This function handles both encrypted (base64) and plain text email addresses.
   *
   * @param string $encryptedEmail The email address (encrypted or plain text).
   * @return string The decrypted or plain email address.
   */
  public static function displayDecryptedEmail(string $encryptedEmail): string {
    // Check if email is actually encrypted (base64 encoded)
    // If it contains @ and looks like a plain email, return as-is
    if (filter_var($encryptedEmail, FILTER_VALIDATE_EMAIL)) {
      return $encryptedEmail;
    }
    
    // Try to decrypt
    $data = base64_decode($encryptedEmail, true);
    
    // If base64_decode failed or returned empty, return the original
    if ($data === false || empty($data)) {
      return $encryptedEmail;
    }
    
    $ivLength = openssl_cipher_iv_length(self::$cipher);
    $iv = str_pad(substr($data, 0, $ivLength), $ivLength, "\0");
    $encrypted = substr($data, $ivLength);
    
    $decrypted = openssl_decrypt($encrypted, self::$cipher, self::$key, 0, $iv);
    
    // If decryption failed, return the original value
    return $decrypted !== false ? $decrypted : $encryptedEmail;
  }
/*

// Exemple d'utilisation
$email = "user@example.com";

// Chiffrement (première fois)
$encryptedEmail = Hash::encryptEmail($email);
echo "Email chiffré : " . $encryptedEmail . "\n";

// Stockage du hash pour vérification plus tard
$hashedEmail = Hash::hashEmail($email);
echo "Hash de l'email : " . $hashedEmail . "\n";

// Déchiffrement
$decryptedEmail = Hash::displayDecrypteEmail($encryptedEmail);
echo "Email déchiffré : " . $decryptedEmail . "\n";

// Vérification d'un email
$isValid = Hash::verifyEmail("user@example.com", $hashedEmail);
echo "L'email est valide ? " . ($isValid ? "Oui" : "Non") . "\n";
*/
}
