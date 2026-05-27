<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\AI\Ecommerce\Classes\Shop\ACP;

use ClicShopping\OM\CLICSHOPPING;

/**
 * Class GptSessionManagerACP
 *
 * Persists ACP (Agentic Commerce Protocol / OpenAI) checkout sessions on disk.
 * Symmetric to the UCP\GptSessionManager but without TTL expiry, since the ACP
 * specification does not mandate server-side session expiration.
 */
class GptSessionManagerACP
{
  protected string $dirSession;

  public function __construct()
  {
    $this->dirSession = CLICSHOPPING::BASE_DIR . 'Work/Session/Shop/ACP';

    if (!is_dir($this->dirSession)) {
      mkdir($this->dirSession, 0775, true);
    }
  }

  /**
   * Generate a new session ID following the ACP convention.
   */
  public function generateSessionId(): string
  {
    return uniqid('cs_');
  }

  /**
   * Persist a full session payload (overwrite if it already exists).
   */
  public function save(string $sessionId, array $session): bool
  {
    $file = $this->getFilePath($sessionId);
    $payload = ['checkout_session' => $session];

    return (bool)file_put_contents($file, json_encode($payload, JSON_UNESCAPED_SLASHES));
  }

  /**
   * Read a session by ID.
   *
   * @return array|null The 'checkout_session' payload, or null if missing/invalid.
   */
  public function get(string $sessionId): ?array
  {
    $file = $this->getFilePath($sessionId);
    if (!file_exists($file)) {
      return null;
    }

    $payload = json_decode(file_get_contents($file), true);
    if (!is_array($payload) || !isset($payload['checkout_session'])) {
      return null;
    }

    return $payload['checkout_session'];
  }

  /**
   * Check whether a session file exists on disk.
   */
  public function exists(string $sessionId): bool
  {
    return file_exists($this->getFilePath($sessionId));
  }

  /**
   * Delete a session file.
   */
  public function delete(string $sessionId): bool
  {
    $file = $this->getFilePath($sessionId);
    if (!file_exists($file)) {
      return false;
    }

    return unlink($file);
  }

  /**
   * List all stored sessions.
   *
   * @param bool $fullData If true, returns full session payloads; if false, returns only the IDs.
   * @return array
   */
  public function listAll(bool $fullData = true): array
  {
    $sessions = [];

    if (!is_dir($this->dirSession)) {
      return $sessions;
    }

    $files = glob($this->dirSession . '/*.json') ?: [];

    foreach ($files as $file) {
      $sessionId = basename($file, '.json');

      if ($fullData) {
        $payload = json_decode(file_get_contents($file), true);
        if (is_array($payload) && isset($payload['checkout_session'])) {
          $sessions[] = $payload['checkout_session'];
        }
      } else {
        $sessions[] = $sessionId;
      }
    }

    return $sessions;
  }

  protected function getFilePath(string $sessionId): string
  {
    return $this->dirSession . '/' . $sessionId . '.json';
  }
}
