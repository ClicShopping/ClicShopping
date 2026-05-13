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
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

/**
 * This class implements a security check to ensure that all database tables
 * use the correct MySQL collation (utf8mb4_unicode_ci).
 */
class securityCheckExtended_mysql_utf8
{
  public string $type = 'warning';
  public $has_doc = true;
  public string $title;

  /**
   *
   * @return void
   */
  public function __construct()
  {
    $CLICSHOPPING_Language = Registry::get('Language');

    $CLICSHOPPING_Language->loadDefinitions('modules/security_check/extended/mysql_utf8', null, null, 'Shop');

    /**
     *
     */
      $this->title = CLICSHOPPING::getDef('module_security_check_extended_mysql_utf8_title');
  }

  /**
   * Checks the database tables to ensure that their collation is set to 'utf8mb4_unicode_ci'.
   *
   * @return bool Returns true if all tables have the correct collation; otherwise, returns false.
   */
  public function pass()
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $Qcheck = $CLICSHOPPING_Db->query('show table status');

    if ($Qcheck->fetch() !== false) {
      do {
        if ($Qcheck->hasValue('Collation') && ($Qcheck->value('Collation') != 'utf8mb4_unicode_ci')) {
          return false;
        }
      } while ($Qcheck->fetch());
    }

    return true;
  }

  /**
   * Retrieves a formatted HTML link to a specific page with a defined error message.
   *
   * @return string The HTML link containing the error message.
   */
  public function getMessage()
  {
    return HTML::link(CLICSHOPPING::link(null, 'A&Tools\DataBaseTables&DataBaseTables'), CLICSHOPPING::getDef('module_security_check_extended_mysql_utf8_error'));
  }
}
