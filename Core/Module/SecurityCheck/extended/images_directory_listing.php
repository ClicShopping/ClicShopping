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
 * Class securityCheckExtended_images_directory_listing
 *
 * This class is responsible for performing a security check to determine
 * if the images directory is accessible and potentially listing its contents.
 * It uses an HTTP request to verify the server response and assess whether
 * access is restricted.
 */
class securityCheckExtended_images_directory_listing
{
  public string $type = 'warning';
  public $has_doc = true;
  public string $title;

  /**
   * Constructor method.
   *
   * Loads the language definitions for the security check extended images directory listing module
   * and sets the title property with the corresponding definition.
   *
   * @return void
   */
  public function __construct()
  {
    $CLICSHOPPING_Language = Registry::get('Language');

    $CLICSHOPPING_Language->loadDefinitions('modules/security_check/extended/images_directory_listing', null, null, 'Shop');

    $this->title = CLICSHOPPING::getDef('module_security_check_extended_images_directory_listing_title');
  }

  /**
   * Checks if the HTTP request to the specified URL does not return a 200 HTTP status code.
   *
   * @return bool True if the HTTP status code is not 200, false otherwise.
   */
  public function pass()
  {
    $request = $this->getHttpRequest(CLICSHOPPING::link('Shop/images/'));

    return $request['http_code'] != 200;
  }

  /**
   * Retrieves the defined message for the extended security check module regarding HTTP 200 status.
   *
   * @return string Returns the localized message associated with the HTTP 200 status check.
   */
  public function getMessage()
  {
    return CLICSHOPPING::getDef('module_security_check_extended_images_directory_listing_http_200');
  }

  /**
   * Makes an HTTP HEAD request to the specified URL and retrieves connection information.
   *
   * @param string $url The URL to which the HTTP request should be made.
   * @return array|string An array of connection details if successful, or a string 'error' if an error occurs.
   */
  public function getHttpRequest(string $url): array
  {
    $result = HTTP::getResponse(['url' => $url, 'method' => 'head']);

    return ['http_code' => $result !== false ? 200 : 0];
  }
}