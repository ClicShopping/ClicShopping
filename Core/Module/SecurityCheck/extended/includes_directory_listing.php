<?php
  /**
   *
   * @copyright 2008 - https://www.clicshopping.org
   * @Brand : ClicShoppingAI(TM) at Inpi all right Reserved
   * @Licence GPL 2 & MIT
   * @Info : https://www.clicshopping.org/forum/trademark/
   *
   */

  use ClicShopping\OM\CLICSHOPPING;
  use ClicShopping\OM\HTTP;
  use ClicShopping\OM\Registry;

  class securityCheckExtended_includes_directory_listing
  {
    public string $type = 'warning';
    public bool $has_doc = true;
    public string $title;

    public function __construct()
    {
      $CLICSHOPPING_Language = Registry::get('Language');

      $CLICSHOPPING_Language->loadDefinitions('modules/security_check/extended/Core_directory_listing', null, null, 'Shop');

      $this->title = CLICSHOPPING::getDef('module_security_check_extended_includes_directory_listing_title');
    }

    /**
     * Returns true if the Core/ directory is NOT publicly browsable (403, 404, error).
     * Returns false only when a real HTTP 200 is received (directory listing exposed).
     */
    public function pass(): bool
    {
      return $this->getHttpRequest(CLICSHOPPING::link('Shop/Core/')) !== 200;
    }

    public function getMessage(): string
    {
      return CLICSHOPPING::getDef('module_security_check_extended_includes_directory_listing_http_200');
    }

    /**
     * Sends an HTTP HEAD request and returns the HTTP status code as int,
     * or null on any failure / non-2xx response.
     */
    public function getHttpRequest(string $url): ?int
    {
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

      // Suppress the trigger_error fired by HTTP::getResponse() on non-2xx responses.
      // 403 is the expected secure response here — not an application error.
      set_error_handler(static function () { return true; });

      try {
        $responseData = HTTP::getResponse($options);
      } catch (\Throwable $e) {
        $responseData = false;
      } finally {
        restore_error_handler();
      }

      if ($responseData === false || !is_array($responseData)) {
        return null;
      }

      $info = $responseData['info'] ?? null;

      if (!is_array($info)) {
        return null;
      }

      return (int)($info['http_code'] ?? 0) ?: null;
    }
  }