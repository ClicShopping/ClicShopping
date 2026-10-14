<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\OM;

/**
 * Class responsible for managing cookies within the application.
 */
class Cookies
{
  protected string|null $domain;
  protected string|null $path;

  protected string $name;
  protected string|null $value;
  protected int $expire;
  protected bool $secure;

  protected string|null $sameSite;

  /**
   * Constructor method for initializing the class properties.
   *
   * @return void
   */
  public function __construct()
  {
    $this->domain = CLICSHOPPING::getConfig('http_cookie_domain');
    $this->path = CLICSHOPPING::getConfig('http_cookie_path');
    $this->sameSite = 'Lax';
  }

  /**
   * Sets a cookie with the specified parameters.
   *
   * @param string $name The name of the cookie.
   * @param ?string $value The value of the cookie. Default is an empty string.
   * @param int $expire The time the cookie expires. Default is 0.
   * @param ?string $path The path on the server the cookie is available to. Default is null.
   * @param ?string $domain The domain the cookie is available to. Default is null.
   * @param bool $secure Whether the cookie should only be transmitted over a secure HTTPS connection. Default is true.
   * @param bool $httponly Whether the cookie is accessible only through the HTTP protocol. Default is true.
   * @param ?string $sameSite The SameSite attribute of the cookie ("Lax", "Strict", "None"). Default is 'Lax'.
   * @return bool Returns true if the cookie is set successfully, false otherwise.
   */
  public function set(string $name, ?string $value = '', int $expire = 0, ?string $path = null, ?string $domain = null, bool $secure = true, bool $httponly = true, ?string $sameSite = 'Lax'): bool
  {
    // Sanitize inputs
    $name = htmlspecialchars(strip_tags(trim($name)), ENT_QUOTES, 'UTF-8');
    if ($value !== null) {
      $value = htmlspecialchars(strip_tags($value), ENT_QUOTES, 'UTF-8');
    }

    // Validate SameSite
    $sameSite = in_array($sameSite, ['Strict', 'Lax', 'None'], true) ? $sameSite : $this->sameSite;

    // If SameSite=None, secure must be true
    if ($sameSite === 'None') {
      $secure = true;
    }

    // Prepare cookie options array (PHP 7.3+)
    $options = [
      'expires' => $expire,
      'path' => $path ?? $this->path,
      'domain' => $domain ?? $this->domain,
      'secure' => $secure,
      'httponly' => $httponly,
      'samesite' => $sameSite
    ];

    // Remove any null values from options
    $options = array_filter($options, function($value) {
      return $value !== null;
    });

    try {
      if (PHP_VERSION_ID >= 70300) {
        return setcookie($name, $value ?? '', $options);
      } else {
        // Fallback for older PHP versions
        return setcookie(
          $name,
          $value ?? '',
          $expire,
          $path ?? $this->path,
          $domain ?? $this->domain,
          $secure,
          $httponly
        );
      }
    } catch (\Exception $e) {
      // Log error if needed
      error_log('Cookie setting failed: ' . $e->getMessage());
      return false;
    }
  }

