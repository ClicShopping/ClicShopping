<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\AI\Ecommerce\Classes\Shop\UCP;

use ClicShopping\OM\CLICSHOPPING;

class GptSessionManager
{
  protected string $dirSession;
  protected int $ttl;

  public function __construct()
  {
    $this->dirSession = CLICSHOPPING::BASE_DIR . 'Work/Session/Shop/UCP';
    $this->ttl = \defined('CLICSHOPPING_APP_ECOMMERCE_UCP_SESSION_TIMEOUT')
      ? (int)CLICSHOPPING_APP_ECOMMERCE_UCP_SESSION_TIMEOUT
      : 86400;

    if (!is_dir($this->dirSession)) {
      mkdir($this->dirSession, 0775, true);
    }
  }

  public function create(array $sessionData): string
  {
    $sessionId = $this->generateSessionId();
    $this->persist($sessionId, $sessionData);

    return $sessionId;
  }

  public function update(string $sessionId, array $updates): bool
  {
    $data = $this->get($sessionId);
    if ($data === null) {
      return false;
    }

    $merged = array_merge($data, $updates);
    return $this->persist($sessionId, $merged);
  }

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

    if ($this->isExpired($payload['checkout_session'])) {
      $this->delete($sessionId);
      return null;
    }

    return $payload['checkout_session'];
  }

  public function delete(string $sessionId): bool
  {
    $file = $this->getFilePath($sessionId);
    if (!file_exists($file)) {
      return false;
    }

    return unlink($file);
  }

  public function expire(string $sessionId): bool
  {
    $data = $this->get($sessionId);
    if ($data === null) {
      return false;
    }

    $data['expires_at'] = gmdate('c', time() - 60);
    return $this->persist($sessionId, $data);
  }

  protected function persist(string $sessionId, array $data): bool
  {
    $file = $this->getFilePath($sessionId);

    if (!isset($data['id'])) {
      $data['id'] = $sessionId;
    }

    if (!isset($data['created_at'])) {
      $data['created_at'] = gmdate('c');
    }

    $data['updated_at'] = gmdate('c');

    if (!isset($data['expires_at'])) {
      $data['expires_at'] = gmdate('c', time() + $this->ttl);
    }

    $payload = [
      'checkout_session' => $data
    ];

    return (bool)file_put_contents($file, json_encode($payload, JSON_UNESCAPED_SLASHES));
  }

  protected function load(string $sessionId): ?array
  {
    $file = $this->getFilePath($sessionId);
    if (!file_exists($file)) {
      return null;
    }

    $payload = json_decode(file_get_contents($file), true);
    if (!is_array($payload)) {
      return null;
    }

    return $payload;
  }

  protected function generateSessionId(): string
  {
    return uniqid('cs_', true);
  }

  protected function getFilePath(string $sessionId): string
  {
    return $this->dirSession . '/' . $sessionId . '.json';
  }

  protected function isExpired(array $session): bool
  {
    if (empty($session['expires_at'])) {
      return false;
    }

    return strtotime($session['expires_at']) < time();
  }
}
