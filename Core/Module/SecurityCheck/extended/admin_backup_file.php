<?php
  /**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

  use ClicShopping\OM\CLICSHOPPING;
  use ClicShopping\OM\HTTP;
  use ClicShopping\OM\Registry;

  /**
   * This class performs a security check to ensure that no backup files
   * are publicly accessible in the administrator backup directory.
   */
  class securityCheckExtended_admin_backup_file
  {
    public string $type = 'danger';
    public bool $has_doc = true;
    public string $title;

    public function __construct()
    {
      $CLICSHOPPING_Language = Registry::get('Language');

      $CLICSHOPPING_Language->loadDefinitions('modules/security_check/extended/admin_backup_file', null, null, 'Shop');

      $this->title = CLICSHOPPING::getDef('module_security_check_extended_admin_backup_file_title');
    }

    /**
     * Checks whether any backup file in the backup directory is publicly accessible.
     *
     * @return bool True if no backup file is accessible (safe), false if one is reachable via HTTP 200.
     */
    public function pass(): bool
    {
      $backup_directory = CLICSHOPPING::BASE_DIR . 'Work/Backups/';
      $backup_file = null;

      if (is_dir($backup_directory)) {
        $dir = dir($backup_directory);
        $contents = [];

        while ($file = $dir->read()) {
          if (!is_dir($backup_directory . $file)) {
            $ext = substr($file, strrpos($file, '.') + 1);

            if (in_array($ext, ['zip', 'sql', 'gz']) && !isset($contents[$ext])) {
              $contents[$ext] = $file;

              if ($ext !== 'sql') { // zip and gz (binaries) prioritised over sql (plain text)
                break;
              }
            }
          }
        }

        $dir->close();

        if (isset($contents['zip'])) {
          $backup_file = $contents['zip'];
        } elseif (isset($contents['gz'])) {
          $backup_file = $contents['gz'];
        } elseif (isset($contents['sql'])) {
          $backup_file = $contents['sql'];
        }
      }

      if (!isset($backup_file)) {
        return true; // No backup files found — nothing to expose
      }

      if (!is_file($backup_directory . $backup_file)) {
        return true;
      }

      $httpCode = $this->getHttpRequest(HTTP::getShopUrlDomain() . 'Core/Work/Backups/' . $backup_file);

      // Only fail if we got a real 200 OK — anything else (403, 404, error) means protected
      return $httpCode !== 200;
    }

    public function getMessage(): string
    {
      return CLICSHOPPING::getDef('module_security_check_extended_admin_backup_file_http_200', [
        'backups_path' => CLICSHOPPING::getConfig('http_path', 'Shop') . 'Core/ClicShopping/Work/Backups/'
      ]);
    }

    /**
     * Sends a HEAD request to $url and returns the HTTP status code,
     * or null if the request fails (connection error, 403, 404, etc.).
     *
     * @param string|null $url
     * @return int|null HTTP status code on success, null on any error/non-2xx response.
     */
    public function getHttpRequest(?string $url): ?int
    {
      if (empty($url)) {
        return null;
      }

      $server = parse_url($url);

      if (empty($server['scheme']) || empty($server['host'])) {
        return null;
      }

      $server['port'] ??= ($server['scheme'] === 'https') ? 443 : 80;
      $server['path'] ??= '/';

      $cleanUrl = $server['scheme'] . '://' . $server['host'] . $server['path']
        . (isset($server['query']) ? '?' . $server['query'] : '');

      $options = [
        'url'     => $cleanUrl,
        'method'  => 'HEAD',
        'port'    => $server['port'],
        'timeout' => 10,
        'headers' => [],
      ];

      if (isset($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW'])) {
        $options['auth'] = $_SERVER['PHP_AUTH_USER'] . ':' . $_SERVER['PHP_AUTH_PW'];
        $this->type = 'warning';
      }

      // Suppress trigger_error fired by HTTP::getResponse() on non-2xx responses.
      // 403/404 are expected and mean the file is protected — not an application error.
      set_error_handler(static function () { return true; });

      try {
        $responseData = HTTP::getResponse($options);
      } catch (\Throwable $e) {
        $responseData = false;
      } finally {
        restore_error_handler();
      }

      if ($responseData === false || !is_array($responseData)) {
        return null; // Request failed or directory is protected — treat as safe
      }

      $info = $responseData['info'] ?? null;

      if (!is_array($info)) {
        return null;
      }

      return (int)($info['http_code'] ?? 0) ?: null;
    }
  }