  /**
   * Deletes a cookie by name, with options for path, domain, security, HTTP-only, and SameSite attributes.
   *
   * @param string $name The name of the cookie to delete.
   * @param string|null $path The path on the server in which the cookie will be available. Defaults to null.
   * @param string|null $domain The (sub)domain that the cookie is available to. Defaults to null.
   * @param bool $secure Indicates if the cookie should only be transmitted over a secure HTTPS connection. Defaults to true.
   * @param bool $httponly When set to true, the cookie will be accessible only through the HTTP protocol. Defaults to true.
   * @param string|null $sameSite The SameSite attribute for the cookie, which can be 'Strict', 'Lax', or 'None'. Defaults to null.
   * @return bool Returns true if the cookie deletion is successful, false otherwise.
   */
  public function del(string $name, ?string $path = null, ?string $domain = null, bool $secure = true, bool $httponly = true, ?string $sameSite = null): bool
  {
    if (empty($name)) {
      return false;
    }

    // Sanitize inputs using modern approach
    $name = htmlspecialchars(strip_tags(trim($name)), ENT_QUOTES, 'UTF-8');
    if ($path !== null) {
      $path = htmlspecialchars(strip_tags(trim($path)), ENT_QUOTES, 'UTF-8');
    }
    if ($domain !== null) {
      $domain = htmlspecialchars(strip_tags(trim($domain)), ENT_QUOTES, 'UTF-8');
    }
    if ($sameSite !== null) {
      $sameSite = in_array($sameSite, ['Strict', 'Lax', 'None'], true) ? $sameSite : null;
    }

    // Set multiple cookie variations to ensure complete deletion
    $success = true;
    $pastTime = time() - 3600; // Set expiration to 1 hour in the past

    // Common cookie paths to clean
    $paths = [
      $path ?? $this->path,  // Current path
      '/',                   // Root path
      '',                    // Empty path
      rtrim($path ?? $this->path, '/') // Path without trailing slash
    ];

    // Try to delete cookie with all possible path combinations
    foreach (array_unique($paths) as $cookiePath) {
      $result = $this->set(
        $name,
        '',
        $pastTime,
        $cookiePath,
        $domain ?? $this->domain,
        $secure,
        $httponly,
        $sameSite ?? $this->sameSite
      );
      $success &= $result;
    }

    // Unset from $_COOKIE superglobal if it exists
    if (isset($_COOKIE[$name])) {
      unset($_COOKIE[$name]);
    }

    return $success;
  }

  /**
   * Retrieves the domain.
   *
   * @return string The domain value.
   */
  public function getDomain(): string
  {
    return $this->domain;
  }

  /**
   * Retrieves the current path.
   *
   * @return string|null The current path or null if not set.
   */
  public function getPath(): ?string
  {
    return $this->path;
  }

  /**
   * Sets the domain for the cookie.
   *
   * @param string $domain The domain to be set (e.g., '.example.com')
   * @return string|null The previously set domain, or null if none was set
   * @throws \InvalidArgumentException If the domain is invalid
   */
  public function setDomain(string $domain): ?string
  {
    // Sanitize and validate domain
    $domain = trim(strtolower($domain));

    // Use parse_url to properly handle URLs
    if (str_contains($domain, '://')) {
      $parsed = parse_url($domain);
      $domain = $parsed['host'] ?? $domain;
    }

    // Basic domain validation
    if (!empty($domain)) {
      // Check if domain format is valid
      if (!preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/i', $domain)) {
        throw new \InvalidArgumentException('Invalid domain format. Must be a valid domain name (e.g., example.com or .example.com)');
      }

      // Ensure domain starts with a dot for all subdomains if not a specific subdomain
      if ($domain[0] !== '.' && substr_count($domain, '.') === 1) {
        $domain = '.' . $domain;
      }
    }

    $previous = $this->domain;
    $this->domain = $domain;

    return $previous;
  }

  /**
   * Sets a new value for the path.
   *
   * @param string|null $path The new value for the path, or null.
   * @return string|null The updated path value, or null if not set.
   */
  public function setPath(string|null $path): string|null
  {
    $previous = $this->path;
    $this->path = $path;

    return $previous;
  }

  /**
   * Sets the SameSite attribute value for the cookie.
   *
   * @param string|null $same_site The SameSite attribute value ('Strict', 'Lax', or 'None')
   * @return string|null The previously set SameSite attribute value
   * @throws \InvalidArgumentException If an invalid SameSite value is provided
   */
  public function setSameSite(?string $same_site): ?string
  {
    if ($same_site !== null && !in_array($same_site, ['Strict', 'Lax', 'None'], true)) {
      throw new \InvalidArgumentException('Invalid SameSite value. Must be "Strict", "Lax", or "None"');
    }

    $previous = $this->sameSite;
    $this->sameSite = $same_site;

    return $previous;
  }

  /**
   * Retrieves the SameSite attribute value.
   *
   * @return string|null The SameSite attribute value or null if not set
   */
  public function getSameSite(): ?string
  {
    return $this->sameSite;
  }
}
