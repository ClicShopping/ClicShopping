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
 * This class performs a security check to ensure the ext/ directory does not expose its contents via directory listing.
 */
class securityCheckExtended_ext_directory_listing
{
  public string $type = 'warning';
  public $has_doc = true;
  public string $title;

  /**
   * Constructor method for initializing the module.
   *
   * @return void
   */
  public function __construct()
  {
    $CLICSHOPPING_Language = Registry::get('Language');

    $CLICSHOPPING_Language->loadDefinitions('modules/security_check/extended/ext_directory_listing', null, null, 'Shop');

    /**
     *
     */
      $this->title = CLICSHOPPING::getDef('module_security_check_extended_ext_directory_listing_title');
  }

  /**
   * Checks if an HTTP request to a specific URL does not return a 200 status code.
   *
   * @return bool Returns true if the HTTP code is not 200, otherwise false.
   */
  public function pass()
  {
    $request = $this->getHttpRequest(CLICSHOPPING::link('Shop/ext/'));

    return $request['http_code'] != 200;
  }

  /**
   * Retrieves a message definition with dynamic placeholders for the external URL and path.
   *
   * @return string The formatted message string including the external URL and path.
   */
  public function getMessage()
  {
    return CLICSHOPPING::getDef('module_security_check_extended_ext_directory_listing_http_200', [
      'ext_url' => CLICSHOPPING::link('Shop/ext/'),
      'ext_path' => CLICSHOPPING::getConfig('http_path', 'Shop') . 'ext/'
    ]);
  }

  /**
   * Sends a HTTP HEAD request to the specified URL and retrieves information about the request.
   *
   * @param string $url The URL to send the HTTP request to.
   * @return array Returns an array with the http_code key.
   */
  public function getHttpRequest(string $url): array
  {
    $result = HTTP::getResponse(['url' => $url, 'method' => 'head']);

    return ['http_code' => $result !== false ? 200 : 0];
  }
}