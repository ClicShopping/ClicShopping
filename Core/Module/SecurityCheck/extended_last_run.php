<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */


use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

/**
 * Manages and tracks the status of extended security checks for the application.
 */
class securityCheck_extended_last_run
{
  public string $type = 'warning';

  /**
   * Constructor method to initialize the language definitions for the SecurityCheck module.
   *
   * @return void
   */
  public function __construct()
  {
    $CLICSHOPPING_Language = Registry::get('Language');

    $CLICSHOPPING_Language->loadDefinitions('modules/security_check/extended_last_run', null, null, 'Shop');
  }

  /**
   * Checks and processes the extended security check status.
   *
   * @return bool Returns true if the extended security check timestamp is updated or
   *              if the last run timestamp exists and was within the last 30 days; otherwise, false.
   */
  public function pass()
  {

    $CLICSHOPPING_Db = Registry::get('Db');

    if (isset($_GET['SecurityCheck'])) {
      if (defined('MODULE_SECURITY_CHECK_EXTENDED_LAST_RUN_DATETIME')) {
        $CLICSHOPPING_Db->save('configuration', [
          'configuration_value' => time(),
        ], [
            'configuration_key' => 'MODULE_SECURITY_CHECK_EXTENDED_LAST_RUN_DATETIME'
          ]
        );
      } else {

        $CLICSHOPPING_Db->save('configuration', [
            'configuration_title' => 'Security Check Extended Last Run',
            'configuration_key' => 'MODULE_SECURITY_CHECK_EXTENDED_LAST_RUN_DATETIME',
            'configuration_value' => time(),
            'configuration_description' => 'The date and time the last extended security check was performed.',
            'configuration_group_id' => '6',
            'sort_order' => '0',
            'set_function' => '',
            'date_added' => 'now()'
          ]
        );
      }

      return true;
    }

    return defined('MODULE_SECURITY_CHECK_EXTENDED_LAST_RUN_DATETIME') && (MODULE_SECURITY_CHECK_EXTENDED_LAST_RUN_DATETIME > strtotime('-30 days'));
  }

  /**
   * Generates a link to the security check tool with a specific message definition.
   *
   * @return string The generated hyperlink pointing to the security check tool with the corresponding message.
   */
  public function getMessage()
  {
    return HTML::link(CLICSHOPPING::link(null, 'A&Tools\SecurityCheck&SecurityCheck'), CLICSHOPPING::getDef('module_security_check_extended_last_run_old'));
  }
}

