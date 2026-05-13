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

  /**
   * This class performs a security check on the admin backup directory listing.
   * It verifies if the backup directory is publicly accessible by making an HTTP GET request.
   * A 403 or 404 response means the directory is protected (pass).
   * A 200 response means it is publicly browsable (fail).
   */
  class securityCheckExtended_admin_backup_directory_listing
  {
    public string $type = 'danger';
    public bool $has_doc = true;
    public string $title;

    public function __construct()
    {
      $CLICSHOPPING_Language = Registry::get('Language');

      $CLICSHOPPING_Language->loadDefinitions('modules/security_check/extended/admin_backup_directory_listing', null, null, 'Shop');

      $this->title = CLICSHOPPING::getDef('module_security_check_extended_admin_backup_directory_listing_title');
    }

    /**
     * Checks if the backup directory is NOT publicly accessible (browsable).
     *
     * @return bool True if directory is protected (non-200 response or unreachable), false if openly browsable.
     */
    public function pass(): bool
    {
      return !$this->getHttpRequest(CLICSHOPPING::link('Shop/Core/ClicShopping/Work/Backups/'));
    }

    public function getMessage(): string
    {
      return CLICSHOPPING::getDef('module_security_check_extended_admin_backup_directory_listing_http_200', [
          'backups_url'  => CLICSHOPPING::link('Shop/Core/ClicShopping/Work/Backups/'),
          'backups_path' => CLICSHOPPING::getConfig('http_path', 'Shop') . 'Core/ClicShopping/Work/Backups/'
        ]
      );
    }

    /**
     * Sends an HTTP GET request and returns true ONLY if the server responds with HTTP 200
     * (meaning directory listing is publicly accessible — a security risk).
     * Any other response (403, 404, error) returns false (directory is protected).
     *
     * @param string $url
     * @return bool
     */
    public function getHttpRequest(string $url): bool
    {
      $data = ['url' => $url, 'method' => 'get'];

      if (isset($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW'])) {
        $data['header'] = [
          'Authorization: Basic ' . base64_encode($_SERVER['PHP_AUTH_USER'] . ':' . $_SERVER['PHP_AUTH_PW'])
        ];
        $this->type = 'warning';
      }

      // Suppress the trigger_error that HTTP::getResponse() fires on non-2xx responses.
      // A 403/404 is expected and desirable here — it means the directory is protected.
      set_error_handler(static function () { return true; });

      try {
        $response = HTTP::getResponse($data);
      } catch (\Throwable $e) {
        $response = false;
      } finally {
        restore_error_handler();
      }

      // Only return true (= security risk) when we actually got a successful 200 response
      return $response !== false && !empty($response);
    }
  }
