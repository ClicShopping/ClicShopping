<?php
/**
 *
 * @copyright 2008 - https://www.clicshopping.org
 * @Brand : ClicShoppingAI(TM) at Inpi all right Reserved
 * @Licence GPL 2 & MIT
 * @Info : https://www.clicshopping.org/forum/trademark/
 *
 */

use ClicShopping\OM\Cache;
use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

/**
 * This class represents a security check to ensure the software version is up-to-date.
 *
 * It verifies the availability and recency of a cached version check result.
 */
class securityCheckExtended_version_check
{
  public string $type = 'warning';
  public $has_doc = true;
  public string $title;

  /**
   * Initializes the module by loading the required language definitions and setting the module title.
   *
   * @return void
   */
  public function __construct()
  {
    $CLICSHOPPING_Language = Registry::get('Language');

    $CLICSHOPPING_Language->loadDefinitions('modules/security_check/extended/version_check', null, null, 'Shop');

    $this->title = CLICSHOPPING::getDef('module_security_check_extended_version_check_title');
  }

  /**
   * Checks if the version cache exists and if it has been updated within the last 30 days.
   *
   * @return bool Returns true if the version cache exists and is not older than 30 days, otherwise false.
   */
  public function pass()
  {
    $VersionCache = new Cache('clicshopping_version_check');

    return $VersionCache->exists() && ($VersionCache->getTime() > strtotime('-30 days'));
  }

  /**
   * Retrieves the formatted message with a hyperlink for the upgrade error notification.
   *
   * @return string The message containing the link to the upgrade tool along with the associated error description.
   */
  public function getMessage()
  {
    return HTML::link(CLICSHOPPING::link(null, 'A&Tools\Upgrade&Upgrade'), CLICSHOPPING::getDef('module_security_check_extended_version_check_error'));
  }
}