<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\Security;

use ClicShopping\OM\Is\IpAddress;

/**
 * Authorisation policy for outbound AI calls.
 *
 * Every destination the AI layer reaches — LLM chat, embeddings, web search — is declared here
 * before the connection is built. The operator chooses the regime; the code never decides which
 * vendor is acceptable, so this class names no provider and no host.
 *
 * Modes:
 *  - 'open'      : no restriction. The default, so an existing installation behaves as before.
 *  - 'sovereign' : loopback and private networks only — a self-hosted model runs, nothing leaves.
 *  - 'allowlist' : only the hosts the operator declared.
 *
 * @package ClicShopping\AI\Security
 * @since 4.33.0
 */
class OutboundPolicy
{
  public const MODE_OPEN = 'open';
  public const MODE_SOVEREIGN = 'sovereign';
  public const MODE_ALLOWLIST = 'allowlist';

  private const CONST_MODE = 'CLICSHOPPING_APP_CHATGPT_ASY_OUTBOUND_MODE';
  private const CONST_ALLOWED_HOSTS = 'CLICSHOPPING_APP_CHATGPT_ASY_OUTBOUND_ALLOWED_HOSTS';

  // Seeded by ASY/Params, so absent until the configuration screen runs: read under defined(),
  // never declared in TechnicalDefaults — a constant defined there makes saveCfgParam() take its
  // UPDATE branch on a row that does not exist, and the operator's choice is discarded.

  /**
   * The regime in force. An unknown value is treated as 'open': a typo must not silently sever
   * every AI call, and the mode is displayed to the operator who set it.
   *
   * @return string One of the MODE_* constants
   */
  public static function mode(): string
  {
    $mode = \defined(self::CONST_MODE) ? strtolower(trim((string)\constant(self::CONST_MODE))) : self::MODE_OPEN;

    return \in_array($mode, [self::MODE_OPEN, self::MODE_SOVEREIGN, self::MODE_ALLOWLIST], true)
      ? $mode
      : self::MODE_OPEN;
  }

  /**
   * Hosts the operator declared, lowercased, empty entries dropped.
   *
   * @return string[]
   */
  public static function allowedHosts(): array
  {
    $raw = \defined(self::CONST_ALLOWED_HOSTS) ? (string)\constant(self::CONST_ALLOWED_HOSTS) : '';

    $hosts = array_map(
      static fn(string $host): string => strtolower(trim($host)),
      preg_split('/[\s,;]+/', $raw) ?: []
    );

    return array_values(array_filter($hosts, static fn(string $host): bool => $host !== ''));
  }

  /**
   * May the AI layer reach this destination?
   *
   * @param string $url Full URL, or a bare host
   * @param string $purpose What the call is for, for the audit trail
   * @return bool
   */
  public static function isAllowed(string $url, string $purpose = 'llm'): bool
  {
    $mode = self::mode();

    if ($mode === self::MODE_OPEN) {
      return true;
    }

    $host = self::hostOf($url);

    // A destination we cannot name is a destination we cannot authorise.
    if ($host === '') {
      return false;
    }

    return $mode === self::MODE_SOVEREIGN
      ? self::isLocal($host)
      : self::matchesAllowedHost($host);
  }

  /**
   * Same decision, but a refusal stops the call and is journalled.
   *
   * @param string $url Full URL, or a bare host
   * @param string $purpose What the call is for, for the audit trail
   * @return string The url, unchanged, so a caller can wrap its own argument
   * @throws OutboundBlockedException When the policy forbids the destination
   */
  public static function assertAllowed(string $url, string $purpose = 'llm'): string
  {
    if (self::isAllowed($url, $purpose)) {
      return $url;
    }

    $host = self::hostOf($url);
    self::journal($host === '' ? $url : $host, $purpose);

    throw new OutboundBlockedException($host === '' ? $url : $host, self::mode(), $purpose);
  }

  /**
   * Host part of a URL, or the argument itself when it is already a bare host.
   *
   * @param string $url
   * @return string Lowercased host, empty when none could be read
   */
  private static function hostOf(string $url): string
  {
    $url = trim($url);

    if ($url === '') {
      return '';
    }

    $host = parse_url($url, PHP_URL_HOST);

    if (!\is_string($host) || $host === '') {
      // Bare host, possibly with a port: "localhost:1234".
      $host = str_contains($url, '://') ? '' : strtok($url, '/');
      $host = \is_string($host) ? explode(':', $host)[0] : '';
    }

    return strtolower(trim((string)$host, " \t\n\r\0\x0B[]"));
  }

  /**
   * Loopback, link-local, or an RFC 1918 private address — nothing that leaves the site.
   *
   * @param string $host
   * @return bool
   */
  private static function isLocal(string $host): bool
  {
    if ($host === 'localhost' || str_ends_with($host, '.localhost') || str_ends_with($host, '.local')) {
      return true;
    }

    if (!IpAddress::execute($host, 'any')) {
      return false;
    }

    // One implementation of "is this address public", in OM: this policy and the platform's
    // outbound gate must never disagree on what counts as internal.
    return !IpAddress::execute($host, 'public');
  }

  /**
   * Exact host, or any subdomain of a declared entry.
   *
   * @param string $host
   * @return bool
   */
  private static function matchesAllowedHost(string $host): bool
  {
    foreach (self::allowedHosts() as $allowed) {
      if ($host === $allowed || str_ends_with($host, '.' . $allowed)) {
        return true;
      }
    }

    return false;
  }

  /**
   * A refusal must be readable afterwards. Only refusals are written: journalling every allowed
   * call would turn the security log into a request log.
   *
   * @param string $host
   * @param string $purpose
   * @return void
   */
  private static function journal(string $host, string $purpose): void
  {
    try {
      (new SecurityLogger())->logEvent('outbound_blocked', [
        'severity' => 'warning',
        'host' => $host,
        'purpose' => $purpose,
        'mode' => self::mode(),
      ]);
    } catch (\Throwable $e) {
      error_log('[OutboundPolicy] journalling failed: ' . $e->getMessage());
    }
  }
}
